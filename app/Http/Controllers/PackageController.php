<?php

namespace App\Http\Controllers;

use App\Models\ConnectionPackage;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    /**
     * সকল সক্রিয় প্যাকেজের তালিকা
     */
   public function index()
    {
        try {
            $packages = \App\Models\ConnectionPackage::where('is_active', true)
                ->orderBy('connection_count', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $packages
            ]);
        } catch (\Exception $e) {
            // 🔴 আপডেট: আসল এরর মেসেজটি দেখার জন্য $e->getMessage() যোগ করা হলো
            return response()->json([
                'success' => false,
                'message' => 'প্যাকেজ লোড করতে সমস্যা: ' . $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}
