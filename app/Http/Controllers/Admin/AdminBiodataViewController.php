<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Biodata;
use App\Models\BiodataView;
use Illuminate\Http\Request;
use Carbon\Carbon; // 🔴 এটি যুক্ত করতে ভুলবেন না

class AdminBiodataViewController extends Controller
{
    public function index(Request $request)
    {
        $query = Biodata::with(['user:id,name,email,mobile']);

        // 🔴 ডেট রেঞ্জ ফিল্টার (ভিউ কাউন্টের জন্য)
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $query->withCount(['views' => function ($q) use ($startDate, $endDate) {
            if ($startDate) {
                $start = Carbon::parse($startDate)->startOfDay();
                // এন্ড ডেট না থাকলে আজকের দিন পর্যন্ত কাউন্ট করবে
                $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();
                $q->whereBetween('created_at', [$start, $end]);
            }
        }]);

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->visibility && $request->visibility !== 'all') {
            $isHidden = $request->visibility === 'hidden' ? 1 : 0;
            $query->where('is_hidden', $isHidden);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('biodata_no', 'like', "%{$search}%")
                  ->orWhereHas('user', function($uq) use ($search) {
                      $uq->where('id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%");
                  });
            });
        }

        $biodatas = $query->orderBy('views_count', 'desc')
                          ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $biodatas
        ]);
    }

    public function viewers($biodata_id, Request $request)
    {
        $query = BiodataView::with('viewer:id,name,email,mobile')
            ->where('biodata_id', $biodata_id);

        // 🔴 ডেট রেঞ্জ ফিল্টার (ভিউয়ার লিস্টের জন্য)
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        if ($startDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        }

        $total_count = (clone $query)->count();

        if ($request->type === 'logged_in') {
            $query->whereNotNull('viewer_id');
        } elseif ($request->type === 'guest') {
            $query->whereNull('viewer_id');
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('viewer', function($vq) use ($search) {
                      $vq->where('id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%");
                  });
            });
        }

        $viewers = $query->orderBy('created_at', 'desc')
                         ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $viewers,
            'total_visitors' => $total_count,
            'filtered_count' => $viewers->total()
        ]);
    }
}
