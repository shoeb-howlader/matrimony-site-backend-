<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Biodata;
use App\Models\Transaction;
use App\Models\SupportTicket;
use App\Models\Report;
use App\Models\LoginHistory;
use App\Models\PurchasedBiodata;
use App\Models\ContactMessage;
use App\Models\BiodataPreference;
use App\Models\BiodataDeletionLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        // ১. মূল পরিসংখ্যান
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'total_biodatas' => Biodata::count(),
            'male_biodatas' => Biodata::where('type', 'Male')->count(),
            'female_biodatas' => Biodata::where('type', 'Female')->count(),
            'successful_marriages' => class_exists(BiodataDeletionLog::class) ? BiodataDeletionLog::where('reason', 'like', '%married_here%')->count() : 0,
            'pending_biodatas' => Biodata::where('status', 'pending')->count(),
            'total_revenue' => Transaction::whereIn('status', ['success', 'completed', 'paid'])->sum('amount'),
            'today_revenue' => Transaction::whereIn('status', ['success', 'completed', 'paid'])->whereDate('created_at', $today)->sum('amount'),
            'pending_tickets' => SupportTicket::where('status', 'pending')->where('category', '!=', 'biodata_report')->count(),
            'pending_reports' => SupportTicket::where('status', 'pending')->where('category', 'biodata_report')->count(),
        ];

        // ২. আজকের অ্যাক্টিভিটি
        $todaysActivity = [
            'new_users' => User::whereDate('created_at', $today)->count(),
            'new_biodatas' => Biodata::whereDate('created_at', $today)->count(),
            'logins_today' => class_exists(LoginHistory::class) ? LoginHistory::whereDate('created_at', $today)->distinct('user_id')->count() : 0,
        ];

        // 🔴 ৩. গ্রাফের ডাটা (আয় এবং নতুন ইউজার গ্রোথ)
        $revenueChart = [];
        $userGrowthChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dayName = $date->locale('bn')->translatedFormat('D');

            $revenueChart[] = [
                'day' => $dayName,
                'amount' => (int) Transaction::whereIn('status', ['success', 'completed', 'paid'])->whereDate('created_at', $date)->sum('amount')
            ];

            $userGrowthChart[] = [
                'day' => $dayName,
                'count' => User::whereDate('created_at', $date)->count()
            ];
        }

        // ৪. ডেমোগ্রাফি এবং প্যাকেজ সেলস
        $demographicsRaw = Biodata::select('permanent_division_id', DB::raw('count(*) as total'))->whereNotNull('permanent_division_id')->groupBy('permanent_division_id')->orderByDesc('total')->take(5)->with('permanentDivision:id,name,bn_name')->get();
        $demographics = $demographicsRaw->map(fn($item) => ['division' => $item->permanentDivision->bn_name ?? $item->permanentDivision->name ?? 'অজানা', 'total' => $item->total]);

        $packageSalesRaw = Transaction::select('connection_package_id', DB::raw('count(*) as total'))->whereIn('status', ['success', 'completed', 'paid'])->whereNotNull('connection_package_id')->groupBy('connection_package_id')->with('connectionPackage:id,name')->get();
        $packageSales = $packageSalesRaw->map(fn($item) => ['package' => $item->connectionPackage->name ?? 'অন্যান্য', 'total' => $item->total]);

        // ৫. স্প্যাম, মেসেজ এবং টপ প্রোফাইল
        $topProfiles = Biodata::select('id', 'user_id', 'biodata_no', 'status')->with('user:id,name')->withCount('views')->orderByDesc('views_count')->take(5)->get();
        $recentMessages = class_exists(ContactMessage::class) ? ContactMessage::latest()->take(5)->get() : [];
        $spamAlerts = User::with('biodata:id,user_id,biodata_no')->whereHas('biodata', function($q) {
            $q->whereIn('biodata_no', function($sub) { $sub->select('biodata_no')->from('support_tickets')->where('category', 'biodata_report')->groupBy('biodata_no')->havingRaw('COUNT(*) >= 3'); });
        })->orWhereHas('deletionLogs', function($q) { $q->where('created_at', '>=', now()->subDays(30)); }, '>', 1)->take(5)->get(['id', 'name', 'mobile']);

        // 🔴 ৬. নতুন ফিচার ডাটা (ফেইলড পেমেন্ট, অসম্পূর্ণ বায়োডাটা, রিসেন্ট শর্টলিস্ট)
        $failedPayments = Transaction::with('user:id,name,mobile')->whereIn('status', ['failed', 'canceled', 'cancelled'])->latest()->take(5)->get();

        // যাদের বায়োডাটা ১০ম স্টেপের নিচে আছে তাদের অসম্পূর্ণ ধরা হলো (আপনার লজিক অনুযায়ী বদলাতে পারেন)
        $incompleteProfiles = Biodata::with('user:id,name,mobile')->where('current_step', '<', 10)->where('status', 'incomplete')->latest('updated_at')->take(5)->get();

        $recentShortlists = class_exists(BiodataPreference::class) ? BiodataPreference::with(['user:id,name', 'biodata:id,biodata_no'])->latest()->take(5)->get() : [];

        // ৭. টেবিল ডাটা
        $recentPendingBiodatas = Biodata::with(['user:id,name', 'user.loginHistories' => fn($q) => $q->latest()->take(1)])->where('status', 'pending')->latest('updated_at')->take(5)->get();
        $recentUnlocks = PurchasedBiodata::with(['user:id,name', 'biodata' => fn($q) => $q->withTrashed()->select('id', 'user_id', 'biodata_no')->with('user:id,name')])->latest()->take(5)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'todaysActivity' => $todaysActivity,
                'revenueChart' => $revenueChart,
                'userGrowthChart' => $userGrowthChart,
                'demographics' => $demographics,
                'packageSales' => $packageSales,
                'topProfiles' => $topProfiles,
                'recentMessages' => $recentMessages,
                'spamAlerts' => $spamAlerts,
                'failedPayments' => $failedPayments,
                'incompleteProfiles' => $incompleteProfiles,
                'recentShortlists' => $recentShortlists,
                'recentPendingBiodatas' => $recentPendingBiodatas,
                'recentUnlocks' => $recentUnlocks
            ]
        ]);
    }
}
