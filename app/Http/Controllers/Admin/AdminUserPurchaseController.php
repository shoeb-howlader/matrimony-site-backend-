<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminUserPurchaseController extends Controller
{
public function index(Request $request)
    {
        try {
            $query = \Illuminate\Support\Facades\DB::table('purchased_biodatas')
                // ১. ক্রেতার তথ্য
                ->join('users as purchaser', 'purchased_biodatas.user_id', '=', 'purchaser.id')
                // ২. ক্রেতার নিজের বায়োডাটা (যদি থাকে)
                ->leftJoin('biodatas as purchaser_biodata', 'purchaser.id', '=', 'purchaser_biodata.user_id')
                // ৩. যে বায়োডাটাটি কেনা হয়েছে তার তথ্য
                ->join('biodatas as purchased_biodata', 'purchased_biodatas.biodata_id', '=', 'purchased_biodata.id')
                // ৪. ক্রয়কৃত বায়োডাটার মালিকের তথ্য
                ->join('users as owner', 'purchased_biodata.user_id', '=', 'owner.id')
                ->select(
                    'purchased_biodatas.id',
                    'purchased_biodatas.user_id as purchaser_id',
                    'purchased_biodatas.created_at',

                    // ক্রেতার ফিল্ডসমূহ
                    'purchaser.name as purchaser_name',
                    'purchaser.email as purchaser_email',
                    'purchaser.mobile as purchaser_mobile',
                    'purchaser_biodata.biodata_no as purchaser_biodata_no',
                    'purchaser_biodata.status as purchaser_biodata_status',

                    // ক্রয়কৃত বায়োডাটার ফিল্ডসমূহ
                    'purchased_biodata.biodata_no',
                    'purchased_biodata.id as biodata_internal_id',

                    // মালিকের ফিল্ডসমূহ
                    'owner.id as owner_id',
                    'owner.name as owner_name',
                    'owner.email as owner_email', // সার্চের জন্য মালিকের ইমেইলও আনা হলো
                    'owner.mobile as owner_mobile'
                );

            // 🔴 অ্যাডভান্সড সার্চ ফিল্টার
            if ($request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('purchaser.name', 'like', "%{$search}%")           // ক্রেতার নাম
                      ->orWhere('purchaser.id', 'like', "%{$search}%")           // ক্রেতার আইডি
                      ->orWhere('purchaser.email', 'like', "%{$search}%")        // ক্রেতার ইমেইল
                      ->orWhere('purchaser.mobile', 'like', "%{$search}%")       // ক্রেতার মোবাইল
                      ->orWhere('purchaser_biodata.biodata_no', 'like', "%{$search}%") // ক্রেতার বায়োডাটা নাম্বার

                      ->orWhere('owner.name', 'like', "%{$search}%")             // মালিকের নাম
                      ->orWhere('owner.id', 'like', "%{$search}%")               // মালিকের আইডি
                      ->orWhere('owner.email', 'like', "%{$search}%")            // মালিকের ইমেইল
                      ->orWhere('owner.mobile', 'like', "%{$search}%")           // মালিকের মোবাইল

                      ->orWhere('purchased_biodata.biodata_no', 'like', "%{$search}%"); // ক্রয়কৃত বায়োডাটা নাম্বার
                });
            }

            // 🔴 ফিক্সড ডেট রেঞ্জ ফিল্টার (যেকোনো একটি দিলেও কাজ করবে)
            if (!empty($request->start_date)) {
                $query->where('purchased_biodatas.created_at', '>=', \Carbon\Carbon::parse($request->start_date)->startOfDay());
            }
            if (!empty($request->end_date)) {
                $query->where('purchased_biodatas.created_at', '<=', \Carbon\Carbon::parse($request->end_date)->endOfDay());
            }

            // 🔴 নিরাপদ সর্টিং
            $sortBy = $request->sort_by ?? 'created_at';
            $sortDir = $request->sort_dir ?? 'desc';

            if ($sortBy === 'purchaser_id') {
                $query->orderBy('purchased_biodatas.user_id', $sortDir);
            } elseif ($sortBy === 'created_at') {
                $query->orderBy('purchased_biodatas.created_at', $sortDir);
            } else {
                $query->orderBy('purchased_biodatas.created_at', 'desc');
            }

            // পেজিনেশন
            $data = $query->paginate($request->per_page ?? 10);

            // ফ্রন্টএন্ডের জন্য ডাটা ম্যাপিং
            $data->getCollection()->transform(function ($item) {
                return [
                    'id' => $item->id,
                    'purchaser_id' => $item->purchaser_id,
                    'purchaser_name' => $item->purchaser_name,
                    'purchaser_email' => $item->purchaser_email,
                    'purchaser_mobile' => $item->purchaser_mobile,
                    'purchaser_biodata_no' => $item->purchaser_biodata_no,
                    'purchaser_biodata_status' => $item->purchaser_biodata_status,

                    'biodata_no' => $item->biodata_no,
                    'biodata_internal_id' => $item->biodata_internal_id,

                    'owner_id' => $item->owner_id,
                    'owner_name' => $item->owner_name,
                    'owner_email' => $item->owner_email,
                    'owner_mobile' => $item->owner_mobile,

                    'created_at' => $item->created_at,
                ];
            });

            // টপ কার্ডের জন্য স্ট্যাটাস
            $stats = [
                'total_purchases' => \Illuminate\Support\Facades\DB::table('purchased_biodatas')->count(),
                'today_purchases' => \Illuminate\Support\Facades\DB::table('purchased_biodatas')->whereDate('created_at', \Carbon\Carbon::today())->count(),
                'this_month_purchases' => \Illuminate\Support\Facades\DB::table('purchased_biodatas')->whereMonth('created_at', \Carbon\Carbon::now()->month)->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'purchases' => $data,
                    'stats' => $stats
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ডাটা লোড করতে সমস্যা হয়েছে: ' . $e->getMessage()
            ], 500);
        }
    }
}
