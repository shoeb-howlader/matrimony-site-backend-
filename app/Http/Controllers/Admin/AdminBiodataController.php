<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Biodata;
use Illuminate\Http\Request;
use App\Notifications\BiodataApprovedNotification;
use App\Notifications\BiodataRejectedNotification;
use Illuminate\Support\Facades\DB;
use App\Events\NotificationSent;
use Illuminate\Support\Str;

class AdminBiodataController extends Controller
{
  public function getPendingBiodatas(Request $request)
    {
        // 🔴 এখানে select এর ভেতর 'guardian_mobile' এবং 'guardian_relationship' যোগ করা হয়েছে
        $query = Biodata::select('id', 'user_id', 'biodata_no', 'name', 'type', 'candidate_mobile_number', 'guardian_mobile', 'guardian_relationship', 'updated_at')
            ->with('user:id,email')
            ->where('status', 'pending');

        $query->when($request->search, function ($q) use ($request) {
            $search = $request->search;
            return $q->where(function($subQuery) use ($search) {
                $subQuery->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('candidate_mobile_number', 'LIKE', "%{$search}%")
                         ->orWhere('guardian_mobile', 'LIKE', "%{$search}%") // 🔴 সার্চেও অভিভাবকের নাম্বার অ্যাড করা হলো
                         ->orWhere('id', $search)
                         ->orWhere('biodata_no', $search)
                         ->orWhereHas('user', function($u) use ($search) {
                             $u->where('email', 'LIKE', "%{$search}%");
                         });
            });
        });

        $query->when($request->type && strtolower($request->type) !== 'all', function ($q) use ($request) {
            return $q->where('type', $request->type);
        });

        // সর্টিং লজিক
        $sortBy = $request->sort_by ?? 'updated_at';
        $sortDir = $request->sort_dir ?? 'desc';
        $allowedSorts = ['id', 'biodata_no', 'updated_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->latest('updated_at');
        }

        $biodatas = $query->paginate($request->per_page ?? 10);

        return response()->json([
            'success' => true,
            'data' => $biodatas
        ]);
    }

    public function getAllBiodatas(Request $request)
    {
        $query = Biodata::withTrashed()->select(
            'id', 'user_id', 'biodata_no', 'name', 'type', 'candidate_mobile_number', 'guardian_mobile', 'status', 'is_hidden', 'updated_at', 'created_at', 'deleted_at'
        )->with('user:id,name,email,mobile');

        $query->when($request->start_date, fn($q) => $q->whereDate('created_at', '>=', $request->start_date));
        $query->when($request->end_date, fn($q) => $q->whereDate('created_at', '<=', $request->end_date));

        $query->when($request->search, function ($q) use ($request) {
            $search = $request->search;
            return $q->where(function($subQuery) use ($search) {
                $subQuery->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('candidate_mobile_number', 'LIKE', "%{$search}%")
                         ->orWhere('guardian_mobile', 'LIKE', "%{$search}%")
                         ->orWhere('user_id', 'LIKE', "%{$search}%")
                         ->orWhere('biodata_no', 'LIKE', "%{$search}%")
                         ->orWhereHas('user', function($u) use ($search) {
                             $u->where('email', 'LIKE', "%{$search}%")
                               ->orWhere('name', 'LIKE', "%{$search}%")
                               ->orWhere('mobile', 'LIKE', "%{$search}%");
                         });
            });
        });

        $query->when($request->type && strtolower($request->type) !== 'all', fn($q) => $q->where('type', $request->type));

        if ($request->status && strtolower($request->status) !== 'all') {
            if ($request->status === 'deleted') {
                $query->whereNotNull('biodatas.deleted_at');
            } else {
                $query->whereNull('biodatas.deleted_at')->where('biodatas.status', $request->status);
            }
        } else {
            if (empty($request->search)) {
                $query->whereNull('biodatas.deleted_at');
            }
        }

        $query->when($request->visibility && strtolower($request->visibility) !== 'all', function ($q) use ($request) {
            $isHidden = $request->visibility === 'hidden' ? 1 : 0;
            return $q->where('is_hidden', $isHidden);
        });

        // 🔴 ব্যাকএন্ডেও নতুন সর্টিং সাপোর্ট করানো হয়েছে
        $sortBy = $request->sort_by ?? 'updated_at';
        $sortDir = $request->sort_dir ?? 'desc';
        $allowedSorts = ['user_id', 'biodata_no', 'candidate_mobile_number', 'updated_at', 'created_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->latest('updated_at');
        }

        $biodatas = $query->paginate($request->per_page ?? 10);

        return response()->json(['success' => true, 'data' => $biodatas]);
    }

public function changeStatus(Request $request, $id)
    {
        // 🔴 নোটিফিকেশন পাঠানোর জন্য ইউজারের তথ্যও সাথে লোড করা হলো
        $biodata = Biodata::with('user')->findOrFail($id);

        $oldStatus = $biodata->status;
        $newStatus = $request->status;

        $biodata->status = $newStatus;

        if ($newStatus === 'rejected') {
            $biodata->rejection_reason = $request->reason;
            $biodata->is_hidden = 1; // রিজেক্ট হলে হাইড করে দেওয়া ভালো
        } elseif ($newStatus === 'approved') {
            $biodata->is_hidden = 0; // অ্যাপ্রুভ হলে শো করবে
        }

        $biodata->save();

        // ── 🔴 নোটিফিকেশন লজিক শুরু ──
        if ($biodata->user && $oldStatus !== $newStatus) {
            try {
                $notificationType = null;
                $dbNotification = null;

                // যদি অ্যাপ্রুভ করা হয়
                if ($newStatus === 'approved') {
                    $dbNotification = new \App\Notifications\BiodataApprovedNotification($biodata);
                    $notificationType = 'App\\Notifications\\BiodataApprovedNotification';
                }
                // যদি রিজেক্ট করা হয়
                elseif ($newStatus === 'rejected') {
                    $reasonText = $request->reason ?? 'অ্যাডমিন কর্তৃক বাতিল করা হয়েছে';
                    $dbNotification = new \App\Notifications\BiodataRejectedNotification($reasonText);
                    $notificationType = 'App\\Notifications\\BiodataRejectedNotification';
                }

                // যদি স্ট্যাটাস অ্যাপ্রুভ বা রিজেক্ট হয়, তাহলেই শুধু নোটিফিকেশন যাবে
                if ($dbNotification) {
                    // ১. ডাটাবেস নোটিফিকেশন সেভ করা
                    $biodata->user->notify($dbNotification);

                    // 🔴 ২. ক্লাস থেকে সরাসরি ডাটা এক্সট্র্যাক্ট করা (DRY Principle) 🔴
                    $notificationData = $dbNotification->toArray($biodata->user);

                    // ৩. Reverb এর মাধ্যমে রিয়েল-টাইম পুশ করা
                    $notificationObj = [
                        'id' => \Illuminate\Support\Str::uuid()->toString(),
                        'type' => $notificationType,
                        'notifiable_type' => 'App\\Models\\User',
                        'notifiable_id' => $biodata->user->id,
                        'data' => $notificationData, // 👈 ডাটাবেসের হুবহু সেম ডাটা এখানে বসে গেলো
                        'read_at' => null,
                        'created_at' => now()->toISOString(),
                        'updated_at' => now()->toISOString(),
                    ];

                    event(new \App\Events\NotificationSent($biodata->user, $notificationObj));
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Status Change Notification Error: ' . $e->getMessage());
            }
        }

        return response()->json(['success' => true]);
    }


    public function bulkAction(Request $request)
    {
        $ids = $request->ids;
        $action = $request->action;
        $reason = $request->reason;
        $feedback = $request->feedback;

        if ($action === 'delete') {
            $biodatas = Biodata::whereIn('id', $ids)->get();
            foreach ($biodatas as $biodata) {
                \App\Models\BiodataDeletionLog::create([
                    'user_id' => $biodata->user_id,
                    'biodata_no' => $biodata->biodata_no,
                    'reason' => $reason ?? 'other',
                    'feedback' => $feedback ?? 'Admin Bulk Delete',
                ]);
                $biodata->delete();
            }
        }
        // ── 🔴 বাল্ক রিজেক্ট (Bulk Reject) ──
        elseif ($action === 'rejected') {
            $biodatas = Biodata::with('user')->whereIn('id', $ids)->get();
            foreach ($biodatas as $biodata) {
                $biodata->update([
                    'status' => 'rejected',
                    'is_hidden' => 1,
                    'reject_reason' => $reason
                ]);

                if ($biodata->user) {
                    // 🔴 ১. নোটিফিকেশন ক্লাসটি ইনিশিয়ালাইজ করা হলো (রিজন সহ)
                    $notification = new \App\Notifications\BiodataRejectedNotification($reason);

                    // 🔴 ২. ডাটাবেস নোটিফিকেশন
                    $biodata->user->notify($notification);

                    // 🔴 ৩. Reverb রিয়েল-টাইম নোটিফিকেশন (ক্লাস থেকেই সরাসরি ডাটা নেওয়া হলো)
                    try {
                        $notificationData = $notification->toArray($biodata->user);

                        $notificationObj = [
                            'id' => \Illuminate\Support\Str::uuid()->toString(),
                            'type' => 'App\\Notifications\\BiodataRejectedNotification',
                            'notifiable_type' => 'App\\Models\\User',
                            'notifiable_id' => $biodata->user->id,
                            'data' => $notificationData, // 👈 ডাটাবেসের হুবহু সেম ডাটা
                            'read_at' => null,
                            'created_at' => now()->toISOString(),
                            'updated_at' => now()->toISOString(),
                        ];

                        event(new \App\Events\NotificationSent($biodata->user, $notificationObj));
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Bulk Reject Notification Error: ' . $e->getMessage());
                    }
                }
            }
        }
        // ── 🔴 বাল্ক অ্যাপ্রুভ (Bulk Approve) ──
        elseif ($action === 'approved') {
            // বায়োডাটা নাম্বার জেনারেট করার জন্য ট্রানজেকশন ব্যবহার করা হলো
            \Illuminate\Support\Facades\DB::transaction(function () use ($ids) {
                $biodatas = Biodata::with('user')->lockForUpdate()->whereIn('id', $ids)->get();

                foreach ($biodatas as $biodata) {
                    // যদি বায়োডাটা নাম্বার না থাকে, তাহলে নতুন জেনারেট করা হবে
                    if (!$biodata->biodata_no) {
                        $maxNo = Biodata::withTrashed()->max('biodata_no');
                        $maxNo = (int) $maxNo;
                        if ($maxNo < 1000) {
                            $maxNo = 1000;
                        }
                        $biodata->biodata_no = $maxNo + 1;
                    }

                    $biodata->status = 'approved';
                    $biodata->is_hidden = 0;
                    $biodata->save();

                    // নোটিফিকেশন পাঠানো
                    if ($biodata->user) {
                        // 🔴 ১. নোটিফিকেশন ক্লাসটি ইনিশিয়ালাইজ করা হলো
                        $notification = new \App\Notifications\BiodataApprovedNotification($biodata);

                        // 🔴 ২. ডাটাবেস নোটিফিকেশন
                        $biodata->user->notify($notification);

                        // 🔴 ৩. Reverb রিয়েল-টাইম নোটিফিকেশন (ক্লাস থেকেই সরাসরি ডাটা নেওয়া হলো)
                        try {
                            $notificationData = $notification->toArray($biodata->user);

                            $notificationObj = [
                                'id' => \Illuminate\Support\Str::uuid()->toString(),
                                'type' => 'App\\Notifications\\BiodataApprovedNotification',
                                'notifiable_type' => 'App\\Models\\User',
                                'notifiable_id' => $biodata->user->id,
                                'data' => $notificationData, // 👈 ডাটাবেসের হুবহু সেম ডাটা
                                'read_at' => null,
                                'created_at' => now()->toISOString(),
                                'updated_at' => now()->toISOString(),
                            ];

                            event(new \App\Events\NotificationSent($biodata->user, $notificationObj));
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Bulk Approve Notification Error: ' . $e->getMessage());
                        }
                    }
                }
            });
        }
        // অন্যান্য যেকোনো স্ট্যাটাসের জন্য (যেমন: pending)
        else {
            Biodata::whereIn('id', $ids)->update(['status' => $action]);
        }

        return response()->json(['success' => true, 'message' => 'বাল্ক অ্যাকশন সফল হয়েছে']);
    }

    public function exportBiodatas(Request $request)
    {
        $query = Biodata::select(
            'id', 'user_id', 'biodata_no', 'name', 'type', 'candidate_mobile_number', 'status', 'created_at'
        )->with('user:id,email');

        $query->when($request->start_date, fn($q) => $q->whereDate('created_at', '>=', $request->start_date));
        $query->when($request->end_date, fn($q) => $q->whereDate('created_at', '<=', $request->end_date));

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

        $query->when($request->type && strtolower($request->type) !== 'all', fn($q) => $q->where('type', $request->type));
        $query->when($request->status && strtolower($request->status) !== 'all', fn($q) => $q->where('status', $request->status));

        $fileName = 'biodatas_export_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename={$fileName}",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($query) {
            $file = fopen('php://output', 'w');
            fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['ID', 'User ID', 'Biodata No', 'Name', 'Email', 'Type', 'Mobile Number', 'Status', 'Submitted At']);

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

public function approveBiodata($id)
    {
        return DB::transaction(function () use ($id) {
            $biodata = Biodata::lockForUpdate()->findOrFail($id);

            if (!$biodata->biodata_no) {
                $maxNo = Biodata::withTrashed()->max('biodata_no');
                $maxNo = (int) $maxNo;
                if ($maxNo < 1000) {
                    $maxNo = 1000;
                }
                $biodata->biodata_no = $maxNo + 1;
            }

            $biodata->status = 'approved';
            $biodata->is_hidden = 0;
            $biodata->save();

            if ($biodata->user) {
                // 🔴 ১. নোটিফিকেশন ক্লাসটি ইনিশিয়ালাইজ করা হলো
                $notification = new \App\Notifications\BiodataApprovedNotification($biodata);

                // 🔴 ২. ডাটাবেসে নোটিফিকেশন সেভ করা
                $biodata->user->notify($notification);

                // 🔴 ৩. Reverb রিয়েল-টাইম নোটিফিকেশন (ক্লাস থেকেই সরাসরি ডাটা নেওয়া হলো)
                try {
                    // ক্লাসের toArray() থেকে ডাটা নিয়ে নিচ্ছি, তাই ম্যানুয়ালি কিছু লিখতে হলো না!
                    $notificationData = $notification->toArray($biodata->user);

                    $notificationObj = [
                        'id' => \Illuminate\Support\Str::uuid()->toString(),
                        'type' => 'App\\Notifications\\BiodataApprovedNotification',
                        'notifiable_type' => 'App\\Models\\User',
                        'notifiable_id' => $biodata->user->id,
                        'data' => $notificationData, // 👈 ডাটাবেসের হুবহু সেম ডাটা এখানে বসে গেলো
                        'read_at' => null,
                        'created_at' => now()->toISOString(),
                        'updated_at' => now()->toISOString(),
                    ];

                    event(new \App\Events\NotificationSent($biodata->user, $notificationObj));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Approve Notification Realtime Error: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'বায়োডাটা সফলভাবে অনুমোদিত হয়েছে!'
            ]);
        });
    }

   public function rejectBiodata(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);
        $biodata = Biodata::with('user')->findOrFail($id);

        $biodata->status = 'rejected';
        $biodata->is_hidden = 1;
        $biodata->reject_reason = $request->reason;
        $biodata->save();

        if ($biodata->user) {
            // 🔴 ১. নোটিফিকেশন ক্লাসটি ইনিশিয়ালাইজ করা হলো (রিজন সহ)
            $notification = new \App\Notifications\BiodataRejectedNotification($request->reason);

            // 🔴 ২. ডাটাবেস নোটিফিকেশন সেভ করা
            $biodata->user->notify($notification);

            // 🔴 ৩. Reverb রিয়েল-টাইম নোটিফিকেশন (ক্লাস থেকেই সরাসরি ডাটা নেওয়া হলো)
            try {
                // ক্লাসের toArray() থেকে ডাটা নিয়ে নিচ্ছি, ফলে ডাটা মিসম্যাচ হওয়ার কোনো সুযোগ নেই
                $notificationData = $notification->toArray($biodata->user);

                $notificationObj = [
                    'id' => \Illuminate\Support\Str::uuid()->toString(),
                    'type' => 'App\\Notifications\\BiodataRejectedNotification',
                    'notifiable_type' => 'App\\Models\\User',
                    'notifiable_id' => $biodata->user->id,
                    'data' => $notificationData, // 👈 ডাটাবেসের হুবহু সেম ডাটা এখানে বসে গেলো
                    'read_at' => null,
                    'created_at' => now()->toISOString(),
                    'updated_at' => now()->toISOString(),
                ];

                event(new \App\Events\NotificationSent($biodata->user, $notificationObj));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Reject Notification Realtime Error: ' . $e->getMessage());
            }
        }

        return response()->json(['success' => true, 'message' => 'বায়োডাটা বাতিল করা হয়েছে।']);
    }

    public function show($id)
    {
        $biodata = Biodata::with('user:id,name,email')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $biodata]);
    }

    public function deleteBiodata(Request $request, $id)
    {
        $biodata = Biodata::findOrFail($id);

        \App\Models\BiodataDeletionLog::create([
            'user_id' => $biodata->user_id,
            'biodata_no' => $biodata->biodata_no,
            'reason' => $request->reason ?? 'other',
            'feedback' => $request->feedback ?? 'Admin Manual Delete',
        ]);

        $biodata->delete();

        return response()->json(['success' => true, 'message' => 'বায়োডাটা সফলভাবে মুছে ফেলা হয়েছে']);
    }

    public function getDeleteLog($id)
    {
        $biodata = Biodata::withTrashed()->findOrFail($id);
        $log = \App\Models\BiodataDeletionLog::where('biodata_no', $biodata->biodata_no)->latest()->first();

        return response()->json([
            'success' => true,
            'data' => $log
        ]);
    }

    public function restoreBiodata($id)
    {
        $biodata = Biodata::withTrashed()->findOrFail($id);
        $biodata->restore();

        $biodata->status = 'pending';
        $biodata->is_hidden = 1;
        $biodata->save();

        return response()->json([
            'success' => true,
            'message' => 'বায়োডাটা সফলভাবে রিস্টোর করা হয়েছে এবং পেন্ডিং লিস্টে পাঠানো হয়েছে।'
        ]);
    }
}
