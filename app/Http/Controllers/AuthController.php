<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        //return response()->json(['message' => 'রেজিস্ট্রেশন সফল হয়েছে']);
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'mobile' => 'required|string|max:15|unique:users,mobile',
            'password' => 'required|string|min:8|confirmed',
            'gender' => 'required|in:male,female',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'mobile' => $request->mobile,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'রেজিস্ট্রেশন সফল হয়েছে',
            'user' => $user,
            'token' => $token,
            'total_connections' => $user->total_connections, // নতুন ফিল্ড রেসপন্সে যুক্ত করা হয়েছে
        ], 201);
    }

  public function login(Request $request)
    {
        // ১. email এর বদলে login_id দিয়ে ভ্যালিডেশন
        $request->validate([
            'login_id' => 'required|string',
            'password' => 'required',
        ]);

        // ২. ইমেইল অথবা মোবাইল—যেকোনো একটি দিয়ে ইউজারকে খোঁজা
        $user = User::where('email', $request->login_id)
                    ->orWhere('mobile', $request->login_id)
                    ->first();

        // ৩. ইউজার না পেলে বা পাসওয়ার্ড ভুল হলে
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'ইমেইল/মোবাইল অথবা পাসওয়ার্ড সঠিক নয়।'], 401);
        }

        // ৪. Sanctum টোকেন জেনারেট
        $token = $user->createToken('auth_token')->plainTextToken;

        // ৫. আপনার চমৎকার Login History ফিচারটি এখানে কাজ করবে
        \App\Models\LoginHistory::create([
            'user_id' => $user->id,          // লগইন করা ইউজারের আইডি
            'ip_address' => $request->ip(),  // আইপি অ্যাড্রেস
            'user_agent' => $request->userAgent(), // ডিভাইস ও ব্রাউজারের নাম
        ]);

        return response()->json([
            'message' => 'লগইন সফল',
            'user' => $user,
            'token' => $token
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'সফলভাবে লগআউট হয়েছেন']);
    }

    /**
     * 2. পাসওয়ার্ড পরিবর্তনের লজিক
     */
    public function changePassword(Request $request)
    {
        // ভ্যালিডেশন (ফ্রন্টএন্ডের ফিল্ডের নামের সাথে মিলিয়ে)
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8',
            'confirm_password' => 'required|same:new_password',
        ]);

        $user = auth()->user();

        // বর্তমান পাসওয়ার্ড সঠিক কি না তা চেক করা
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'আপনার বর্তমান পাসওয়ার্ডটি সঠিক নয়।'
            ], 400); // 400 Bad Request
        }

        // নতুন পাসওয়ার্ড সেভ করা
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'পাসওয়ার্ড সফলভাবে পরিবর্তন করা হয়েছে।'
        ]);
    }
    public function completeProfile(Request $request)
    {
        $request->validate([
            'gender' => 'required|in:male,female',
            'mobile' => 'required|string|max:15|unique:users,mobile,' . $request->user()->id, // নিজের মোবাইল নাম্বার চেক করা এড়ানোর জন্য id পাস করা হলো
        ]);

        $user = $request->user();

        $user->update([
            'gender' => $request->gender,
            'mobile' => $request->mobile,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'প্রোফাইল সফলভাবে আপডেট হয়েছে।',
            'user' => $user
        ], 200);
    }

    /**
     * একাউন্ট স্থায়ীভাবে ডিলিট করার লজিক
     */
    public function deleteAccount(Request $request)
    {
        $user = auth()->user();

        // ⚠️ সতর্কতা: যদি ডাটাবেজে Foreign Key-তে OnDelete('cascade') না থাকে,
        // তবে ইউজারের অন্যান্য ডাটা (বায়োডাটা, ফেভারিটস ইত্যাদি) ম্যানুয়ালি ডিলিট করতে হবে।
        // যেমন: $user->biodata()->delete();

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'আপনার একাউন্ট স্থায়ীভাবে মুছে ফেলা হয়েছে।'
        ]);
    }
}
