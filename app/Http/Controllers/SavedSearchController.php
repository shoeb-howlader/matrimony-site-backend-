<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use Illuminate\Http\Request;

class SavedSearchController extends Controller
{
    // ইউজারের সব সেভ করা সার্চ লিস্ট পাঠানো
    public function index(Request $request)
    {
        $searches = SavedSearch::where('user_id', $request->user()->id)
                               ->latest()
                               ->get();

        return response()->json([
            'success' => true,
            'data' => $searches
        ]);
    }

    // নতুন সার্চ ফিল্টার সেভ করা
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'filter_data' => 'required|array', // ফ্রন্টএন্ড থেকে আসা অবজেক্ট
        ]);

        $search = SavedSearch::create([
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'filter_data' => $request->filter_data,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Search saved successfully',
            'data' => $search
        ]);
    }

    // সেভ করা সার্চ ডিলিট করা
    public function destroy(Request $request, $id)
    {
        $search = SavedSearch::where('user_id', $request->user()->id)
                             ->where('id', $id)
                             ->firstOrFail();

        $search->delete();

        return response()->json([
            'success' => true,
            'message' => 'Saved search deleted successfully'
        ]);
    }
}
