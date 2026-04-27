<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Biodata;
use App\Models\HasFactory;

class SupportTicketController extends Controller
{
    // ── ১. সব সাপোর্ট টিকিট লোড করা (ফিল্টার ও সার্চ সহ) ──
public function index(Request $request)
    {
        // টিকিট, ইউজার এবং ইউজারের বায়োডাটা রিলেশনসহ কুয়েরি
        $query = SupportTicket::with(['user.biodata']);

        // ১. সার্চ ফিল্টার (নাম, আইডি, ইমেইল, মোবাইল, বায়োডাটা নং, টিকিট সাবজেক্ট)
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('biodata_no', 'like', "%{$search}%") // অভিযোগের বায়োডাটা নং
                  ->orWhereHas('user', function($uq) use ($search) {
                      $uq->where('id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhereHas('biodata', function($bq) use ($search) {
                            $bq->where('biodata_no', 'like', "%{$search}%"); // ইউজারের নিজস্ব বায়োডাটা নং
                        });
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

        // পেজিনেশন ও সর্টিং
        $tickets = $query->latest()->paginate($request->per_page ?? 20);

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
        // 🔴 mobile_number এর বদলে mobile করা হয়েছে
        $ticket = SupportTicket::with('user:id,name,email,mobile')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $ticket
        ]);
    }

    // ── ৩. অ্যাডমিন রিপ্লাই দেওয়া এবং স্ট্যাটাস আপডেট করা ──
// ── ৩. অ্যাডমিন রিপ্লাই দেওয়া এবং স্ট্যাটাস আপডেট করা ──
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
        // 🔴 ইউজারের কাছে নোটিফিকেশন পাঠানো
    if ($ticket->user) {
        $ticket->user->notify(new TicketResolvedNotification($ticket));
    }

        return response()->json([
            'success' => true,
            'message' => 'সফলভাবে উত্তর দেওয়া হয়েছে এবং টিকিটের স্ট্যাটাস আপডেট করা হয়েছে।'
        ]);
    }

    // ── ৪. অপ্রয়োজনীয় বা স্প্যাম টিকিট ডিলিট করা ──
  // ── ۴. অপ্রয়োজনীয় বা স্প্যাম টিকিট ডিলিট করা ──
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
