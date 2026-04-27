<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Notifications\UserAlertNotification;

class AdminUserController extends Controller
{
    public function getUsers(Request $request)
    {
        $query = User::select('users.id', 'users.name', 'users.email', 'users.mobile', 'users.role', 'users.status', 'users.created_at')
                     ->with('biodata:id,user_id,biodata_no,status');

        $query->when($request->start_date, fn ($q) => $q->whereDate('users.created_at', '>=', $request->start_date));
        $query->when($request->end_date, fn ($q) => $q->whereDate('users.created_at', '<=', $request->end_date));

        $query->when($request->search, function ($q) use ($request) {
            $search = $request->search;
            return $q->where(function($subQuery) use ($search) {
                $subQuery->where('users.name', 'LIKE', "%{$search}%")
                         ->orWhere('users.email', 'LIKE', "%{$search}%")
                         ->orWhere('users.mobile', 'LIKE', "%{$search}%")
                         ->orWhere('users.id', 'LIKE', "%{$search}%")
                         ->orWhereHas('biodata', function($b) use ($search) {
                             $b->where('biodata_no', 'LIKE', "%{$search}%")
                               ->orWhere('name', 'LIKE', "%{$search}%")
                               ->orWhere('candidate_mobile_number', 'LIKE', "%{$search}%")
                               ->orWhere('guardian_mobile', 'LIKE', "%{$search}%");
                         });
            });
        });

        $query->when($request->role && strtolower($request->role) !== 'all', fn ($q) => $q->where('users.role', $request->role));
        $query->when($request->status && strtolower($request->status) !== 'all', fn ($q) => $q->where('users.status', $request->status));
        $query->when($request->has_biodata && $request->has_biodata !== 'all', function ($q) use ($request) {
            return $request->has_biodata === 'yes' ? $q->whereHas('biodata') : $q->doesntHave('biodata');
        });
        $query->when($request->biodata_status && strtolower($request->biodata_status) !== 'all', function ($q) use ($request) {
            return $q->whereHas('biodata', fn($b) => $b->where('status', $request->biodata_status));
        });

        $sortBy = $request->sort_by ?? 'created_at';
        $sortDir = $request->sort_dir ?? 'desc';

        if ($sortBy === 'biodata_no') {
            $query->orderBy(\App\Models\Biodata::select('biodata_no')->whereColumn('biodatas.user_id', 'users.id'), $sortDir);
        } else {
            $allowedSorts = ['id', 'name', 'email', 'mobile', 'created_at'];
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy('users.' . $sortBy, $sortDir);
            } else {
                $query->orderBy('users.created_at', 'desc');
            }
        }

        $perPage = $request->per_page ?? 10;
        $users = $query->paginate($perPage);

        return response()->json(['success' => true, 'data' => $users, 'total' => $users->total()]);
    }

    public function changeStatus(Request $request, $id) {
        $user = User::findOrFail($id); $user->status = $request->status; $user->save();
        return response()->json(['success' => true]);
    }

    public function bulkAction(Request $request) {
        $ids = $request->ids; $action = $request->action;
        if ($action === 'delete') { User::whereIn('id', $ids)->delete(); }
        else { User::whereIn('id', $ids)->update(['status' => $action]); }
        return response()->json(['success' => true]);
    }

    public function updateUser(Request $request, $id) {
        $user = User::findOrFail($id);
        if ($request->has('role')) $user->role = $request->role;
        if ($request->has('status')) $user->status = $request->status;
        $user->save();
        return response()->json(['success' => true, 'message' => 'ইউজার সফলভাবে আপডেট হয়েছে']);
    }

// ─── 🔴 User Details with Stats, Suspicious Check & Restriction ───
    public function getUserDetails($id)
    {
        $user = User::with(['biodata' => fn($q) => $q->withTrashed()])->findOrFail($id);
        $data = $user->toArray();

        // Total Spent & Other Stats (আগের মতোই থাকবে)
        $data['total_spent'] = \App\Models\Transaction::where('user_id', $user->id)->whereIn('status', ['success', 'completed', 'paid'])->sum('amount');
        $data['views_count'] = $user->biodata ? \App\Models\BiodataView::where('biodata_id', $user->biodata->id)->count() : 0;
        $data['visits_count'] = \App\Models\BiodataView::where('viewer_id', $user->id)->count();
        $data['shortlists_count'] = \App\Models\BiodataPreference::where('user_id', $user->id)->where('type', 'shortlist')->count();
        $data['dislikes_count'] = \App\Models\BiodataPreference::where('user_id', $user->id)->where('type', 'ignore')->count();
        $data['unlocked_count'] = \App\Models\PurchasedBiodata::where('user_id', $user->id)->count();
        $data['reports_made_count'] = \App\Models\SupportTicket::where('user_id', $user->id)->where('category', 'biodata_report')->count();
        $data['reports_received_count'] = $user->biodata ? \App\Models\SupportTicket::where('biodata_no', $user->biodata->biodata_no)->where('category', 'biodata_report')->count() : 0;
        $data['support_tickets_count'] = \App\Models\SupportTicket::where('user_id', $user->id)->where('category', '!=', 'biodata_report')->count();
        $data['shortlisted_by_count'] = $user->biodata ? \App\Models\BiodataPreference::where('biodata_id', $user->biodata->id)->where('type', 'shortlist')->count() : 0;
        $data['disliked_by_count'] = $user->biodata ? \App\Models\BiodataPreference::where('biodata_id', $user->biodata->id)->where('type', 'ignore')->count() : 0;
        $data['unlocked_by_count'] = $user->biodata ? \App\Models\PurchasedBiodata::where('biodata_id', $user->biodata->id)->count() : 0;

        // 🔴 Fake Delete Checker: গত ৩০ দিনে কয়বার ডিলিট করেছে
        $recentDeletesCount = \App\Models\BiodataDeletionLog::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $data['recent_delete_count'] = $recentDeletesCount;
        $data['is_suspicious'] = $recentDeletesCount > 1; // একের বেশি হলে Suspicious

        // Deletion Logs Fetch
        $logs = \App\Models\BiodataDeletionLog::where('user_id', $user->id)->latest()->get();
        $deletionLogsArray = [];
        foreach ($logs as $log) {
            $trashedBio = \App\Models\Biodata::withTrashed()->where('biodata_no', $log->biodata_no)->first();
            $logArr = $log->toArray();
            $logArr['biodata_id'] = $trashedBio ? $trashedBio->id : null;
            $deletionLogsArray[] = $logArr;
        }
        $data['deletion_logs'] = $deletionLogsArray;

        $data['packages'] = \App\Models\ConnectionPackage::select('id', 'name')->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    // ─── API Lists with withTrashed() ───
    public function getUserViews(Request $request, $id)
    {
        $user = User::with(['biodata' => fn($q) => $q->withTrashed()])->findOrFail($id);
        if (!$user->biodata) return response()->json(['success' => true, 'data' => [], 'total' => 0]);

        $query = \App\Models\BiodataView::with(['viewer:id,name,email,mobile', 'viewer.biodata' => fn($q) => $q->withTrashed()->select('id', 'user_id', 'biodata_no', 'status', 'deleted_at')])
            ->where('biodata_id', $user->biodata->id);

        $query->when($request->start_date, fn($q) => $q->whereDate('created_at', '>=', $request->start_date));
        $query->when($request->end_date, fn($q) => $q->whereDate('created_at', '<=', $request->end_date));
        $query->when($request->viewer_type && $request->viewer_type !== 'all', function($q) use ($request) {
            return $request->viewer_type === 'logged_in' ? $q->whereNotNull('viewer_id') : $q->whereNull('viewer_id');
        });
        $query->when($request->biodata_status && $request->biodata_status !== 'all', function ($q) use ($request) {
            if ($request->biodata_status === 'deleted' || $request->biodata_status === 'none') {
                $q->whereDoesntHave('viewer.biodata')->orWhereHas('viewer.biodata', fn($b) => $b->onlyTrashed());
            } else {
                $q->whereHas('viewer.biodata', fn($b) => $b->where('status', $request->biodata_status)->whereNull('deleted_at'));
            }
        });
        $query->when($request->search, function ($q) use ($request) {
            $search = $request->search;
            return $q->where(function($sub) use ($search) {
                $sub->where('ip_address', 'LIKE', "%{$search}%")->orWhereHas('viewer', function($v) use ($search) {
                    $v->where('name', 'LIKE', "%{$search}%")->orWhere('email', 'LIKE', "%{$search}%")->orWhere('mobile', 'LIKE', "%{$search}%")->orWhere('id', 'LIKE', "%{$search}%");
                })->orWhereHas('viewer.biodata', fn($b) => $b->withTrashed()->where('biodata_no', 'LIKE', "%{$search}%"));
            });
        });

        $views = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 10);
        return response()->json(['success' => true, 'data' => $views, 'total' => $views->total()]);
    }

    public function getUserVisits(Request $request, $id)
    {
        $query = \App\Models\BiodataView::with(['biodata' => fn($q) => $q->withTrashed()->select('id', 'user_id', 'biodata_no', 'status', 'deleted_at'), 'biodata.user:id,name,email,mobile'])
            ->where('viewer_id', $id);

        $query->when($request->start_date, fn($q) => $q->whereDate('created_at', '>=', $request->start_date));
        $query->when($request->end_date, fn($q) => $q->whereDate('created_at', '<=', $request->end_date));
        $query->when($request->biodata_status && $request->biodata_status !== 'all', function ($q) use ($request) {
            if ($request->biodata_status === 'deleted') {
                $q->whereDoesntHave('biodata')->orWhereHas('biodata', fn($b) => $b->onlyTrashed());
            } else {
                $q->whereHas('biodata', fn($b) => $b->where('status', $request->biodata_status)->whereNull('deleted_at'));
            }
        });
        $query->when($request->search, function ($q) use ($request) {
            $search = $request->search;
            return $q->whereHas('biodata', function($b) use ($search) {
                $b->withTrashed()->where('biodata_no', 'LIKE', "%{$search}%")->orWhereHas('user', function($u) use ($search) {
                    $u->where('name', 'LIKE', "%{$search}%")->orWhere('email', 'LIKE', "%{$search}%")->orWhere('mobile', 'LIKE', "%{$search}%")->orWhere('id', 'LIKE', "%{$search}%");
                });
            });
        });

        $visits = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 10);
        return response()->json(['success' => true, 'data' => $visits, 'total' => $visits->total()]);
    }

    private function buildBiodataRelationQuery($modelClass, $request, $userId, $extraCondition = null) {
        $query = $modelClass::with(['biodata' => fn($q) => $q->withTrashed()->select('id', 'user_id', 'biodata_no', 'status', 'deleted_at'), 'biodata.user:id,name,email,mobile'])
            ->where('user_id', $userId);

        if ($extraCondition) $extraCondition($query);

        $query->when($request->start_date, fn($q) => $q->whereDate('created_at', '>=', $request->start_date));
        $query->when($request->end_date, fn($q) => $q->whereDate('created_at', '<=', $request->end_date));
        $query->when($request->biodata_status && $request->biodata_status !== 'all', function ($q) use ($request) {
            if ($request->biodata_status === 'deleted') {
                $q->whereDoesntHave('biodata')->orWhereHas('biodata', fn($b) => $b->onlyTrashed());
            } else {
                $q->whereHas('biodata', fn($b) => $b->where('status', $request->biodata_status)->whereNull('deleted_at'));
            }
        });
        $query->when($request->search, function ($q) use ($request) {
            $search = $request->search;
            return $q->whereHas('biodata', function($b) use ($search) {
                $b->withTrashed()->where('biodata_no', 'LIKE', "%{$search}%")->orWhereHas('user', function($u) use ($search) {
                    $u->where('name', 'LIKE', "%{$search}%")->orWhere('email', 'LIKE', "%{$search}%")->orWhere('mobile', 'LIKE', "%{$search}%")->orWhere('id', 'LIKE', "%{$search}%");
                });
            });
        });

        return $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 10);
    }

    public function getUserShortlists(Request $request, $id) {
        $data = $this->buildBiodataRelationQuery(\App\Models\BiodataPreference::class, $request, $id, fn($q) => $q->where('type', 'favorite'));
        return response()->json(['success' => true, 'data' => $data, 'total' => $data->total()]);
    }
    public function getUserDislikes(Request $request, $id) {
        $data = $this->buildBiodataRelationQuery(\App\Models\BiodataPreference::class, $request, $id, fn($q) => $q->where('type', 'ignore'));
        return response()->json(['success' => true, 'data' => $data, 'total' => $data->total()]);
    }
    public function getUserUnlocked(Request $request, $id) {
        $data = $this->buildBiodataRelationQuery(\App\Models\PurchasedBiodata::class, $request, $id);
        return response()->json(['success' => true, 'data' => $data, 'total' => $data->total()]);
    }

   private function buildSupportTicketQuery($userId, $type, $request) {
        $query = \App\Models\SupportTicket::query();

        if ($type === 'received') {
            $user = User::with(['biodata' => fn($q) => $q->withTrashed()])->find($userId);
            if (!$user || !$user->biodata || !$user->biodata->biodata_no) return \App\Models\SupportTicket::whereRaw('1 = 0')->paginate(10);

            $query->with(['user:id,name,email,mobile', 'user.biodata' => fn($q) => $q->withTrashed()->select('id', 'user_id', 'biodata_no', 'status', 'deleted_at')])
                  ->where('biodata_no', $user->biodata->biodata_no)->where('category', 'biodata_report');
        } elseif ($type === 'made') {
            $query->with(['reportedBiodata' => fn($q) => $q->withTrashed()->select('id', 'user_id', 'biodata_no', 'status', 'deleted_at'), 'reportedBiodata.user:id,name,email,mobile'])
                  ->where('user_id', $userId)->where('category', 'biodata_report');
        } elseif ($type === 'support') {
            $query->where('user_id', $userId)->where('category', '!=', 'biodata_report');
        }

        $query->when($request->start_date, fn($q) => $q->whereDate('created_at', '>=', $request->start_date));
        $query->when($request->end_date, fn($q) => $q->whereDate('created_at', '<=', $request->end_date));
        $query->when($request->status && $request->status !== 'all', fn($q) => $q->where('status', $request->status));

        $query->when($request->search, function ($q) use ($request, $type) {
            $search = $request->search;
            return $q->where(function($sub) use ($search, $type) {
                // সব ট্যাবের জন্য কমন সার্চ (ID এবং Subject)
                $sub->where('id', 'LIKE', "%{$search}%")->orWhere('subject', 'LIKE', "%{$search}%");

                if ($type === 'received') {
                    // অভিযোগকারীর (Reporter) তথ্য এবং তার বায়োডাটা নম্বর দিয়ে সার্চ (ডিলিট হওয়া বায়োডাটা সহ)
                    $sub->orWhereHas('user', function($u) use ($search) {
                        $u->where('name', 'LIKE', "%{$search}%")
                          ->orWhere('email', 'LIKE', "%{$search}%")
                          ->orWhere('mobile', 'LIKE', "%{$search}%")
                          ->orWhere('id', 'LIKE', "%{$search}%")
                          ->orWhereHas('biodata', function($b) use ($search) {
                              $b->withTrashed()->where('biodata_no', 'LIKE', "%{$search}%");
                          });
                    });
                } elseif ($type === 'made') {
                    // অভিযুক্তের (Reported) বায়োডাটা নম্বর অথবা তার অন্যান্য তথ্য দিয়ে সার্চ
                    $sub->orWhere('biodata_no', 'LIKE', "%{$search}%")->orWhereHas('reportedBiodata.user', function($u) use ($search) {
                        $u->where('name', 'LIKE', "%{$search}%")
                          ->orWhere('email', 'LIKE', "%{$search}%")
                          ->orWhere('mobile', 'LIKE', "%{$search}%")
                          ->orWhere('id', 'LIKE', "%{$search}%");
                    });
                } elseif ($type === 'support') {
                    // সাপোর্ট টিকিটের ক্যাটাগরি এবং মেসেজ দিয়ে সার্চ
                    $sub->orWhere('category', 'LIKE', "%{$search}%")
                        ->orWhere('message', 'LIKE', "%{$search}%");
                }
            });
        });

        return $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 10);
    }

    public function getUserReportsReceived(Request $request, $id) {
        $data = $this->buildSupportTicketQuery($id, 'received', $request);
        return response()->json(['success' => true, 'data' => $data, 'total' => $data->total()]);
    }
    public function getUserReportsMade(Request $request, $id) {
        $data = $this->buildSupportTicketQuery($id, 'made', $request);
        return response()->json(['success' => true, 'data' => $data, 'total' => $data->total()]);
    }
    public function getUserSupportTickets(Request $request, $id) {
        $data = $this->buildSupportTicketQuery($id, 'support', $request);
        return response()->json(['success' => true, 'data' => $data, 'total' => $data->total()]);
    }

    public function replySupportTicket(Request $request, $ticket_id) {
        $ticket = \App\Models\SupportTicket::findOrFail($ticket_id);
        if ($request->has('status')) $ticket->status = $request->status;
        if ($request->has('admin_reply')) $ticket->admin_reply = $request->admin_reply;
        $ticket->save();
        return response()->json(['success' => true, 'message' => 'আপডেট করা হয়েছে!']);
    }

    // ─── পেমেন্ট হিস্ট্রি ───
    public function getUserPurchases(Request $request, $id)
    {
        $query = \App\Models\Transaction::with('connectionPackage:id,name')->where('user_id', $id);

        $query->when($request->start_date, fn($q) => $q->whereDate('created_at', '>=', $request->start_date));
        $query->when($request->end_date, fn($q) => $q->whereDate('created_at', '<=', $request->end_date));
        $query->when($request->status && $request->status !== 'all', fn($q) => $q->where('status', $request->status));
        $query->when($request->method && $request->method !== 'all', fn($q) => $q->where('payment_method', $request->method));

        // 🔴 প্যাকেজ ফিল্টার
        $query->when($request->package_id && $request->package_id !== 'all', fn($q) => $q->where('connection_package_id', $request->package_id));

        $query->when($request->search, function($q) use ($request) {
            $search = $request->search;
            return $q->where(function($sub) use ($search) {
                $sub->where('transaction_id', 'LIKE', "%{$search}%")->orWhereHas('connectionPackage', fn($p) => $p->where('name', 'LIKE', "%{$search}%"));
            });
        });

        $totalAmount = $query->sum('amount');
        $data = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 10);

        return response()->json(['success' => true, 'data' => $data, 'total' => $data->total(), 'totalAmount' => $totalAmount]);
    }

    public function changeTransactionStatus(Request $request, $id) {
        $transaction = \App\Models\Transaction::findOrFail($id);
        $transaction->status = $request->status;
        $transaction->save();
        return response()->json(['success' => true, 'message' => 'পেমেন্ট স্ট্যাটাস আপডেট করা হয়েছে!']);
    }

    // ─── 🔴 নতুন: তার বায়োডাটা যারা পছন্দ/অপছন্দ করেছে ───
    private function buildBiodataPreferenceByOthersQuery($type, $biodataId, $request) {
        // এখানে user এর সাথে তার biodata (ডিলিট হওয়া সহ) লোড করা হলো
        $query = \App\Models\BiodataPreference::with([
            'user:id,name,email,mobile',
            'user.biodata' => fn($q) => $q->withTrashed()->select('id', 'user_id', 'biodata_no', 'status', 'deleted_at')
        ])
        ->where('biodata_id', $biodataId)
        ->where('type', $type);

        $query->when($request->start_date, fn($q) => $q->whereDate('created_at', '>=', $request->start_date));
        $query->when($request->end_date, fn($q) => $q->whereDate('created_at', '<=', $request->end_date));

        $query->when($request->search, function ($q) use ($request) {
            $search = $request->search;
            return $q->whereHas('user', function($u) use ($search) {
                $u->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('mobile', 'LIKE', "%{$search}%")
                  ->orWhere('id', 'LIKE', "%{$search}%");
            });
        });

        return $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 10);
    }

    public function getUserBiodataShortlistedBy(Request $request, $id) {
        $user = User::with('biodata')->findOrFail($id);
        if (!$user->biodata) return response()->json(['success' => true, 'data' => [], 'total' => 0]);
        $data = $this->buildBiodataPreferenceByOthersQuery('favorite', $user->biodata->id, $request);
        return response()->json(['success' => true, 'data' => $data, 'total' => $data->total()]);
    }

    public function getUserBiodataDislikedBy(Request $request, $id) {
        $user = User::with('biodata')->findOrFail($id);
        if (!$user->biodata) return response()->json(['success' => true, 'data' => [], 'total' => 0]);
        $data = $this->buildBiodataPreferenceByOthersQuery('ignore', $user->biodata->id, $request);
        return response()->json(['success' => true, 'data' => $data, 'total' => $data->total()]);
    }

    // ─── অ্যাডমিন প্যানেল থেকে ইউজারের বায়োডাটা লাইভ/হাইড করা ───
    public function toggleUserBiodataVisibility($id)
    {
        $user = User::with(['biodata' => fn($q) => $q->withTrashed()])->findOrFail($id);

        if (!$user->biodata) {
            return response()->json(['success' => false, 'message' => 'বায়োডাটা পাওয়া যায়নি!']);
        }

        $user->biodata->is_hidden = !$user->biodata->is_hidden;
        $user->biodata->save();

        $statusText = $user->biodata->is_hidden ? 'হাইড' : 'লাইভ';
        return response()->json([
            'success' => true,
            'message' => "বায়োডাটা সফলভাবে {$statusText} করা হয়েছে!",
            'is_hidden' => $user->biodata->is_hidden
        ]);
    }

    // ─── ডিলিট হওয়া বায়োডাটা রিস্টোর করা ───
// ─── 🔴 Restore Biodata with Admin Note & Notification ───
    public function restoreBiodata(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $biodata = \App\Models\Biodata::withTrashed()->where('user_id', $id)->first();

        if ($biodata && $biodata->trashed()) {
            $biodata->restore(); // সফট ডিলিট থেকে ফিরিয়ে আনা

            // সর্বশেষ ডিলিট লগে অ্যাডমিন নোট সেভ করা
            $latestLog = \App\Models\BiodataDeletionLog::where('user_id', $id)->latest()->first();
            if ($latestLog) {
                $latestLog->restored_at = now();
                $latestLog->admin_note = $request->admin_note ?? 'No note provided';
                $latestLog->save();
            }

            // রেস্ট্রিকশন থাকলে তা উঠিয়ে নেওয়া
            $user->restriction_expires_at = null;
            $user->save();

            // 📩 🔴 ইউজারকে নোটিফিকেশন পাঠানো
            $user->notify(new UserAlertNotification(
                'বায়োডাটা রিস্টোর করা হয়েছে!',
                'অ্যাডমিন আপনার ডিলিট করা বায়োডাটাটি পুনরায় রিস্টোর করেছেন এবং অ্যাকাউন্টের রেস্ট্রিকশন তুলে নিয়েছেন।',
                '/user/dashboard'
            ));

            return response()->json(['success' => true, 'message' => 'বায়োডাটা রিস্টোর করা হয়েছে এবং ইউজারকে নোটিফিকেশন পাঠানো হয়েছে!']);
        }
        return response()->json(['success' => false, 'message' => 'বায়োডাটা পাওয়া যায়নি!']);
    }

    // ─── 🔴 রেস্ট্রিকশন ম্যানুয়ালি তুলে নেওয়া এবং নোটিফিকেশন ───
    public function removeRestriction($id)
    {
        $user = User::findOrFail($id);
        $user->restriction_expires_at = null;
        $user->save();

        // 📩 🔴 ইউজারকে নোটিফিকেশন পাঠানো
        $user->notify(new UserAlertNotification(
            'অ্যাকাউন্ট রেস্ট্রিকশন বাতিল',
            'আপনার অ্যাকাউন্টের ওপর থাকা রেস্ট্রিকশন অ্যাডমিন কর্তৃক তুলে নেওয়া হয়েছে। আপনি এখন নতুন বায়োডাটা তৈরি করতে পারবেন।',
            '/user/dashboard'
        ));

        return response()->json(['success' => true, 'message' => 'অ্যাকাউন্টের রেস্ট্রিকশন তুলে নেওয়া হয়েছে এবং নোটিফিকেশন পাঠানো হয়েছে!']);
    }

public function getAllDeletionLogs(Request $request)
{
    $query = \App\Models\BiodataDeletionLog::with(['user']);

    // ১. সার্চ লজিক
    if ($request->search) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->where('biodata_no', 'like', "%$search%")
              ->orWhereHas('user', function($uq) use ($search) {
                  $uq->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('mobile', 'like', "%$search%");
              });
        });
    }

    // 🔴 ২. ডিলিটের কারণ অনুযায়ী ফিল্টার (নতুন যুক্ত)
    if ($request->reason && $request->reason !== 'all') {
        $query->where('reason', $request->reason);
    }

    // ৩. ডেট ফিল্টার
    if ($request->start_date) {
        $query->whereDate('created_at', '>=', $request->start_date);
    }
    if ($request->end_date) {
        $query->whereDate('created_at', '<=', $request->end_date);
    }

    $sortBy = $request->sort_by ?? 'created_at';
    $sortOrder = $request->sort_order ?? 'desc';
    $query->orderBy($sortBy, $sortOrder);

    $logs = $query->paginate($request->per_page ?? 15);

    $logs->getCollection()->transform(function($log) {
        $biodata = \App\Models\Biodata::withTrashed()->where('biodata_no', $log->biodata_no)->first();
        $log->biodata_id = $biodata ? $biodata->id : null;
        return $log;
    });

    return response()->json(['success' => true, 'data' => $logs]);
}

// ─── 🔴 তার প্রোফাইল যারা আনলক করেছে (ডিলিট হওয়া বায়োডাটা সার্চ ফিক্স) ───
    public function getUserBiodataUnlockedBy(Request $request, $id) {
        $user = User::with('biodata')->findOrFail($id);
        if (!$user->biodata) return response()->json(['success' => true, 'data' => [], 'total' => 0]);

        $query = \App\Models\PurchasedBiodata::with([
            'user:id,name,email,mobile',
            'user.biodata' => fn($q) => $q->withTrashed()->select('id', 'user_id', 'biodata_no', 'status', 'deleted_at')
        ])
        ->where('biodata_id', $user->biodata->id);

        $query->when($request->start_date, fn($q) => $q->whereDate('created_at', '>=', $request->start_date));
        $query->when($request->end_date, fn($q) => $q->whereDate('created_at', '<=', $request->end_date));

        // 🔴 সার্চ লজিকে withTrashed() যুক্ত করা হয়েছে
        $query->when($request->search, function ($q) use ($request) {
            $search = $request->search;
            return $q->whereHas('user', function($u) use ($search) {
                $u->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('mobile', 'LIKE', "%{$search}%")
                  ->orWhere('id', 'LIKE', "%{$search}%")
                  ->orWhereHas('biodata', fn($b) => $b->withTrashed()->where('biodata_no', 'LIKE', "%{$search}%")); // 👈 ফিক্সড
            });
        });

        $data = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 10);
        return response()->json(['success' => true, 'data' => $data, 'total' => $data->total()]);
    }

    // ─── 🔴 ১. প্রাইভেট অ্যাডমিন নোট সেভ করা ───
    public function updateAdminNote(Request $request, $id) {
        $user = User::findOrFail($id);
        $user->admin_note = $request->admin_note;
        $user->save();
        return response()->json(['success' => true, 'message' => 'অ্যাডমিন নোট সেভ করা হয়েছে!']);
    }

  // ─── 🔴 ২. ইমপার্সোনেট (লগইন অ্যাজ ইউজার) ───
    public function impersonateUser($id) {
        $user = User::findOrFail($id);
        $token = $user->createToken('impersonation_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user, // 👈 নতুন টোকেনের সাথে ইউজারের ইনফোও পাঠানো হলো
            'message' => 'সফলভাবে লগইন হয়েছে'
        ]);
    }

    // ─── 🔴 ৩. লগইন / আইপি হিস্ট্রি (ডেট ফিল্টার সহ) ───
    public function getUserLoginHistory(Request $request, $id) {
        if (class_exists(\App\Models\LoginHistory::class)) {
            $query = \App\Models\LoginHistory::where('user_id', $id);

            // 🔴 ডেট ফিল্টার যুক্ত করা হয়েছে
            $query->when($request->start_date, fn($q) => $q->whereDate('created_at', '>=', $request->start_date));
            $query->when($request->end_date, fn($q) => $q->whereDate('created_at', '<=', $request->end_date));

            $query->when($request->search, function($q) use ($request) {
                $search = $request->search;
                $q->where(function($sub) use ($search) {
                    $sub->where('ip_address', 'LIKE', "%{$search}%")
                      ->orWhere('user_agent', 'LIKE', "%{$search}%");
                });
            });

            $data = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 10);
        } else {
            $data = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);
        }

        return response()->json(['success' => true, 'data' => $data, 'total' => $data->total()]);
    }

    public function exportUsers(Request $request) { /* Your existing export logic */ }
}
