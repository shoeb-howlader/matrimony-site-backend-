<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchasedBiodata;
use Illuminate\Support\Facades\DB;
use App\Models\Biodata;

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
                    'name' => $biodata->name, // বায়োডাটায় প্রার্থীর নাম
                    'guardian_relationship' => $biodata->guardian_relationship, // 🔴 অভিভাবকের সাথে সম্পর্ক
                    'phone' => $biodata->guardian_mobile, // অভিভাবকের নাম্বার
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

            // ৫. আসল যোগাযোগের তথ্য রিটার্ন করা
            $biodata = Biodata::find($biodataId);
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
