<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBannedUser
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // যদি ইউজার লগইন করা থাকে এবং তার স্ট্যাটাস 'banned' হয়
        if (auth()->check() && auth()->user()->status === 'banned') {

            // ইউজারের বর্তমান সব অ্যাক্সেস টোকেন (Sanctum) ডিলিট করে দেওয়া
            $request->user()->tokens()->delete();

            return response()->json([
                'success' => false,
                'is_banned' => true,
                'message' => 'আপনার অ্যাকাউন্টটি স্থায়ীভাবে ব্যান করা হয়েছে। নিরাপত্তার স্বার্থে আপনাকে লগআউট করা হলো।'
            ], 403);
        }

        return $next($request);
    }
}
