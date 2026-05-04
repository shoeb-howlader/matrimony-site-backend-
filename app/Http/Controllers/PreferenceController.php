<?php

namespace App\Http\Controllers;

use App\Models\BiodataPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Biodata;

class PreferenceController extends Controller
{
    // ইউজারের পছন্দ ও অপছন্দ করা সব ডাটা একসাথে পাঠানো
    public function getPreferences()
    {
        $userId = Auth::id();

        // 🔴 ফিক্স: whereHas('biodata') ব্যবহার করা হয়েছে।
        // এর ফলে যেই বায়োডাটাগুলো ডিলিট হয়ে গেছে, সেগুলোর আইডি আর ফ্রন্টএন্ডে যাবে না।
        $favorites = BiodataPreference::where('user_id', $userId)
            ->where('type', 'favorite')
            ->whereHas('biodata') // নিশ্চিত করে যে বায়োডাটাটি এখনো বিদ্যমান
            ->pluck('biodata_id');

        $ignores = BiodataPreference::where('user_id', $userId)
            ->where('type', 'ignore')
            ->whereHas('biodata') // নিশ্চিত করে যে বায়োডাটাটি এখনো বিদ্যমান
            ->pluck('biodata_id');

        return response()->json([
            'success' => true,
            'favorites' => $favorites,
            'ignores' => $ignores
        ]);
    }

    // পছন্দ বা অপছন্দ করা (Single Method)
    public function togglePreference(Request $request)
    {
        $request->validate([
            'biodata_id' => 'required|exists:biodatas,id',
            'type' => 'required|in:favorite,ignore'
        ]);

        $userId = Auth::id();
        $biodataId = $request->biodata_id;
        $type = $request->type;

        $existing = BiodataPreference::where('user_id', $userId)->where('biodata_id', $biodataId)->first();

        // ১. যদি আগে থেকেই সেম বাটনে ক্লিক করা থাকে (Toggle Off)
        if ($existing && $existing->type === $type) {
            $existing->delete();
            return response()->json(['success' => true, 'message' => 'তালিকা থেকে সরানো হয়েছে।']);
        }

        // ২. যদি ডাটা না থাকে অথবা অন্য টাইপে ক্লিক করে (Create OR Update)
        BiodataPreference::updateOrCreate(
            ['user_id' => $userId, 'biodata_id' => $biodataId],
            ['type' => $type]
        );

        $message = $type === 'favorite' ? 'পছন্দের তালিকায় যুক্ত করা হয়েছে।' : 'অপছন্দের তালিকায় রাখা হয়েছে।';
        return response()->json(['success' => true, 'message' => $message]);
    }

// ইউজারের পছন্দের (Favorite) বায়োডাটাগুলো বের করা
    public function getFavorites()
    {
        $userId = auth()->id();

        $preferences = \App\Models\BiodataPreference::where('user_id', $userId)
            ->where('type', 'favorite')
            ->with(['biodata' => function($query) {
                $query->withTrashed()->with('presentDistrict');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedData = $preferences->map(function ($pref) {
            $biodata = $pref->biodata;
            if (!$biodata) return null;

            // ফ্রন্টএন্ডের জন্য ফিল্ডগুলো ম্যাপ করে দেওয়া হচ্ছে
            $biodata->is_deleted = $biodata->trashed();
            $biodata->added_at = $pref->created_at;
            $biodata->note = $pref->note; // 🔴 ইউজারের প্রাইভেট নোট
            $biodata->updated_at = $biodata->updated_at; // 🔴 আপডেট ব্যাজের জন্য

            return $biodata;
        })->filter()->values();

        return response()->json([
            'success' => true,
            'data' => $formattedData
        ]);
    }

// 🔴 একটিমাত্র ফাংশন যা Favorite এবং Ignore দুই জায়গার নোটই সেভ করবে
    public function saveNote(Request $request)
    {
        $request->validate([
            'biodata_id' => 'required|exists:biodatas,id',
            'note' => 'nullable|string|max:1000'
        ]);

        // এখানে type (favorite/ignore) চেক না করে শুধু ইউজার এবং বায়োডাটা আইডি চেক করা হচ্ছে
        $preference = \App\Models\BiodataPreference::where('user_id', auth()->id())
            ->where('biodata_id', $request->biodata_id)
            ->first();

        if ($preference) {
            $preference->note = $request->note;
            $preference->save();
            return response()->json(['success' => true, 'message' => 'নোট সফলভাবে সেভ হয়েছে।']);
        }

        return response()->json(['success' => false, 'message' => 'বায়োডাটাটি তালিকায় পাওয়া যায়নি।'], 404);
    }

// ইউজারের অপছন্দের (Ignored) বায়োডাটাগুলো বের করা
    public function getIgnores()
    {
        $userId = auth()->id();

        // 🔴 BiodataPreference থেকে কোয়েরি করে সাথে biodata আনা হচ্ছে (with নোট এবং deleted data)
        $preferences = \App\Models\BiodataPreference::where('user_id', $userId)
            ->where('type', 'ignore')
            ->with(['biodata' => function($query) {
                // withTrashed() এর মাধ্যমে ডিলিট হওয়া প্রোফাইলগুলোও আসবে
                $query->withTrashed()->with('presentDistrict');
            }])
            ->orderBy('created_at', 'desc') // ডিফল্টভাবে নতুন যোগ করাগুলো আগে
            ->get();

        $formattedData = $preferences->map(function ($pref) {
            $biodata = $pref->biodata;
            if (!$biodata) return null;

            // ফ্রন্টএন্ডের জন্য ফিল্ডগুলো ম্যাপ করে দেওয়া হচ্ছে
            $biodata->is_deleted = $biodata->trashed();
            $biodata->added_at = $pref->created_at;
            $biodata->note = $pref->note; // 🔴 ইউজারের প্রাইভেট নোট
            $biodata->updated_at = $biodata->updated_at;

            return $biodata;
        })->filter()->values();

        return response()->json([
            'success' => true,
            'data' => $formattedData
        ]);
    }



    /**
 * ইউজারের বায়োডাটা কতজন পছন্দের তালিকায় রেখেছে তার সংখ্যা বের করা
 */
public function getWhoLikedMeCount()
{
    $userId = Auth::id();

    // ১. লগইন করা ইউজারের নিজস্ব বায়োডাটা খুঁজে বের করা
    $userBiodata = Biodata::where('user_id', $userId)->first();

    if (!$userBiodata) {
        return response()->json([
            'success' => false,
            'count' => 0,
            'message' => 'বায়োডাটা খুঁজে পাওয়া যায়নি।'
        ]);
    }

    // ২. BiodataPreference টেবিল থেকে এই বায়োডাটা আইডিতে কতজন 'favorite' করেছে তা কাউন্ট করা
    $count = BiodataPreference::where('biodata_id', $userBiodata->id)
        ->where('type', 'favorite')
        ->count();

    return response()->json([
        'success' => true,
        'count' => $count
    ]);
}
}
