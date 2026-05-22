<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Biodata;
use App\Events\NotificationSent;
use Illuminate\Support\Str;

class SupportTicketController extends Controller
{
    // ── ১. সব সাপোর্ট টিকিট লোড করা (ফিল্টার ও সার্চ সহ) ──
    public function index(Request $request)
    {
        // 🔴 অভিযোগকারী (user.biodata) এবং অভিযুক্ত (reportedBiodata.user) উভয়ের রিলেশন আনা হচ্ছে
        $query = SupportTicket::with(['user.biodata', 'reportedBiodata.user']);

        // ১. বিস্তারিত সার্চ ফিল্টার
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('biodata_no', 'like', "%{$search}%") // 🔴 অভিযুক্ত বায়োডাটা নং দিয়ে সার্চ

                  // 🔴 অভিযোগকারীর (Reporter) তথ্য দিয়ে সার্চ
                  ->orWhereHas('user', function($uq) use ($search) {
                      $uq->where('id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhereHas('biodata', function($bq) use ($search) {
                            $bq->where('biodata_no', 'like', "%{$search}%"); // অভিযোগকারীর বায়োডাটা নং
                        });
                  })

                  // 🔴 অভিযুক্ত ব্যক্তির (Reported Owner) তথ্য দিয়ে সার্চ
                  ->orWhereHas('reportedBiodata.user', function($ruq) use ($search) {
                       $ruq->where('name', 'like', "%{$search}%")
                           ->orWhere('mobile', 'like', "%{$search}%")
                           ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // ২. স্ট্যাটাস ফিল্টার
        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // ৩. ক্যাটাগরি ফিল্টার
        if ($request->category && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        // 🔴 ৪. ডাইনামিক সর্টিং (তারিখ অনুযায়ী)
        $sortBy = $request->sort_by ?? 'created_at';
        $sortDir = $request->sort_dir ?? 'desc';

        $allowedSorts = ['created_at', 'id'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->latest();
        }

        $tickets = $query->paginate($request->per_page ?? 20);

        // Stats ক্যালকুলেশন
        $stats = [
            'total_tickets' => SupportTicket::count(),
            'pending_tickets' => SupportTicket::where('status', 'pending')->count(),
            'resolved_tickets' => SupportTicket::where('status', 'resolved')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $tickets,
            'stats' => $stats
        ]);
    }

    // ── ২. নির্দিষ্ট একটি টিকিটের বিস্তারিত দেখা ──
    public function show($id)
    {
        $ticket = SupportTicket::with('user:id,name,email,mobile')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $ticket
        ]);
    }


  // ── ৩. অ্যাডমিন রিপ্লাই দেওয়া এবং স্ট্যাটাস আপডেট করা (রিয়েল-টাইম নোটিফিকেশন সহ) ──
    public function reply(Request $request, $id)
    {
        $request->validate([
            'admin_reply' => 'required|string',
            'status' => 'required|in:resolved'
        ]);

        $ticket = SupportTicket::findOrFail($id);

        $ticket->update([
            'admin_reply' => $request->admin_reply,
            'status' => $request->status
        ]);

        // ইউজারের কাছে নোটিফিকেশন পাঠানো
        if ($ticket->user) {

            $categoryText = $ticket->category === 'biodata_report' ? 'রিপোর্টের' : 'সাপোর্ট টিকিটের';

            $notificationData = [
                'title' => "আপনার {$categoryText} উত্তর এসেছে!",
                'message' => "অ্যাডমিন আপনার {$categoryText} (Ticket #{$ticket->id}) একটি উত্তর দিয়েছেন। বিস্তারিত দেখতে এখানে ক্লিক করুন।",
                'link' => '/user/support' // ইউজারের সাপোর্ট প্যানেলের লিংক
            ];

            // 🔴 ১. ফিক্সড: TicketResolvedNotification এর বদলে UserAlertNotification ব্যবহার করা হলো
            $ticket->user->notify(new \App\Notifications\UserAlertNotification(
                $notificationData['title'],
                $notificationData['message'],
                $notificationData['link']
            ));

            // 🔴 ২. রিয়েল-টাইম নোটিফিকেশন (Reverb) ফায়ার করা
            try {
                $notificationObj = [
                    'id' => \Illuminate\Support\Str::uuid()->toString(),
                    'type' => 'App\\Notifications\\UserAlertNotification', // 🔴 এখানেও টাইপ চেঞ্জ করা হলো
                    'notifiable_type' => 'App\\Models\\User',
                    'notifiable_id' => $ticket->user->id,
                    'data' => $notificationData,
                    'read_at' => null,
                    'created_at' => now()->toISOString(),
                    'updated_at' => now()->toISOString(),
                ];

                event(new \App\Events\NotificationSent($ticket->user, $notificationObj));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Ticket Reply Realtime Notification Error: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'সফলভাবে উত্তর দেওয়া হয়েছে এবং টিকিটের স্ট্যাটাস আপডেট করা হয়েছে।'
        ]);
    }

    // ── ৪. অপ্রয়োজনীয় বা স্প্যাম টিকিট ডিলিট করা ──
    public function destroy($id)
    {
        $ticket = SupportTicket::findOrFail($id);

        if ($ticket->attachment) {
            Storage::disk('public')->delete($ticket->attachment);
        }

        $ticket->delete();

        return response()->json([
            'success' => true,
            'message' => 'টিকিটটি সফলভাবে মুছে ফেলা হয়েছে।'
        ]);
    }
}
