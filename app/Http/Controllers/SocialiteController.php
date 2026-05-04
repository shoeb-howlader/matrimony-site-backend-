<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SocialiteController extends Controller
{
    // এই একটি ফাংশন দিয়েই গুগল, ফেসবুক সব লগিন হ্যান্ডেল করা যাবে
    public function handleProviderCallback(Request $request, $provider)
    {
        $request->validate([
            'access_token' => 'required|string',
        ]);

        try {
            // প্রোভাইডার (google/facebook) থেকে ইউজারের তথ্য আনা (Stateless ভাবে)
            $socialUser = Socialite::driver($provider)->stateless()->userFromToken($request->access_token);

            // ডাটাবেসে ইউজার খুঁজুন অথবা নতুন তৈরি করুন
            $user = User::firstOrCreate(
                ['email' => $socialUser->getEmail()],
                [
                    'name' => $socialUser->getName(),
                    'google_id' => $provider === 'google' ? $socialUser->getId() : null,
                    // পরে ফেসবুক আসলে: 'facebook_id' => $provider === 'facebook' ? $socialUser->getId() : null,
                    'avatar' => $socialUser->getAvatar(),
                    'password' => Hash::make(Str::random(24)) // র‍্যান্ডম পাসওয়ার্ড
                ]
            );

            // যদি ইউজারের আগে থেকেই অ্যাকাউন্ট থাকে (নরমাল ইমেইল দিয়ে করা), কিন্তু গুগল আইডি নেই, তাহলে আপডেট করুন
            if ($provider === 'google' && !$user->google_id) {
                $user->update([
                    'google_id' => $socialUser->getId(),
                    'avatar' => $user->avatar ?? $socialUser->getAvatar() // যদি আগের ছবি না থাকে
                ]);
            }

            // Sanctum টোকেন জেনারেট করুন
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'লগিন ব্যর্থ হয়েছে। ' . $e->getMessage()
            ], 401);
        }
    }
}
