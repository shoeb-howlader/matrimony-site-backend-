<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware // (আপনার ফাইলের নাম অনুযায়ী হবে)
{
    public function handle(Request $request, Closure $next)
    {
        // ১. যদি ইউজার লগইন করা না থাকে
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // 🔴 ২. নতুন আপডেট: যাদের অ্যাডমিন প্যানেলের এপিআই কল করার অনুমতি আছে
        $adminRoles = ['super_admin', 'admin', 'moderator'];

        if (!in_array(auth()->user()->role, $adminRoles)) {
            return response()->json(['success' => false, 'message' => 'আপনার এই এপিআই অ্যাক্সেস করার অনুমতি নেই!'], 403);
        }

        return $next($request);
    }
}
