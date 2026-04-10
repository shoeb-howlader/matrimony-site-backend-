<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BiodataController;
use App\Models\Division;
use App\Models\District;
use App\Models\Upazila;
use App\Models\Union;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\AuthController;



Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function () {
    return response()->json(['message' => 'This is a test route.']);
});

// Public: Anyone can search
Route::get('/biodatas', [BiodataController::class, 'index', ]);

Route::get('/biodatas/{id}', [BiodataController::class, 'show']);



// Admin Only
Route::middleware(['auth:sanctum', 'can:admin-only'])->group(function () {
    Route::patch('/biodatas/{id}/approve', [BiodataController::class, 'approve']);
});

// সকল বিভাগের তালিকা
Route::get('/locations/divisions', function () {
    return Division::select('id', 'name', 'bn_name')->get();
});

// নির্দিষ্ট বিভাগের অধীনে জেলাসমূহ
Route::get('/locations/districts/{division_id}', function ($division_id) {
    return District::where('division_id', $division_id)
        ->select('id', 'division_id', 'name', 'bn_name')
        ->get();
});

// নির্দিষ্ট জেলার অধীনে উপজেলাসমূহ
Route::get('/locations/upazilas/{district_id}', function ($district_id) {
    return Upazila::where('district_id', $district_id)
        ->select('id', 'district_id', 'name', 'bn_name')
        ->get();
});
// নির্দিষ্ট উপজেলার অধীনে ইউনিয়নসমূহ
Route::get('/locations/unions/{upazila_id}', function ($upazila_id) {
    return Union::where('upazila_id', $upazila_id)
        ->select('id', 'upazila_id', 'name', 'bn_name')
        ->get();
});

Route::get('/metadata', [SettingController::class, 'getMetadata']);

/*
|--------------------------------------------------------------------------
| Protected Routes (শুধুমাত্র লগইন করা ইউজাররা অ্যাক্সেস পাবে)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // ইউজারের নিজস্ব তথ্য
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // লগআউট
    Route::post('/logout', [AuthController::class, 'logout']);

    // বায়োডাটা জমা দেওয়া বা আপডেট করা
    Route::post('/biodatas', [BiodataController::class, 'store']);
    Route::put('/biodatas/{id}', [BiodataController::class, 'update']);

    // প্রিয় বায়োডাটার তালিকা
    Route::get('/favorites', [BiodataController::class, 'favorites']);
    Route::post('/favorites/{id}', [BiodataController::class, 'toggleFavorite']);
});
