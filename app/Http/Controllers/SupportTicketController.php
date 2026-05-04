<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SupportTicket;
use App\Models\PurchasedBiodata;
use App\Models\HasFactory;
use App\Models\Biodata;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use App\Notifications\AdminAlertNotification;
use Illuminate\Support\Facades\Storage;

class SupportTicketController extends Controller
{
    // ১. হিস্ট্রি দেখানোর জন্য (GET Request)
    public function index(Request $request)
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)
                    ->orderBy('updated_at', 'desc') // সর্বশেষ আপডেট হওয়া টিকিট সবার উপরে
                    ->get()
                    ->map(function ($ticket) {
                        // ফ্রন্টএন্ডের জন্য কিছু ডাটা ফরম্যাট করা
                        return [
                            'id' => $ticket->id,
                            'category' => $ticket->category,
                            'category_label' => $this->getCategoryLabel($ticket->category),
                            'biodata_no' => $ticket->biodata_no,
                            'subject' => $ticket->subject,
                            'message' => $ticket->message,
                            'attachment_url' => $ticket->attachment ? asset('storage/' . $ticket->attachment) : null,
                            'status' => $ticket->status,
                            'reply' => $ticket->admin_reply,
                            'created_at' => $ticket->created_at,
                        ];
                    });

        return response()->json(['success' => true, 'data' => $tickets]);
    }

    // ২. নতুন টিকিট তৈরি করার জন্য (POST Request)
public function store(Request $request)
    {
        // ১. ডাটা ভ্যালিডেশন
        $request->validate([
            'category' => 'required|string',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:10',
            'attachment' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // সর্বোচ্চ ৫ এমবি
            'biodata_no' => 'nullable|string', // যদি বায়োডাটা রিপোর্ট হয়
        ]);

        $user = $request->user();

        // 🔴 ২. যদি এটি "বায়োডাটা রিপোর্ট" হয়, তবে আপনার আগের সিকিউরিটি চেকগুলো রান করবে 🔴
        if ($request->category === 'biodata_report' && $request->biodata_no) {

            $biodata = \App\Models\Biodata::where('biodata_no', $request->biodata_no)->first();

            if (!$biodata) {
                return response()->json(['success' => false, 'message' => 'বায়োডাটা খুঁজে পাওয়া যায়নি।'], 404);
            }

            // ক. নিজের বায়োডাটা চেক
            if ($biodata->user_id == $user->id) {
                return response()->json(['success' => false, 'message' => 'আপনি নিজের বায়োডাটার বিরুদ্ধে অভিযোগ করতে পারবেন না।'], 403);
            }

            // খ. পারচেজ চেক (কিনেছে কিনা)
            $hasPurchased = \App\Models\PurchasedBiodata::where('user_id', $user->id)
                                            ->where('biodata_id', $biodata->id)
                                            ->exists();

            if (!$hasPurchased) {
                return response()->json(['success' => false, 'message' => 'আপনি এই বায়োডাটার যোগাযোগের তথ্য আনলক করেননি, তাই অভিযোগ করতে পারবেন না।'], 403);
            }

            // গ. স্প্যামিং চেক (আগে পেন্ডিং আছে কিনা)
            $alreadyReported = \App\Models\SupportTicket::where('user_id', $user->id)
                                            ->where('biodata_no', $request->biodata_no)
                                            ->where('category', 'biodata_report')
                                            ->where('status', 'pending')
                                            ->exists();

            if ($alreadyReported) {
                return response()->json(['success' => false, 'message' => 'আপনি ইতিমধ্যে এই বায়োডাটার বিরুদ্ধে একটি অভিযোগ করেছেন, যা এখনো রিভিউয়ের অপেক্ষায় আছে।'], 422);
            }
        }

        // ৩. ফাইল আপলোড (যদি থাকে)
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('support_tickets', 'public');
        }

        // ৪. ডাটাবেজে সেভ করা (Unified Table)
        $ticket = \App\Models\SupportTicket::create([
            'user_id' => $user->id,
            'category' => $request->category,
            'biodata_no' => $request->biodata_no,
            'subject' => $request->subject,
            'message' => $request->message,
            'attachment' => $attachmentPath,
            'status' => 'pending',
        ]);

        // 🔴 ৫. অ্যাডমিনকে নোটিফিকেশন পাঠানো 🔴
        try {
            $admins = \App\Models\User::where('role', 'admin')->get();

            if ($request->category === 'biodata_report') {
                $title = 'নতুন রিপোর্ট জমা পড়েছে!';
                $message = "বায়োডাটা নং {$request->biodata_no} এর বিরুদ্ধে একটি রিপোর্ট করা হয়েছে।";
            } else {
                $title = 'নতুন সাপোর্ট টিকিট!';
                $message = "নতুন একটি সাপোর্ট টিকিট ওপেন হয়েছে। বিষয়: {$request->subject}";
            }

            \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\AdminAlertNotification($title, $message, '/admin/support-tickets'));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Admin Notification Error (Support Ticket): ' . $e->getMessage());
        }

        // 🔴 ৬. ইউজারকে নোটিফিকেশন পাঠানো 🔴
        try {
            if ($request->category === 'biodata_report') {
                $userTitle = 'রিপোর্ট সফলভাবে জমা হয়েছে!';
                $userMessage = "বায়োডাটা নং {$request->biodata_no} এর বিরুদ্ধে আপনার অভিযোগটি আমাদের কাছে পৌঁছেছে। আমরা দ্রুত এটি রিভিউ করবো।";
            } else {
                $userTitle = 'সাপোর্ট টিকিট তৈরি হয়েছে!';
                $userMessage = "আপনার সাপোর্ট টিকিটটি (বিষয়: {$request->subject}) সফলভাবে তৈরি হয়েছে। অ্যাডমিন শীঘ্রই আপনার সাথে যোগাযোগ করবেন।";
            }

            // ইউজারের সাপোর্ট টিকিট পেজের লিংক দিতে পারেন (আপনার ফ্রন্টএন্ড অনুযায়ী URL পরিবর্তন করে নেবেন)
            $user->notify(new \App\Notifications\UserAlertNotification($userTitle, $userMessage, '/user/support-tickets'));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('User Notification Error (Support Ticket): ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => $request->category === 'biodata_report'
                         ? 'আপনার অভিযোগটি সফলভাবে কর্তৃপক্ষের কাছে পাঠানো হয়েছে।'
                         : 'আপনার সাপোর্ট টিকিটটি সফলভাবে তৈরি হয়েছে।'
        ], 201);
    }

    // হেল্পার ফাংশন
    private function getCategoryLabel($category) {
        $labels = [
            'payment_issue' => 'পেমেন্ট সমস্যা',
            'account_issue' => 'অ্যাকাউন্ট সমস্যা',
            'biodata_report' => 'বায়োডাটা রিপোর্ট',
            'technical_bug' => 'টেকনিক্যাল সমস্যা',
            'general_query' => 'সাধারণ জিজ্ঞাসা'
        ];
        return $labels[$category] ?? 'অন্যান্য';
    }





}
