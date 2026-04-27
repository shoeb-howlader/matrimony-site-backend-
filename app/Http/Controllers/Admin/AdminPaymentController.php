<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AdminPaymentController extends Controller
{
    /**
     * পেমেন্ট লিস্ট এবং সামারি স্ট্যাটস (Stats)
     */

/**
     * পেমেন্ট লিস্ট এবং সামারি স্ট্যাটস (Stats)
     */
    public function index(Request $request)
    {
        try {
            // 🔴 আপডেট: 'user' এর পাশাপাশি 'connectionPackage' রিলেশনটিও লোড করা হলো
            $query = Transaction::with(['user', 'connectionPackage']);

            // ১. সার্চ ফিল্টার (TrxID, User ID, Name, Email, Mobile)
            if ($request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('transaction_id', 'like', "%{$search}%")
                      ->orWhere('user_id', 'like', "%{$search}%")
                      ->orWhereHas('user', function ($uq) use ($search) {
                          $uq->where('email', 'like', "%{$search}%")
                             ->orWhere('name', 'like', "%{$search}%")
                             ->orWhere('mobile', 'like', "%{$search}%");
                      });
                });
            }

            // ২. স্ট্যাটাস ফিল্টার
            if ($request->status && $request->status !== 'all') {
                $status = $request->status === 'completed' ? 'success' : $request->status;
                $query->where('status', $status);
            }

            // ৩. পেমেন্ট মেথড ফিল্টার
            if ($request->method && $request->method !== 'all') {
                if ($request->method === 'Card') {
                    $query->whereNotIn('payment_method', ['bKash', 'Nagad', 'Rocket']);
                } else {
                    $query->where('payment_method', 'like', "%{$request->method}%");
                }
            }

            // ৪. ডেট রেঞ্জ ফিল্টার
            if (!empty($request->start_date)) {
                $query->where('created_at', '>=', \Carbon\Carbon::parse($request->start_date)->startOfDay());
            }
            if (!empty($request->end_date)) {
                $query->where('created_at', '<=', \Carbon\Carbon::parse($request->end_date)->endOfDay());
            }
            // ৫. প্যাকেজ ফিল্টার (নতুন যুক্ত করা হলো)
if ($request->package_id && $request->package_id !== 'all') {
    $query->where('connection_package_id', $request->package_id);
}

            // ৫. ফিল্টার অনুযায়ী মোট আয় হিসাব (শুধুমাত্র সফল পেমেন্টগুলো)
            $filteredTotal = (clone $query)->where('status', 'success')->sum('amount');

            // ৬. সর্টিং (তারিখ, ইউজার আইডি, অ্যামাউন্ট)
            $sortBy = $request->sort_by ?? 'created_at';
            $sortDir = $request->sort_dir ?? 'desc';

            $allowedSorts = ['created_at', 'user_id', 'amount'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortDir);
            } else {
                $query->orderBy('created_at', 'desc');
            }

            // ৭. পেজিনেশন
            $payments = $query->paginate($request->per_page ?? 10);

            // ৮. ফ্রন্টএন্ডের জন্য ডাটা ম্যাপিং
            $payments->getCollection()->transform(function ($item) {

                // 🔴 আপডেট: ডাটাবেস রিলেশন থেকে আসল প্যাকেজের নাম বের করা হচ্ছে
                $packageName = $item->connectionPackage ? $item->connectionPackage->name : 'প্যাকেজ';

                return [
                    'id' => $item->id,
                    'trx_id' => $item->transaction_id,
                    'user_id' => $item->user_id,
                    'biodata_no' => $packageName, // 🔴 এখানে এখন আসল প্যাকেজের নাম (যেমন: Premium Package) দেখাবে
                    'amount' => $item->amount,
                    'method' => $item->payment_method ?? 'Gateway',
                    'status' => $item->status === 'success' ? 'completed' : strtolower($item->status),
                    'created_at' => $item->created_at,
                    'user' => $item->user ? [
                        'name' => $item->user->name,
                        'email' => $item->user->email,
                        'mobile' => $item->user->mobile,
                    ] : null,
                ];
            });

            // ৯. পেজের উপরের সামারি কার্ডের জন্য ডাটা (Stats)
            $stats = [
                'total_revenue' => Transaction::where('status', 'success')->sum('amount'),
                'today_revenue' => Transaction::where('status', 'success')
                                    ->whereDate('created_at', \Carbon\Carbon::today())
                                    ->sum('amount'),
                'pending_payments' => Transaction::where('status', 'pending')->count(),
                'total_transactions' => Transaction::count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'payments' => $payments,
                    'filtered_total' => $filteredTotal,
                    'stats' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'পেমেন্ট ডাটা লোড করতে সমস্যা হয়েছে: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ম্যানুয়ালি স্ট্যাটাস চেঞ্জ করা (যেমন Pending থেকে Success বা Refunded করা)
     */
    public function changeStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required']);

        try {
            $transaction = Transaction::findOrFail($id);
            $newStatus = $request->status === 'completed' ? 'success' : $request->status;

            // 🔴 যদি অ্যাডমিন ম্যানুয়ালি পেন্ডিং থেকে সাকসেস করে, তবে কানেকশন অ্যাড করতে হবে
            if ($transaction->status !== 'success' && $newStatus === 'success') {
                \Illuminate\Support\Facades\DB::transaction(function () use ($transaction, $newStatus) {
                    $transaction->update(['status' => $newStatus]);
                    $transaction->user->increment('total_connections', $transaction->connections_added);
                });
            } else {
                $transaction->update(['status' => $newStatus]);
            }

            return response()->json(['success' => true, 'message' => 'স্ট্যাটাস আপডেট করা হয়েছে।']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'আপডেট ব্যর্থ হয়েছে।'], 500);
        }
    }

    /**
     * ডাটা এক্সপোর্ট (CSV)
     */
    public function export(Request $request)
    {
        // আপনার ইউজার বা বায়োডাটা এক্সপোর্টের মতো হুবহু লজিক এখানে বসিয়ে দিন
        // Transaction::query()->get() করে CSV রেসপন্স রিটার্ন করবেন।
    }
}
