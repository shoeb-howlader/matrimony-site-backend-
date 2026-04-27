<?php

namespace App\Http\Controllers;

use App\Models\Ignore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IgnoreController extends Controller
{
    // ইউজারের ইগনোর করা সব আইডির লিস্ট পাঠানো
    public function getIgnoredIds()
    {
        $userId = Auth::id();
        $ignoredIds = Ignore::where('user_id', $userId)->pluck('biodata_id');
        return response()->json(['success' => true, 'ignored_ids' => $ignoredIds]);
    }

    // ইগনোর টগল (অ্যাড/রিমুভ) করা
    public function toggleIgnore(Request $request)
    {
        $request->validate(['biodata_id' => 'required|exists:biodatas,id']);
        $userId = Auth::id();

        $existing = Ignore::where('user_id', $userId)->where('biodata_id', $request->biodata_id)->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['success' => true, 'message' => 'অপছন্দের তালিকা থেকে সরানো হয়েছে।']);
        } else {
            Ignore::create(['user_id' => $userId, 'biodata_id' => $request->biodata_id]);
            return response()->json(['success' => true, 'message' => 'বায়োডাটাটি অপছন্দ করা হয়েছে। এটি আর আপনার লিস্টে দেখাবে না।']);
        }
    }
}
