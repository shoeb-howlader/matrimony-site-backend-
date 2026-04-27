<?php

namespace App\Http\Controllers;

use App\Models\BiodataView;
use App\Models\Biodata;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BiodataViewController extends Controller
{
    // ১. ভিউ রেকর্ড করার ফাংশন
    public function recordView(Request $request)
    {
        $request->validate(['biodata_id' => 'required|exists:biodatas,id']);

        $biodataId = $request->biodata_id;
        $ip = $request->ip();
        $userAgent = $request->header('User-Agent');

        // যেহেতু রাউটটি পাবলিক, তাই ম্যানুয়ালি টোকেন চেক করতে হবে
        $user = Auth::guard('sanctum')->user();

        // 🔴 নিজের বায়োডাটা নিজে দেখলে কাউন্ট হবে না
        if ($user) {
            $userBiodata = Biodata::where('user_id', $user->id)->first();
            if ($userBiodata && $userBiodata->id == $biodataId) {
                return response()->json(['success' => true, 'message' => 'Self view ignored.']);
            }
        }

        // 🔴 ২৪ ঘণ্টার মধ্যে একই ইউজার বা আইপি থেকে ডুপ্লিকেট ভিউ রোধ
        $alreadyViewed = BiodataView::where('biodata_id', $biodataId)
            ->where(function ($query) use ($user, $ip) {
                if ($user) {
                    $query->where('viewer_id', $user->id);
                } else {
                    $query->where('ip_address', $ip);
                }
            })
            ->where('created_at', '>', now()->subDay()) // ২৪ ঘণ্টার কন্ডিশন
            ->exists();

        // যদি আগে ভিউ না করে থাকে, তবেই সেভ হবে
        if (!$alreadyViewed) {
            BiodataView::create([
                'viewer_id' => $user ? $user->id : null,
                'biodata_id' => $biodataId,
                'ip_address' => $ip,
                'user_agent' => $userAgent
            ]);
        }

        return response()->json(['success' => true]);
    }

    // ২. ইউজারের আগে দেখা সব বায়োডাটার আইডি পাঠানোর ফাংশন
    public function getViewedIds(Request $request)
    {
        $user = Auth::guard('sanctum')->user();
        $ip = $request->ip();

        $query = BiodataView::query();

        if ($user) {
            $query->where('viewer_id', $user->id);
        } else {
            $query->where('ip_address', $ip);
        }

        // distinct() দিয়ে ডুপ্লিকেট আইডিগুলো বাদ দেওয়া হয়েছে
        $viewedIds = $query->distinct()->pluck('biodata_id');

        return response()->json([
            'success' => true,
            'viewed_ids' => $viewedIds
        ]);
    }

    /**
 * ৪. ইউজারের নিজের বায়োডাটার মোট ভিউ সংখ্যা দেখা
 */
public function getMyProfileViewCount()
{
    $user = Auth::user();

    // ইউজারের বায়োডাটা আইডি খুঁজে বের করা
    $biodataId = Biodata::where('user_id', $user->id)->value('id');

    if (!$biodataId) {
        return response()->json([
            'success' => false,
            'view_count' => 0,
            'message' => 'বায়োডাটা খুঁজে পাওয়া যায়নি।'
        ]);
    }

    // BiodataView টেবিল থেকে কাউন্ট করা
    $viewCount = BiodataView::where('biodata_id', $biodataId)->count();

    return response()->json([
        'success' => true,
        'view_count' => $viewCount
    ]);
}
}
