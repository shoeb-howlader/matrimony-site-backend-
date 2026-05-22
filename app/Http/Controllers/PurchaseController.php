<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchasedBiodata;
use Illuminate\Support\Facades\DB;
use App\Models\Biodata;
use App\Events\NotificationSent;
use Illuminate\Support\Str;
use App\Notifications\BiodataUnlockedBuyerNotification;
use App\Notifications\BiodataViewedOwnerNotification;

class PurchaseController extends Controller
{
    public function purchase(Request $request)
    {
        $user = $request->user();
        $biodataId = $request->biodata_id;

        // ১. চেক করা: অলরেডি কেনা আছে কিনা
        $exists = PurchasedBiodata::where('user_id', $user->id)
                                   ->where('biodata_id', $biodataId)
                                   ->exists();

        if ($exists) {
            return response()->json(['message' => 'এই বায়োডাটাটি আপনি আগেই কিনেছেন।'], 400);
        }

        // ২. চেক করা: পর্যাপ্ত কানেকশন আছে কিনা
        if ($user->total_connections < 1) {
            return response()->json(['message' => 'আপনার পর্যাপ্ত কানেকশন নেই।'], 403);
        }

        try {
            DB::beginTransaction();

            // ৩. কানেকশন ১টি কমানো
            $user->decrement('total_connections', 1);

            // ৪. পারচেজ টেবিলে রেকর্ড সেভ করা
            PurchasedBiodata::create([
                'user_id' => $user->id,
                'biodata_id' => $biodataId,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'সফলভাবে কেনা সম্পন্ন হয়েছে!',
                'remaining_connections' => $user->total_connections
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'দুঃখিত, কোনো সমস্যা হয়েছে।'], 500);
        }
    }

    public function viewContact(Request $request)
    {
        $request->validate([
            'biodata_id' => 'required|exists:biodatas,id'
        ]);

        $user = $request->user();
        $biodataId = $request->biodata_id;

        // ১. চেক করা: ইউজার কি আগেই এই বায়োডাটা কিনেছে?
        $alreadyPurchased = PurchasedBiodata::where('user_id', $user->id)
                                            ->where('biodata_id', $biodataId)
                                            ->exists();

        if ($alreadyPurchased) {
            $biodata = Biodata::find($biodataId);
            return response()->json([
                'success' => true,
                'message' => 'আপনি আগেই এটি কিনেছেন।',
                'contact_info' => [
                    'name' => $biodata->name,
                    'guardian_relationship' => $biodata->guardian_relationship,
                    'phone' => $biodata->guardian_mobile,
                    'email' => $biodata->contact_email
                ]
            ]);
        }

        // ২. চেক করা: ইউজারের কি পর্যাপ্ত কানেকশন আছে?
        if ($user->total_connections < 1) {
            return response()->json([
                'success' => false,
                'message' => 'আপনার পর্যাপ্ত কানেকশন নেই। দয়া করে প্যাকেজ কিনুন।',
                'needs_recharge' => true
            ], 403);
        }

        try {
            DB::beginTransaction();

            // ৩. কানেকশন ১টি কমানো
            $user->decrement('total_connections', 1);

            // ৪. পারচেজ রেকর্ড সেভ করা
            PurchasedBiodata::create([
                'user_id' => $user->id,
                'biodata_id' => $biodataId,
            ]);

            DB::commit();

            // ৫. আসল যোগাযোগের তথ্য এবং বায়োডাটার মালিককে বের করা
            $biodata = Biodata::with('user')->findOrFail($biodataId);
            $biodataNo = $biodata->biodata_no ?? $biodataId;
            $owner = $biodata->user;

            // ── 🔴 নোটিফিকেশন ১: যে কিনলো (Buyer) তাকে পাঠানো ──
            try {
                // পাত্র/পাত্রীর ব্যক্তিগত নাম্বার বাদ দিয়ে আপনার দেওয়া ভ্যারিয়েবলগুলো পাঠানো হলো
                $buyerNotification = new \App\Notifications\BiodataUnlockedBuyerNotification(
                    $biodataNo,
                    $biodata->name,
                    $biodata->guardian_mobile,
                    $biodata->guardian_relationship,
                    $biodata->contact_email,
                    $user->total_connections
                );

                $user->notify($buyerNotification);

                // Reverb Real-time
                $buyerNotifObj = [
                    'id' => \Illuminate\Support\Str::uuid()->toString(),
                    'type' => 'App\\Notifications\\BiodataUnlockedBuyerNotification',
                    'notifiable_type' => 'App\\Models\\User',
                    'notifiable_id' => $user->id,
                    'data' => $buyerNotification->toArray($user),
                    'read_at' => null,
                    'created_at' => now()->toISOString(),
                    'updated_at' => now()->toISOString(),
                ];
                event(new \App\Events\NotificationSent($user, $buyerNotifObj));

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Buyer Notification Error: ' . $e->getMessage());
            }

            // ── 🔴 নোটিফিকেশন ২: যার বায়োডাটা (Owner) তাকে পাঠানো ──
            try {
                if ($owner) {
                   // \Illuminate\Support\Facades\Log::info("মালিক পাওয়া গেছে! Owner Email: " . $owner->email);

                    // 🔴 ক্রেতার (Buyer) নিজস্ব কোনো অ্যাপ্রুভড বায়োডাটা আছে কিনা চেক করা হচ্ছে
                    $buyerBiodata = \App\Models\Biodata::where('user_id', $user->id)
                                        ->where('status', 'approved')
                                        ->where('is_hidden', 0)
                                        ->first();

                    $buyerBiodataUrl = $buyerBiodata ? config('app.frontend_url') . '/biodata/' . $buyerBiodata->biodata_no : null;

                    // Mailtrap এর লিমিটেশন এড়াতে ৫ সেকেন্ডের Delay
                    $ownerNotification = (new \App\Notifications\BiodataViewedOwnerNotification(
                        $biodataNo,
                        $user->name,       // 🔴 ক্রেতার নাম
                        $buyerBiodataUrl   // 🔴 ক্রেতার বায়োডাটা লিংক (যদি থাকে)
                    ))->delay(now()->addSeconds(5));

                    // ডাটাবেস ও ইমেইলে নোটিফিকেশন পাঠানো
                    $owner->notify($ownerNotification);

                    // Reverb Real-time
                    $ownerNotifObj = [
                        'id' => \Illuminate\Support\Str::uuid()->toString(),
                        'type' => 'App\\Notifications\\BiodataViewedOwnerNotification',
                        'notifiable_type' => 'App\\Models\\User',
                        'notifiable_id' => $owner->id,
                        'data' => (new \App\Notifications\BiodataViewedOwnerNotification($biodataNo, $user->name, $buyerBiodataUrl))->toArray($owner),
                        'read_at' => null,
                        'created_at' => now()->toISOString(),
                        'updated_at' => now()->toISOString(),
                    ];
                    event(new \App\Events\NotificationSent($owner, $ownerNotifObj));
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Owner Notification Error: ' . $e->getMessage());
            }
            // ৬. রেসপন্স রিটার্ন করা
            return response()->json([
                'success' => true,
                'message' => '১টি কানেকশন ব্যবহার করে যোগাযোগের তথ্য উন্মুক্ত করা হয়েছে!',
                'contact_info' => [
                    'phone' => $biodata->phone,
                    'email' => $biodata->email
                ],
                'remaining_connections' => $user->total_connections
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'দুঃখিত, কোনো সমস্যা হয়েছে।'], 500);
        }
    }
}
