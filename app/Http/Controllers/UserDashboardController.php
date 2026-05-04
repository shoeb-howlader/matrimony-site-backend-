<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Biodata;
use App\Models\BiodataView;
use App\Models\BiodataPreference;

class UserDashboardController extends Controller
{
    /**
     * ড্যাশবোর্ডের সব স্ট্যাটিস্টিকস একসাথে পাঠানো
     */
    public function getDashboardStats(Request $request)
    {
        $user = Auth::user();
        $biodata = Biodata::where('user_id', $user->id)->first();

        // ── ১. প্রোফাইল কমপ্লিশন স্ট্যাটাস ──
        $percentage = 0;
        $stepsStatus = [];
        $stepLabels = [
            1 => 'সাধারণ তথ্য', 2 => 'ঠিকানা', 3 => 'শিক্ষাগত যোগ্যতা', 4 => 'পারিবারিক তথ্য',
            5 => 'ব্যক্তিগত তথ্য', 6 => 'পেশাগত তথ্য', 7 => 'বিয়ে সংক্রান্ত তথ্য',
            8 => 'প্রত্যাশিত জীবনসঙ্গী', 9 => 'অঙ্গীকারনামা', 10 => 'যোগাযোগের তথ্য'
        ];

        if ($biodata) {
            $currentStep = (int) $biodata->current_step;
            $percentage = $currentStep * 10;
            $percentage = $percentage > 100 ? 100 : $percentage;

            foreach ($stepLabels as $stepNum => $label) {
                $stepsStatus[] = [
                    'id' => $stepNum,
                    'label' => $label,
                    'completed' => $currentStep >= $stepNum
                ];
            }
        } else {
            foreach ($stepLabels as $stepNum => $label) {
                $stepsStatus[] = [
                    'id' => $stepNum,
                    'label' => $label,
                    'completed' => false
                ];
            }
        }

        // ── ২. ইউজারের নিজস্ব প্রোফাইল ভিউ কাউন্ট ──
        $profileViewCount = $biodata ? BiodataView::where('biodata_id', $biodata->id)->count() : 0;

        // ── ৩. কতজন ইউজারের প্রোফাইল পছন্দ (Favorite) করেছে ──
        $whoLikedMeCount = $biodata ? BiodataPreference::where('biodata_id', $biodata->id)->where('type', 'favorite')->count() : 0;

        // ── ৪. কতগুলো প্রোফাইলের কন্ট্যাক্ট আনলক করেছে ──
        $unlockedCount = $user->purchasedBiodatas()->count();

        // সব ডাটা একসাথে রিটার্ন করা
        return response()->json([
            'success' => true,
            'completion_stats' => [
                'percentage' => $percentage,
                'steps' => $stepsStatus
            ],
            'view_count' => $profileViewCount,
            'who_liked_me_count' => $whoLikedMeCount,
            'unlocked_count' => $unlockedCount
        ]);
    }
}
