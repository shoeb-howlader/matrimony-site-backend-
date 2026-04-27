<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    /**
     * কন্টাক্ট ফর্মের মেসেজ রিসিভ এবং সেভ করার ফাংশন
     */
    public function submitContactMessage(Request $request)
    {
        // ১. ইনপুট ভ্যালিডেশন (যেহেতু আমরা ইমেইল এবং মোবাইল দুটিই বাধ্যতামূলক করেছি)
        $request->validate([
            'name'    => 'required|string|max:255',
            'mobile'  => 'required|string|max:20',
            'email'   => 'required|email|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string'
        ]);

        try {
            // ২. ডাটাবেজে সেভ করা
            ContactMessage::create([
                'name'    => $request->name,
                'mobile'  => $request->mobile,
                'email'   => $request->email,
                'subject' => $request->subject,
                'message' => $request->message,
            ]);

            // ৩. সফল রেসপন্স পাঠানো (ফ্রন্টএন্ড এই রেসপন্স ধরে টোস্ট দেখাবে)
            return response()->json([
                'success' => true,
                'message' => 'আপনার মেসেজটি সফলভাবে পাঠানো হয়েছে।'
            ], 200);

        } catch (\Exception $e) {
            // ৪. এরর হলে রেসপন্স
            return response()->json([
                'success' => false,
                'message' => 'মেসেজ সেভ করতে সমস্যা হয়েছে। অনুগ্রহ করে আবার চেষ্টা করুন।',
                'error'   => $e->getMessage() // ডেভেলপমেন্টের সময় ডিবাগ করার জন্য
            ], 500);
        }
    }
}
