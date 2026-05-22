<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtpMail;
use App\Notifications\WelcomeUserNotification;

class AuthController extends Controller
{
    public function register(Request $request)
    {
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
            'status' => 'active', // বাই ডিফল্ট অ্যাকটিভ থাকবে
        ]);

        // 🔴 স্বাগতম নোটিফিকেশন পাঠানো হচ্ছে
        $user->notify(new WelcomeUserNotification());

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'রেজিস্ট্রেশন সফল হয়েছে',
            'user' => $user,
            'token' => $token,
            'total_connections' => $user->total_connections ?? 0,
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

        // 🔴 ৪. ব্যান চেক (Banned Check) 🔴
        if ($user->status === 'banned') {
            return response()->json([
                'success' => false,
                'is_banned' => true,
                'message' => 'আপনার অ্যাকাউন্টটি স্থায়ীভাবে ব্যান করা হয়েছে। বিস্তারিত জানতে সাপোর্টে যোগাযোগ করুন।'
            ], 403);
        }

        // ৫. Sanctum টোকেন জেনারেট
        $token = $user->createToken('auth_token')->plainTextToken;

        // ৬. Login History ফিচার
        \App\Models\LoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'লগইন সফল',
            'user' => $user,
            'token' => $token
        ], 200);
    }

    // ── 🔴 Google Login / Socialite Handling (নতুন যুক্ত করা হলো) ──
    public function googleLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'name' => 'required|string',
            // Google থেকে আসা id বা টোকেন ভ্যালিডেশন এখানে থাকতে পারে
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            // 🔴 ব্যান চেক: যদি ইউজার আগে থেকেই থাকে এবং ব্যান হয় 🔴
            if ($user->status === 'banned') {
                return response()->json([
                    'success' => false,
                    'is_banned' => true,
                    'message' => 'আপনার অ্যাকাউন্টটি স্থায়ীভাবে ব্যান করা হয়েছে।'
                ], 403);
            }
        } else {
            // যদি ইউজার নতুন হয়, তবে ক্রিয়েট করা হবে
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                // গুগল লগইনে পাসওয়ার্ড থাকে না, তাই রেন্ডম দেওয়া হলো
                'password' => Hash::make(Str::random(16)),
                'status' => 'active',
                'email_verified_at' => now(), // গুগল ইমেইল ভেরিফাইড থাকে
            ]);

            // 🔴 গুগল দিয়ে প্রথমবার একাউন্ট খুললেও স্বাগতম নোটিফিকেশন পাঠানো হবে
            $user->notify(new WelcomeUserNotification());
        }

        // টোকেন জেনারেট
        $token = $user->createToken('auth_token')->plainTextToken;

        // লগইন হিস্ট্রি
        \App\Models\LoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'গুগল লগইন সফল',
            'user' => $user,
            'token' => $token
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'সফলভাবে লগআউট হয়েছেন']);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8',
            'confirm_password' => 'required|same:new_password',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'আপনার বর্তমান পাসওয়ার্ডটি সঠিক নয়।'
            ], 400);
        }

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
            'mobile' => 'required|string|max:15|unique:users,mobile,' . $request->user()->id,
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

    public function deleteAccount(Request $request)
    {
        $user = auth()->user();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'আপনার একাউন্ট স্থায়ীভাবে মুছে ফেলা হয়েছে।'
        ]);
    }


    // ১. OTP পাঠানোর মেথড (ইমেইল বা ফোন)
public function sendOtp(Request $request)
{
    $request->validate([
        'login_id' => 'required'
    ]);

    // ইউজারকে ইমেইল অথবা মোবাইল নাম্বার দিয়ে খুঁজে বের করা
    $user = User::where('email', $request->login_id)
                ->orWhere('mobile', $request->login_id)
                ->first();

    if (!$user) {
        return response()->json(['success' => false, 'message' => 'এই ইমেইল বা ফোন নাম্বারের কোনো ইউজার পাওয়া যায়নি।'], 404);
    }

    $otp = rand(100000, 999999);
    $identifier = $user->email; // টেবিল আইডেন্টিফায়ার হিসেবে ইমেইল ব্যবহার করছি

    // পুরনো টোকেন ডিলিট করে নতুন OTP সেভ করা
    DB::table('password_reset_tokens')->where('email', $identifier)->delete();
    DB::table('password_reset_tokens')->insert([
        'email' => $identifier,
        'token' => Hash::make($otp),
        'created_at' => now()
    ]);

   // 📩 ইমেইল অথবা SMS পাঠানোর লজিক
    if (filter_var($request->login_id, FILTER_VALIDATE_EMAIL)) {

        // 🔴 এখানে ইমেইল পাঠানো হচ্ছে
        Mail::to($user->email)->send(new SendOtpMail($otp));

    } else {
        // SMS পাঠানোর কোড (পরবর্তীতে SMS Gateway বসালে এখানে লজিক দেবেন)
    }

    return response()->json([
        'success' => true,
        'message' => 'আপনার ঠিকানায় একটি ভেরিফিকেশন কোড পাঠানো হয়েছে।',
        'debug_otp' => $otp // শুধু টেস্টিংয়ের জন্য
    ]);
}

// ২. পাসওয়ার্ড রিসেট করার মেথড
public function resetPassword(Request $request)
{
    $request->validate([
        'login_id' => 'required',
        'otp' => 'required|numeric',
        'password' => 'required|min:8|confirmed'
    ]);

    $user = User::where('email', $request->login_id)
                ->orWhere('mobile', $request->login_id)
                ->first();

    if (!$user) return response()->json(['success' => false, 'message' => 'ইউজার পাওয়া যায়নি।'], 404);

    $resetRecord = DB::table('password_reset_tokens')->where('email', $user->email)->first();

    // OTP ভেরিফিকেশন এবং মেয়াদ (১৫ মিনিট) চেক
    if (!$resetRecord || !Hash::check($request->otp, $resetRecord->token)) {
        return response()->json(['success' => false, 'message' => 'ভুল কোড দেওয়া হয়েছে।'], 400);
    }

    if (now()->diffInMinutes($resetRecord->created_at) > 15) {
        return response()->json(['success' => false, 'message' => 'কোডটির মেয়াদ শেষ হয়ে গেছে।'], 400);
    }

    // পাসওয়ার্ড আপডেট
    $user->password = Hash::make($request->password);
    $user->save();

    DB::table('password_reset_tokens')->where('email', $user->email)->delete();

    return response()->json(['success' => true, 'message' => 'পাসওয়ার্ড সফলভাবে পরিবর্তন করা হয়েছে।']);
}
}
