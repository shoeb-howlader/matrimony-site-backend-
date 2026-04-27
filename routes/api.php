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
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\BiodataViewController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Admin\SupportTicketController as AdminSupportController;
use App\Models\SupportTicket;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Admin\AdminBiodataController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminUserPurchaseController;
use App\Http\Controllers\Admin\AdminPackageController;
use App\Http\Controllers\Admin\AdminContactController;
use App\Http\Controllers\Admin\AdminBiodataViewController;
use App\Http\Controllers\Admin\AdminDashboardController;

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

Route::get('/biodatas/{biodata_no}', [BiodataController::class, 'show']);
// 🔴 ভিউ রিলেটেড পাবলিক রাউট (মিডলওয়্যারের বাইরে)
Route::post('/biodata/record-view', [BiodataViewController::class, 'recordView']);
Route::get('/biodata/viewed-ids', [BiodataViewController::class, 'getViewedIds']);



// Admin Only
Route::middleware(['auth:sanctum', 'can:admin-only'])->group(function () {
    Route::patch('/biodatas/{id}/approve', [BiodataController::class, 'approve']);
});


// 🔴 অ্যাডমিন রাউট গ্রুপ (অবশ্যই Admin Middleware থাকতে হবে)
Route::middleware(['auth:sanctum', 'isAdmin'])->prefix('admin')->group(function () {





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

    // Save Step 1
    Route::post('/user/biodata/step-1', [BiodataController::class, 'saveStep1']);
    // 🔴 Step 2 এর জন্য নতুন রাউট
    Route::post('/user/biodata/step-2', [BiodataController::class, 'saveStep2']);
    Route::post('/user/biodata/step-3', [BiodataController::class, 'saveStep3']);
    Route::post('/user/biodata/step-4', [BiodataController::class, 'saveStep4']);
    Route::post('/user/biodata/step-5', [BiodataController::class, 'saveStep5']);
    Route::post('/user/biodata/step-6', [BiodataController::class, 'saveStep6']);
    Route::post('/user/biodata/step-7', [BiodataController::class, 'saveStep7']);
    Route::post('/user/biodata/step-8', [BiodataController::class, 'saveStep8']);
    Route::post('/user/biodata/step-9', [BiodataController::class, 'saveStep9']);
    Route::post('/user/biodata/step-10', [BiodataController::class, 'saveStep10']);
    Route::post('/user/biodata/submit', [BiodataController::class, 'submitFinal']);
    Route::post('/user/biodata/toggle-visibility', [BiodataController::class, 'toggleVisibility']);
    // ইউজারের পছন্দ ও অপছন্দ করা বায়োডাটার লিস্ট পাওয়ার রাউট
    Route::get('/user/preferences', [PreferenceController::class, 'getPreferences']);
    Route::post('/user/settings/password', [AuthController::class, 'changePassword']);

    // বায়োডাটা পছন্দ বা অপছন্দ করার (Toggle) রাউট
    Route::post('/user/preferences/toggle', [PreferenceController::class, 'togglePreference']);
    Route::get('/user/favorites', [PreferenceController::class, 'getFavorites']);
    Route::get('/user/ignores', [PreferenceController::class, 'getIgnores']);
    Route::get('/user/who-liked-me-count', [PreferenceController::class, 'getWhoLikedMeCount']);
    Route::get('/user/biodata/completion-stats', [BiodataController::class, 'getCompletionStats']);
    // Saved Searches Routes
    Route::get('/user/saved-searches', [SavedSearchController::class, 'index']);
    Route::post('/user/saved-searches', [SavedSearchController::class, 'store']);
    Route::delete('/user/saved-searches/{id}', [SavedSearchController::class, 'destroy']);
    Route::delete('/user/biodata', [BiodataController::class, 'deleteBiodata']);




    // Get Current Draft (পেজ রিলোড দিলে যেন ইউজারের আগের ডেটা ফেরত আসে)
    Route::get('/user/biodata/draft', [BiodataController::class, 'getDraft']);

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
    Route::post('/user/purchase-biodata', [PurchaseController::class, 'purchase']);
    Route::get('/packages', [PackageController::class, 'index']);
    Route::post('/payment/initiate', [PaymentController::class, 'initiate']);
    Route::post('/user/biodata/view-contact', [PurchaseController::class, 'viewContact']);
    Route::get('/user/purchases', [PaymentController::class, 'getPurchaseHistory']);

    //Route::post('/user/report-biodata', [ReportController::class, 'store']);

    Route::get('/user/support-tickets', [SupportTicketController::class, 'index']);
    Route::post('/user/support-ticket', [SupportTicketController::class, 'store']);
    Route::get('/user/unlocked-biodatas', [BiodataController::class, 'getUnlockedList']);
    Route::get('/user/my-view-count', [BiodataViewController::class, 'getMyProfileViewCount']);

    Route::get('/notifications', [NotificationController::class, 'getAllNotifications']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

});
// পেমেন্ট গেটওয়ে থেকে রেসপন্স রিসিভ করার জন্য (এটি পাবলিক হবে, তবে পেমেন্ট গেটওয়ের সিগনেচার ভেরিফাই করতে হবে)
Route::post('/payment/callback', [PaymentController::class, 'callback']);
Route::post('/payment/success', [PaymentController::class, 'success']); // ইউজারকে দেখানোর জন্য
Route::post('/payment/fail', [PaymentController::class, 'fail']);
Route::post('/payment/cancel', [PaymentController::class, 'cancel']);



Route::post('/contact-message', [ContactController::class, 'submitContactMessage']);

// 🔴 Admin Routes
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->group(function () {

    // টেস্ট করার জন্য একটি ডামি রাউট
    /*Route::get('/dashboard-stats', function () {
        return response()->json([
            'success' => true,
            'message' => 'Welcome to Admin Dashboard!',
            'data' => [
                'total_users' => \App\Models\User::count(),
                'total_biodatas' => \App\Models\Biodata::count(),
            ]
        ]);
    });*/
    Route::get('/dashboard-stats', [AdminDashboardController::class, 'index']);

    // ভবিষ্যতে অ্যাডমিনের সব রাউট (যেমন: Approve Biodata, Manage Users) এই গ্রুপের ভেতরেই থাকবে
    Route::get('/pending-biodatas', [AdminBiodataController::class, 'getPendingBiodatas']);
    Route::get('/biodatas', [AdminBiodataController::class, 'getAllBiodatas']);
    Route::post('/biodata/{id}/approve', [AdminBiodataController::class, 'approveBiodata']);
    Route::post('/biodata/{id}/reject', [AdminBiodataController::class, 'rejectBiodata']);
    Route::get('/biodata/{id}', [AdminBiodataController::class, 'show']);
    Route::get('/biodatas/export', [AdminBiodataController::class, 'exportBiodatas']); // 🔴 Export Route
    Route::post('/biodatas/bulk-action', [AdminBiodataController::class, 'bulkAction']); // 🔴 Bulk Action Route
    Route::post('/biodata/{id}/status', [AdminBiodataController::class, 'changeStatus']); // 🔴 Quick Status Route
    Route::delete('/biodata/{id}', [AdminBiodataController::class, 'deleteBiodata']);

    // User Management Routes
    Route::get('/users', [AdminUserController::class, 'getUsers']);
    Route::post('/users/bulk-action', [AdminUserController::class, 'bulkAction']);
    Route::post('/user/{id}/status', [AdminUserController::class, 'changeStatus']);
    Route::get('/users/export', [AdminUserController::class, 'exportUsers']);

    // User Profile Routes
    Route::get('/user/{id}', [AdminUserController::class, 'getUserDetails']);
    Route::post('/user/{id}/update', [AdminUserController::class, 'updateUser']);

    // Payment Management Routes
    Route::get('/payments', [\App\Http\Controllers\Admin\AdminPaymentController::class, 'index']);
    Route::post('/payment/{id}/status', [\App\Http\Controllers\Admin\AdminPaymentController::class, 'changeStatus']);
    Route::get('/payments/export', [\App\Http\Controllers\Admin\AdminPaymentController::class, 'export']);

    //admin user biodata purchase view
    Route::get('/user-purchases', [\App\Http\Controllers\Admin\AdminUserPurchaseController::class, 'index']);

        // সাপোর্ট ও রিপোর্ট ম্যানেজমেন্টের রাউট
    Route::get('/support-tickets', [AdminSupportController::class, 'index']); // সব টিকিট দেখা
    Route::get('/support-tickets/{id}', [AdminSupportController::class, 'show']); // নির্দিষ্ট একটি দেখা
    Route::post('/support-tickets/{id}/reply', [AdminSupportController::class, 'reply']); // উত্তর দেওয়া
    Route::delete('/support-tickets/{id}', [AdminSupportController::class, 'destroy']); // ডিলিট করা

    // 🔴 প্যাকেজ ম্যানেজমেন্ট রাউটস
    Route::get('/packages', [AdminPackageController::class, 'index']); // প্যাকেজ লিস্ট দেখার জন্য
    Route::post('/packages', [AdminPackageController::class, 'store']); // নতুন প্যাকেজ তৈরির জন্য
    Route::put('/packages/{id}', [AdminPackageController::class, 'update']); // প্যাকেজ এডিট করার জন্য
    Route::delete('/packages/{id}', [AdminPackageController::class, 'destroy']); // প্যাকেজ ডিলিট করার জন্য


    Route::get('/contacts', [AdminContactController::class, 'index']);
    Route::patch('/contacts/{id}/status', [AdminContactController::class, 'updateStatus']);
    Route::delete('/contacts/{id}', [AdminContactController::class, 'destroy']);

    Route::get('/biodata-views', [AdminBiodataViewController::class, 'index']);
Route::get('/biodata-views/{id}/viewers', [AdminBiodataViewController::class, 'viewers']);
    Route::get('/user/{id}/views', [AdminUserController::class, 'getUserViews']);
    Route::get('/user/{id}/visits', [AdminUserController::class, 'getUserVisits']);
    Route::get('/user/{id}/shortlists', [AdminUserController::class, 'getUserShortlists']);
Route::get('/user/{id}/dislikes', [AdminUserController::class, 'getUserDislikes']);
Route::get('/user/{id}/unlocked', [AdminUserController::class, 'getUserUnlocked']);
// Report & Support Ticket APIs
Route::get('/user/{id}/reports-received', [AdminUserController::class, 'getUserReportsReceived']);
Route::get('/user/{id}/reports-made', [AdminUserController::class, 'getUserReportsMade']);
Route::post('/support-ticket/{ticket_id}/reply', [AdminUserController::class, 'replySupportTicket']);
Route::get('/user/{id}/support-tickets', [AdminUserController::class, 'getUserSupportTickets']);
Route::post('/support-ticket/{ticket_id}/reply', [AdminUserController::class, 'replySupportTicket']);

// Payment / Purchases APIs
Route::get('/user/{id}/purchases', [AdminUserController::class, 'getUserPurchases']);
Route::post('/transaction/{id}/status', [AdminUserController::class, 'changeTransactionStatus']);
//liked by, disliked by
Route::get('/user/{id}/shortlisted-by', [AdminUserController::class, 'getUserBiodataShortlistedBy']);
Route::get('/user/{id}/disliked-by', [AdminUserController::class, 'getUserBiodataDislikedBy']);
//
Route::post('/user/{id}/toggle-visibility', [AdminUserController::class, 'toggleUserBiodataVisibility']);
//
Route::post('/user/{id}/restore-biodata', [AdminUserController::class, 'restoreBiodata']);

//
Route::post('/user/{id}/remove-restriction', [AdminUserController::class, 'removeRestriction']);

//
Route::get('/biodata-deletion-logs', [AdminUserController::class, 'getAllDeletionLogs']);
//
Route::get('/user/{id}/unlocked-by', [AdminUserController::class, 'getUserBiodataUnlockedBy']);

//
Route::post('/user/{id}/admin-note', [AdminUserController::class, 'updateAdminNote']);
Route::post('/user/{id}/impersonate', [AdminUserController::class, 'impersonateUser']);
Route::get('/user/{id}/login-history', [AdminUserController::class, 'getUserLoginHistory']);

// 🔴 ডিলিট লগ দেখা এবং রিস্টোর করার নতুন রাউট
Route::get('/biodata/{id}/delete-log', [App\Http\Controllers\Admin\AdminBiodataController::class, 'getDeleteLog']);
Route::post('/biodata/{id}/restore', [App\Http\Controllers\Admin\AdminBiodataController::class, 'restoreBiodata']);

});
