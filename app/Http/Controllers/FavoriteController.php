<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Biodata;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    /**
     * ইউজারের সকল ফেভারিট বায়োডাটার আইডি রিটার্ন করবে
     */
    public function getFavoriteIds()
    {
        $userId = Auth::id();
        $favoriteIds = Favorite::where('user_id', $userId)->pluck('biodata_id');

        return response()->json([
            'success' => true,
            'favorite_ids' => $favoriteIds
        ]);
    }

    /**
     * ফেভারিট অ্যাড বা রিমুভ (Toggle) করবে
     */
    public function toggleFavorite(Request $request)
    {
        $request->validate([
            'biodata_id' => 'required|exists:biodatas,id'
        ]);

        $userId = Auth::id();
        $biodataId = $request->biodata_id;

        // চেক করুন ফেভারিট করা আছে কি না
        $existingFavorite = Favorite::where('user_id', $userId)
                                    ->where('biodata_id', $biodataId)
                                    ->first();

        if ($existingFavorite) {
            // যদি থাকে, তবে রিমুভ করে দিন
            $existingFavorite->delete();
            return response()->json([
                'success' => true,
                'message' => 'পছন্দের তালিকা থেকে সরানো হয়েছে।',
                'action' => 'removed'
            ]);
        } else {
            // যদি না থাকে, তবে অ্যাড করুন
            Favorite::create([
                'user_id' => $userId,
                'biodata_id' => $biodataId
            ]);
            return response()->json([
                'success' => true,
                'message' => 'পছন্দের তালিকায় যুক্ত করা হয়েছে।',
                'action' => 'added'
            ]);
        }
    }
}
