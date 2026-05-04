<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminSettingsController extends Controller
{
    // ── ১. প্রোফাইল আপডেট ──
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $request->user()->id,
        ]);

        $user = $request->user();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'প্রোফাইল সফলভাবে আপডেট হয়েছে'
        ]);
    }

    // ── ২. পাসওয়ার্ড আপডেট ──
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'বর্তমান পাসওয়ার্ড ভুল দিয়েছেন!'
            ], 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'পাসওয়ার্ড সফলভাবে পরিবর্তন হয়েছে'
        ]);
    }

    // ── ৩. নোটিফিকেশন ফেচ করা ──
    public function getNotifications(Request $request)
    {
        $user = $request->user();

        $notifications = $user->notifications()->take(20)->get();
        $unreadCount = $user->unreadNotifications()->count();

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'unread_count' => $unreadCount
        ]);
    }

    // ── ৪. নির্দিষ্ট নোটিফিকেশন Read করা ──
    public function markAsRead($id, Request $request)
    {
        $notification = $request->user()->notifications()->find($id);
        if ($notification) {
            $notification->markAsRead();
        }
        return response()->json(['success' => true]);
    }

    // ── ৫. সব নোটিফিকেশন Read করা ──
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['success' => true]);
    }
    // ── ৬. সকল নোটিফিকেশন ফেচ করা (পেজিনেশন সহ) ──
    public function getAllNotifications(Request $request)
    {
        $user = $request->user();

        // পেজিনেশন সহ নোটিফিকেশন রিটার্ন করা
        $notifications = $user->notifications()->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    // ── ৭. নোটিফিকেশন ডিলিট করা ──
    public function deleteNotification($id, Request $request)
    {
        $notification = $request->user()->notifications()->find($id);

        if ($notification) {
            $notification->delete();
            return response()->json(['success' => true, 'message' => 'নোটিফিকেশন মুছে ফেলা হয়েছে']);
        }

        return response()->json(['success' => false, 'message' => 'নোটিফিকেশন পাওয়া যায়নি'], 404);
    }

}
