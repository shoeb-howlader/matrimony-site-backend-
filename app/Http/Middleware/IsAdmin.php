<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // ইউজার লগইন করা আছে কিনা এবং তার রোল 'admin' কিনা চেক করা
        if (Auth::check() && Auth::user()->isAdmin()) {
            return $next($request);
        }

        // অ্যাডমিন না হলে 403 Forbidden এরর রিটার্ন করবে
        return response()->json([
            'success' => false,
            'message' => 'Access Denied. You do not have admin permissions.'
        ], 403);
    }
}
