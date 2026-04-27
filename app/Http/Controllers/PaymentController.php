<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConnectionPackage;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Transation;


class PaymentController extends Controller
{
    /**
     * ধাপ ১: পেমেন্ট ইনিশিয়েট করা
     */
    public function initiate(Request $request)
    {
        // 🔴 ভ্যালিডেশনটি try ব্লকের বাইরে থাকবে
        $request->validate([
            'package_id' => 'required|exists:connection_packages,id'
        ]);

        try {
            $user = $request->user();
            $package = \App\Models\ConnectionPackage::find($request->package_id);

            if (!$package->is_active) {
                return response()->json(['message' => 'প্যাকেজটি বর্তমানে বন্ধ আছে।'], 400);
            }

            $amountToPay = $package->discount_price ?? $package->price;
            $transactionId = 'TXN_' . uniqid();

            // ডাটাবেজে পেন্ডিং ট্রানজেকশন সেভ করা
            $transaction = \App\Models\Transaction::create([
                'user_id' => $user->id,
                'connection_package_id' => $package->id,
                'transaction_id' => $transactionId,
                'amount' => $amountToPay,
                'connections_added' => $package->connection_count,
                'status' => 'pending',
            ]);

            // SSLCommerz এর জন্য ডাটা প্রস্তুত করা
            $post_data = [
                'store_id' => env('SSLCZ_STORE_ID', 'testbox'),
                'store_passwd' => env('SSLCZ_STORE_PASSWORD', 'testbox@ssl'),
                'total_amount' => $amountToPay,
                'currency' => "BDT",
                'tran_id' => $transactionId,
                'success_url' => url('/api/payment/success'),
                'fail_url' => url('/api/payment/fail'),
                'cancel_url' => url('/api/payment/cancel'),
                'emi_option' => 0,
                'cus_name' => $user->name ?? 'Matrimony User',
                'cus_email' => $user->email ?? 'test@matrimony.com',
                'cus_add1' => 'Dhaka',
                'cus_city' => 'Dhaka',
                'cus_country' => 'Bangladesh',
                'cus_phone' => '01700000000',
                'shipping_method' => 'NO',
                'product_name' => $package->name,
                'product_category' => 'Connection Package',
                'product_profile' => 'non-physical-goods',
            ];

            $apiUrl = env('SSLCZ_TESTMODE', true) ?
                      "https://sandbox.sslcommerz.com/gwprocess/v4/api.php" :
                      "https://securepay.sslcommerz.com/gwprocess/v4/api.php";

            // SSLCommerz এ রিকোয়েস্ট পাঠানো
            $response = \Illuminate\Support\Facades\Http::asForm()->post($apiUrl, $post_data);
            $result = $response->json();

            if (isset($result['status']) && $result['status'] == 'SUCCESS') {
                return response()->json([
                    'success' => true,
                    'payment_url' => $result['GatewayPageURL']
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'পেমেন্ট গেটওয়ে লোড হতে ব্যর্থ হয়েছে।',
                'ssl_response' => $result
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'সার্ভার এরর: ' . $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * ধাপ ২: পেমেন্ট গেটওয়ে থেকে কলব্যাক (Webhook)
     */
    public function callback(Request $request)
    {
        // পেমেন্ট গেটওয়ে থেকে পাওয়া ডাটা (যেমন SSLCommerz এর ক্ষেত্রে val_id, status ইত্যাদি)
        $transactionId = $request->input('tran_id');
        $status = $request->input('status'); // 'VALID', 'FAILED' ইত্যাদি

        $transaction = Transaction::where('transaction_id', $transactionId)->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        // যদি পেমেন্ট সফল হয় এবং ট্রানজেকশনটি আগে থেকেই সফল না হয়ে থাকে
        if ($status === 'VALID' && $transaction->status !== 'success') {

            // ডাটাবেজ ট্রানজেকশন ব্যবহার করা ভালো
            \DB::transaction(function () use ($transaction) {
                // ১. ট্রানজেকশন স্ট্যাটাস আপডেট
                $transaction->update([
                    'status' => 'success',
                    'payment_method' => request('card_type') ?? 'unknown'
                ]);

                // ২. ইউজারের কানেকশন বাড়ানো
                $transaction->user->increment('total_connections', $transaction->connections_added);
            });

            return response()->json(['message' => 'Payment successful']);
        }

        // পেমেন্ট ফেইল হলে
        if ($status === 'FAILED') {
            $transaction->update(['status' => 'failed']);
            return response()->json(['message' => 'Payment failed']);
        }

        return response()->json(['message' => 'Invalid status']);
    }

    // ইউজারকে রিডাইরেক্ট করার পর দেখানোর জন্য (Success Page)
   public function success(Request $request)
{
    // ১. SSLCommerz থেকে আসা ডাটা লগে সেভ করা
    \Illuminate\Support\Facades\Log::info('SSLCommerz Payload:', $request->all());

    $transactionId = $request->input('tran_id');
    $status = $request->input('status');

    $transaction = \App\Models\Transaction::where('transaction_id', $transactionId)->first();

    if (!$transaction) {
        \Illuminate\Support\Facades\Log::error('Transaction not found in DB: ' . $transactionId);
        return redirect(env('FRONTEND_URL', 'http://localhost:3000') . '/user/purchases?payment=failed');
    }

    // 🔴 পেমেন্ট মেথড ক্লিন করার লজিক (BKASH-bKash থেকে bKash করা)
    $cardType = $request->input('card_type', 'sslcommerz');
    if (str_contains($cardType, '-')) {
        // হাইফেন থাকলে পরের অংশটি নিবে (যেমন: bKash)
        $paymentMethod = explode('-', $cardType)[1];
    } else {
        $paymentMethod = $cardType;
    }

    try {
        \Illuminate\Support\Facades\DB::transaction(function () use ($transaction, $paymentMethod) {
            // ১. ট্রানজেকশন স্ট্যাটাস আপডেট
            $transaction->update([
                'status' => 'success',
                'payment_method' => $paymentMethod // এখন ক্লিন নাম সেভ হবে
            ]);

            // ২. ইউজারের কানেকশন বাড়ানো
            \App\Models\User::where('id', $transaction->user_id)
                ->increment('total_connections', $transaction->connections_added);
        });

        return redirect(env('FRONTEND_URL', 'http://localhost:3000') . '/user/purchases?payment=success');

    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('DB Update Error: ' . $e->getMessage());
        return redirect(env('FRONTEND_URL', 'http://localhost:3000') . '/user/purchases?payment=failed');
    }
}

/**
 * ইউজারের পেমেন্ট এবং কানেকশন খরচের ইতিহাস (ক্রয়সমূহ)
 */
public function getPurchaseHistory(Request $request)
{
    $user = $request->user();

    // ১. বায়োডাটা আনলক করার ইতিহাস (এগুলো কানেকশন দিয়ে করা হয়)
    $unlockedHistory = $user->purchasedBiodatas()
        ->with(['biodata' => function($query) {
            $query->withTrashed()->select('id', 'biodata_no');
        }])
        ->get()
        ->map(function ($item) {
            return [
                'id' => $item->transaction_id ?? 'UNL-'.str_pad($item->id, 6, '0', STR_PAD_LEFT),
                'type' => 'contact_unlock',
                'title' => 'যোগাযোগের তথ্য আনলক',
                'biodata_no' => $item->biodata ? $item->biodata->biodata_no : null,
                // বায়োডাটা আনলকে সাধারণত ১টি কানেকশন কাটে, তাই এখানে ১ বা আপনার কলাম অনুযায়ী ভ্যালু দিন
                'amount' => 1,
                'payment_method' => 'Connection Balance',
                'status' => 'completed',
                'created_at' => $item->created_at->toISOString(),
            ];
        });

    // ২. প্যাকেজ কেনার ইতিহাস (এগুলো টাকা দিয়ে কেনা হয়)
    $packageHistory = $user->transactions()
        ->latest()
        ->get()
        ->map(function ($item) {
            return [
                'id' => $item->transaction_id ?? 'TRX-'.str_pad($item->id, 6, '0', STR_PAD_LEFT),
                'type' => 'package_buy',
                'title' => $item->package_name ?? 'কানেকশন প্যাকেজ ক্রয়',
                'biodata_no' => null,
                'amount' => (int) $item->amount, // এটি টাকা (৳)
                'payment_method' => $item->payment_method ?? 'bKash',
                'status' => $item->status, // success/completed/pending
                'created_at' => $item->created_at->toISOString(),
            ];
        });

    // ৩. দুই ধরণের ডাটা একত্রিত করা এবং লেটেস্ট গুলো উপরে রাখা
    $allPurchases = $unlockedHistory->concat($packageHistory)
        ->sortByDesc('created_at')
        ->values();

    return response()->json($allPurchases);
}

/**
     * পেমেন্ট ফেইল হলে (Fail Webhook/Redirect)
     */
    public function fail(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('SSLCommerz Fail Payload:', $request->all());

        $transactionId = $request->input('tran_id');

        if ($transactionId) {
            $transaction = \App\Models\Transaction::where('transaction_id', $transactionId)->first();

            if ($transaction) {
                // ডাটাবেজে স্ট্যাটাস failed করে দেওয়া
                $transaction->update(['status' => 'failed']);
            }
        }

        // ইউজারকে ফ্রন্টএন্ডের ফেইলড পেজে রিডাইরেক্ট করা
        return redirect(env('FRONTEND_URL', 'http://localhost:3000') . '/user/purchases?payment=failed');
    }

    /**
     * ইউজার পেমেন্ট ক্যানসেল করলে (Cancel Webhook/Redirect)
     */
    public function cancel(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('SSLCommerz Cancel Payload:', $request->all());

        $transactionId = $request->input('tran_id');

        if ($transactionId) {
            $transaction = \App\Models\Transaction::where('transaction_id', $transactionId)->first();

            if ($transaction) {
                // ক্যানসেল করলে ডাটাবেজে স্ট্যাটাস failed বা cancelled সেভ করা
                $transaction->update(['status' => 'failed']);
            }
        }

        // ইউজারকে ফ্রন্টএন্ডে ক্যানসেলড মেসেজসহ রিডাইরেক্ট করা
        return redirect(env('FRONTEND_URL', 'http://localhost:3000') . '/user/purchases?payment=cancelled');
    }
}
