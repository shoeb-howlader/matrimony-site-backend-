<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    // 🔴 আপডেট: আনরিড এর বদলে সব নোটিফিকেশন পাঠানো (সর্বশেষ ৫০টি)
    public function getAllNotifications()
    {
        $user = Auth::user();
        // latest() দিয়ে নতুনগুলো আগে আসবে, limit(50) দিয়ে সার্ভারের উপর চাপ কমানো হলো
        $notifications = $user->notifications()->latest()->limit(50)->get();

        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    // একটি নির্দিষ্ট নোটিফিকেশন রিড মার্ক করা
    public function markAsRead($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->markAsRead();
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Notification not found'], 404);
    }

    // সব নোটিফিকেশন একসাথে রিড মার্ক করা
    public function markAllAsRead()
    {
        Auth::user()->unreadNotifications->markAsRead();
        return response()->json(['success' => true]);
    }
}
