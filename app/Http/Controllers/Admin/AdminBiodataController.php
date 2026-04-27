<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Biodata;
use Illuminate\Http\Request;
use App\Notifications\BiodataApprovedNotification;
use App\Notifications\BiodataRejectedNotification;
use Illuminate\Support\Facades\DB;

class AdminBiodataController extends Controller
{
    public function getPendingBiodatas(Request $request)
    {
        $query = Biodata::select('id', 'user_id', 'biodata_no', 'name', 'type', 'candidate_mobile_number', 'updated_at')
            ->with('user:id,email')
            ->where('status', 'pending');

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

        $query->when($request->type && strtolower($request->type) !== 'all', function ($q) use ($request) {
            return $q->where('type', $request->type);
        });

        $biodatas = $query->latest('updated_at')->paginate(10);

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

    public function changeStatus(Request $request, $id) {
        $biodata = Biodata::findOrFail($id);
        $biodata->status = $request->status;
        if ($request->status === 'rejected') {
            $biodata->rejection_reason = $request->reason;
        }
        $biodata->save();
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
        } elseif ($action === 'rejected') {
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
                $biodata->user->notify(new BiodataApprovedNotification());
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
            $biodata->user->notify(new BiodataRejectedNotification($request->reason));
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
