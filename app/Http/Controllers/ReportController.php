<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Report;
use App\Models\PurchasedBiodata;
use App\Models\Biodata;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    public function store(Request $request)
    {
        // ১. ডাটা ভ্যালিডেশন
        $request->validate([
            'biodata_no' => 'required|string|exists:biodatas,biodata_no',
            'reason' => 'required|string',
            'description' => 'required|string|min:10',
            'attachment' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // সর্বোচ্চ ২ এমবি
        ]);

        $user = $request->user();
        $biodataNo = $request->biodata_no;

        // ২. সিকিউরিটি চেক: ইউজার কি সত্যিই এই বায়োডাটা কিনেছে?
        $biodata = Biodata::where('biodata_no', $biodataNo)->first();

        // নিজের বায়োডাটা হলে রিপোর্ট করা যাবে না
        if ($biodata->user_id == $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'আপনি নিজের বায়োডাটার বিরুদ্ধে অভিযোগ করতে পারবেন না।'
            ], 403);
        }

        // চেক করা হচ্ছে আগে কিনেছে কিনা
        $hasPurchased = PurchasedBiodata::where('user_id', $user->id)
                                        ->where('biodata_id', $biodata->id)
                                        ->exists();

        if (!$hasPurchased) {
            return response()->json([
                'success' => false,
                'message' => 'আপনি এই বায়োডাটার যোগাযোগের তথ্য আনলক করেননি, তাই অভিযোগ করতে পারবেন না।'
            ], 403);
        }

        // ৩. স্প্যামিং চেক: একই ইউজার একই বায়োডাটার নামে পেন্ডিং অভিযোগ করেছে কিনা?
        $alreadyReported = Report::where('user_id', $user->id)
                                 ->where('biodata_no', $biodataNo)
                                 ->where('status', 'pending')
                                 ->exists();

        if ($alreadyReported) {
            return response()->json([
                'success' => false,
                'message' => 'আপনি ইতিমধ্যে এই বায়োডাটার বিরুদ্ধে একটি অভিযোগ করেছেন, যা এখনো রিভিউয়ের অপেক্ষায় আছে।'
            ], 422);
        }

        // ৪. ফাইল আপলোড (যদি থাকে)
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            // storage/app/public/reports ফোল্ডারে সেভ হবে
            $attachmentPath = $request->file('attachment')->store('reports', 'public');
        }

        // ৫. ডাটাবেজে সেভ করা
        Report::create([
            'user_id' => $user->id,
            'biodata_no' => $biodataNo,
            'reason' => $request->reason,
            'description' => $request->description,
            'attachment' => $attachmentPath,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'আপনার অভিযোগটি সফলভাবে কর্তৃপক্ষের কাছে পাঠানো হয়েছে।'
        ], 201);
    }
}
