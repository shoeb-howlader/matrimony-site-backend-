<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Biodata;
use Illuminate\Http\Request;
use App\Notifications\BiodataApprovedNotification;
use App\Notifications\BiodataRejectedNotification;
use Illuminate\Support\Facades\DB; // 🔴 ডাটাবেজ ট্রানজেকশনের জন্য এটি যুক্ত করা হয়েছে

class AdminBiodataController extends Controller
{
    // ১. পেন্ডিং এবং এডিটেড বায়োডাটাগুলোর লিস্ট পাঠানো
public function getPendingBiodatas(Request $request)
{
    // ১. শুধুমাত্র প্রয়োজনীয় কলামগুলো সিলেক্ট করা হচ্ছে (মেমোরি ও স্পিড অপটিমাইজেশন)
    // নোট: রিলেশনশিপ (user) ঠিকমতো কাজ করার জন্য 'user_id' সিলেক্ট করা বাধ্যতামূলক
    $query = Biodata::select(
            'id',
            'user_id',
            'biodata_no',
            'name',
            'type',
            'candidate_mobile_number',
            'updated_at' // created_at এর বদলে updated_at
        )
        ->with('user:id,email') // ইউজারের টেবিল থেকে শুধু id এবং email আনা হচ্ছে
        ->where('status', 'pending');

    // ২. সার্চ ফিল্টার (নাম, মোবাইল, আইডি, বায়োডাটা নং এবং ইমেইল)
    $query->when($request->search, function ($q) use ($request) {
        $search = $request->search;
        return $q->where(function($subQuery) use ($search) {
            $subQuery->where('name', 'LIKE', "%{$search}%")
                     ->orWhere('candidate_mobile_number', 'LIKE', "%{$search}%")
                     ->orWhere('id', $search)
                     ->orWhere('biodata_no', $search)
                     ->orWhereHas('user', function($u) use ($search) {
                         $u->where('email', 'LIKE', "%{$search}%");
                     });
        });
    });

    // ৩. পাত্র/পাত্রী ফিল্টার (যদি রিকোয়েস্টে 'Male' বা 'Female' আসে)
    $query->when($request->type && strtolower($request->type) !== 'all', function ($q) use ($request) {
        return $q->where('type', $request->type);
    });

    // ৪. updated_at অনুযায়ী সর্টিং এবং পেজিনেশন
    $biodatas = $query->latest('updated_at')->paginate(10);

    // ৫. রেসপন্স পাঠানো হচ্ছে
    return response()->json([
        'success' => true,
        'data' => $biodatas
    ]);
}

// ১. মেইন ডেটা এবং সামারি ফেচ
public function getAllBiodatas(Request $request)
{
    // ১. 'guardian_mobile' কলামটি সিলেক্ট করা হলো
    $query = Biodata::select(
        'id', 'user_id', 'biodata_no', 'name', 'type', 'candidate_mobile_number', 'guardian_mobile', 'status', 'is_hidden', 'updated_at', 'created_at'
    )->with('user:id,name,email,mobile');

    // ২. ডেট রেঞ্জ ফিল্টার
    $query->when($request->start_date, function ($q) use ($request) {
        $q->whereDate('created_at', '>=', $request->start_date);
    });

    $query->when($request->end_date, function ($q) use ($request) {
        $q->whereDate('created_at', '<=', $request->end_date);
    });

    // ৩. উন্নত সার্চ ফিল্টার (guardian_mobile দিয়ে আপডেট করা হয়েছে)
    $query->when($request->search, function ($q) use ($request) {
        $search = $request->search;
        return $q->where(function($subQuery) use ($search) {
            $subQuery->where('name', 'LIKE', "%{$search}%") // পাত্র/পাত্রীর নাম
                     ->orWhere('candidate_mobile_number', 'LIKE', "%{$search}%") // পাত্র/পাত্রীর নাম্বার
                     ->orWhere('guardian_mobile', 'LIKE', "%{$search}%") // 🔴 অভিভাবকের নাম্বার
                     ->orWhere('user_id', 'LIKE', "%{$search}%")       // ইউজার আইডি
                     ->orWhere('biodata_no', 'LIKE', "%{$search}%")   // বায়োডাটা নাম্বার
                     ->orWhereHas('user', function($u) use ($search) {
                         $u->where('email', 'LIKE', "%{$search}%")    // ইউজারের ইমেইল
                           ->orWhere('name', 'LIKE', "%{$search}%")   // ইউজারের প্রোফাইল নাম
                           ->orWhere('mobile', 'LIKE', "%{$search}%"); // ইউজারের প্রোফাইল মোবাইল
                     });
        });
    });

    // ৪. ধরন ফিল্টার (পাত্র/পাত্রী)
    $query->when($request->type && strtolower($request->type) !== 'all', function ($q) use ($request) {
        return $q->where('type', $request->type);
    });

    // ৫. স্ট্যাটাস ফিল্টার
    $query->when($request->status && strtolower($request->status) !== 'all', function ($q) use ($request) {
        return $q->where('status', $request->status);
    });

    // ৬. ভিজিবিলিটি ফিল্টার
    $query->when($request->visibility && strtolower($request->visibility) !== 'all', function ($q) use ($request) {
        $isHidden = $request->visibility === 'hidden' ? 1 : 0;
        return $q->where('is_hidden', $isHidden);
    });

    // ৭. ডাইনামিক সর্টিং (Sorting) লজিক
    $sortBy = $request->sort_by ?? 'updated_at';
    $sortDir = $request->sort_dir ?? 'desc';

    $allowedSorts = ['user_id', 'biodata_no', 'candidate_mobile_number', 'updated_at'];

    if (in_array($sortBy, $allowedSorts)) {
        $query->orderBy($sortBy, $sortDir);
    } else {
        $query->latest('updated_at');
    }

    // ৮. ডাইনামিক পেজিনেশন
    $perPage = $request->per_page ?? 10;
    $biodatas = $query->paginate($perPage);

    // ৯. রেসপন্স পাঠানো
    return response()->json([
        'success' => true,
        'data' => $biodatas
    ]);
}

// ২. কুইক স্ট্যাটাস চেঞ্জ
// ১. কুইক স্ট্যাটাস চেঞ্জের জন্য
public function changeStatus(Request $request, $id) {
    $biodata = Biodata::findOrFail($id);
    $biodata->status = $request->status;
    if ($request->status === 'rejected') {
        $biodata->rejection_reason = $request->reason; // নিশ্চিত করুন টেবিলে এই কলামটি আছে
    }
    $biodata->save();
    return response()->json(['success' => true]);
}

// ২. বাল্ক অ্যাকশনের জন্য
public function bulkAction(Request $request)
{
    $ids = $request->ids;
    $action = $request->action;
    $reason = $request->reason;

    if ($action === 'delete') {
        Biodata::whereIn('id', $ids)->delete();
    } elseif ($action === 'rejected') {
        // লুপ চালিয়ে রিজেক্ট করা যেন সবাই নোটিফিকেশন পায়
        $biodatas = Biodata::with('user')->whereIn('id', $ids)->get();
        foreach ($biodatas as $biodata) {
            $biodata->update([
                'status' => 'rejected',
                'is_hidden' => 1,
                'reject_reason' => $reason
            ]);

            if ($biodata->user) {
                $biodata->user->notify(new BiodataRejectedNotification($reason));
            }
        }
    } else {
        Biodata::whereIn('id', $ids)->update(['status' => $action]);
    }

    return response()->json(['success' => true, 'message' => 'বাল্ক অ্যাকশন সফল হয়েছে']);
}

// ৪. এক্সপোর্ট (CSV)
public function exportBiodatas(Request $request)
{
    // ১. কুয়েরি বিল্ড করা (getAllBiodatas এর মতোই সেম ফিল্টার লজিক)
    $query = Biodata::select(
        'id', 'user_id', 'biodata_no', 'name', 'type', 'candidate_mobile_number', 'status', 'created_at'
    )->with('user:id,email');

    // ডেট রেঞ্জ ফিল্টার
    $query->when($request->start_date, fn($q) => $q->whereDate('created_at', '>=', $request->start_date));
    $query->when($request->end_date, fn($q) => $q->whereDate('created_at', '<=', $request->end_date));

    // সার্চ ফিল্টার
    $query->when($request->search, function ($q) use ($request) {
        $search = $request->search;
        return $q->where(function($subQuery) use ($search) {
            $subQuery->where('name', 'LIKE', "%{$search}%")
                     ->orWhere('candidate_mobile_number', 'LIKE', "%{$search}%")
                     ->orWhere('id', 'LIKE', "%{$search}%")
                     ->orWhere('biodata_no', 'LIKE', "%{$search}%")
                     ->orWhereHas('user', fn($u) => $u->where('email', 'LIKE', "%{$search}%"));
        });
    });

    // ধরন এবং স্ট্যাটাস ফিল্টার
    $query->when($request->type && strtolower($request->type) !== 'all', fn($q) => $q->where('type', $request->type));
    $query->when($request->status && strtolower($request->status) !== 'all', fn($q) => $q->where('status', $request->status));

    // ২. এক্সপোর্টের জন্য হেডার সেট করা
    $fileName = 'biodatas_export_' . date('Y-m-d_H-i-s') . '.csv';
    $headers = [
        "Content-type"        => "text/csv",
        "Content-Disposition" => "attachment; filename={$fileName}",
        "Pragma"              => "no-cache",
        "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
        "Expires"             => "0"
    ];

    // ৩. স্ট্রিম রেসপন্স কলব্যাক (সার্ভারের মেমোরি বাঁচানোর জন্য)
    $callback = function() use ($query) {
        $file = fopen('php://output', 'w');

        // 🔴 UTF-8 BOM যুক্ত করা হচ্ছে যেন এক্সেলে বাংলা ফন্ট ঠিকমতো দেখায়
        fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // CSV এর হেডার বা কলামের নামগুলো
        fputcsv($file, ['ID', 'User ID', 'Biodata No', 'Name', 'Email', 'Type', 'Mobile Number', 'Status', 'Submitted At']);

        // 500 টা করে ডাটা তুলে আনা হচ্ছে
        $query->chunk(500, function($biodatas) use ($file) {
            foreach ($biodatas as $biodata) {
                fputcsv($file, [
                    $biodata->id,
                    $biodata->user_id,
                    $biodata->biodata_no ?? 'N/A',
                    $biodata->name,
                    $biodata->user ? $biodata->user->email : 'N/A',
                    $biodata->type === 'Male' ? 'পাত্র' : 'পাত্রী',
                    $biodata->candidate_mobile_number,
                    ucfirst($biodata->status),
                    $biodata->created_at ? $biodata->created_at->format('Y-m-d h:i A') : 'N/A'
                ]);
            }
        });

        fclose($file);
    };

    return response()->stream($callback, 200, $headers);
}

    // ২. বায়োডাটা এপ্রুভ করা (🔴 প্রফেশনাল লজিক আপডেট)
    public function approveBiodata($id)
    {
        // DB Transaction ব্যবহার করা হয়েছে যাতে প্রসেস শেষ না হওয়া পর্যন্ত ডাটাবেজে কোনো গণ্ডগোল না হয়
        return DB::transaction(function () use ($id) {
            // lockForUpdate() - এর মাধ্যমে নিশ্চিত করা হয়েছে একই সময়ে দুজন এডমিন এপ্রুভ করলেও ডাবল নম্বর জেনারেট হবে না
            $biodata = Biodata::lockForUpdate()->findOrFail($id);

            // নম্বর না থাকলে জেনারেট করা
            if (!$biodata->biodata_no) {
                // withTrashed() এর মাধ্যমে ট্র্যাশে থাকা বা ডিলিট হওয়া নম্বরগুলোকেও কাউন্ট করা হবে
                $maxNo = Biodata::withTrashed()->max('biodata_no');

                $maxNo = (int) $maxNo;

                // নম্বর ১০০০ এর কম থাকলে ডিফল্ট ১০০০ থেকে শুরু হবে
                if ($maxNo < 1000) {
                    $maxNo = 1000;
                }

                $biodata->biodata_no = $maxNo + 1;
            }

            $biodata->status = 'approved';
            $biodata->is_hidden = 0; // 🔴 এপ্রুভ হওয়ার পর বায়োডাটা লাইভ (unhidden) হয়ে যাবে
            $biodata->save();

            // 🔴 ইউজারকে নোটিফিকেশন পাঠানো
            if ($biodata->user) {
                $biodata->user->notify(new BiodataApprovedNotification());
            }

            return response()->json([
                'success' => true,
                'message' => 'বায়োডাটা সফলভাবে অনুমোদিত হয়েছে!'
            ]);
        });
    }

    // ৩. বায়োডাটা রিজেক্ট বা বাতিল করা
    public function rejectBiodata(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $biodata = Biodata::with('user')->findOrFail($id);

        $biodata->status = 'rejected';
        $biodata->is_hidden = 1; // হাইড করে দেওয়া হলো
        $biodata->reject_reason = $request->reason; // 🔴 ডাটাবেজে লেটেস্ট রিজন সেভ হলো
        $biodata->save();

        // 🔴 ইউজারকে কারণসহ নোটিফিকেশন পাঠানো
        if ($biodata->user) {
            $biodata->user->notify(new BiodataRejectedNotification($request->reason));
        }

        return response()->json([
            'success' => true,
            'message' => 'বায়োডাটা বাতিল করা হয়েছে এবং ইউজারকে নোটিফিকেশন পাঠানো হয়েছে।'
        ]);
    }

    // ৪. বায়োডাটার বিস্তারিত দেখানো
    public function show($id)
    {
        // বায়োডাটার সাথে ইউজারের বেসিক তথ্যও নিয়ে আসবো
        $biodata = Biodata::with('user:id,name,email')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $biodata
        ]);
    }

        public function deleteBiodata($id)
{
    $biodata = Biodata::findOrFail($id);

    // বায়োডাটা ডিলিট করা হচ্ছে
    $biodata->delete();

    return response()->json([
        'success' => true,
        'message' => 'বায়োডাটা সফলভাবে মুছে ফেলা হয়েছে'
    ]);
}
}
