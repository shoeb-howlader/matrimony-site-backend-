<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Notifications\UserAlertNotification;
use App\Models\UserRestrictionLog;
use App\Events\NotificationSent;
use Illuminate\Support\Str;
use App\Jobs\SendGlobalNotificationJob;

class AdminUserController extends Controller
{
   public function getUsers(Request $request)
    {
        // 🔴 এখানে 'users.restriction_expires_at' এবং 'users.restriction_reason' যোগ করা হয়েছে
        $query = User::select(
            'users.id',
            'users.name',
            'users.email',
            'users.mobile',
            'users.role',
            'users.status',
            'users.created_at',
            'users.restriction_expires_at', // 👈 নতুন কলাম
            'users.restriction_reason'      // 👈 নতুন কলাম
        )
        ->with(['biodata' => function($q) {
            $q->withTrashed()->select('id', 'user_id', 'biodata_no', 'status', 'deleted_at');
        }]);

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
                             $b->withTrashed()->where(function($bSub) use ($search) {
                                 $bSub->where('biodata_no', 'LIKE', "%{$search}%")
                                      ->orWhere('name', 'LIKE', "%{$search}%")
                                      ->orWhere('candidate_mobile_number', 'LIKE', "%{$search}%")
                                      ->orWhere('guardian_mobile', 'LIKE', "%{$search}%");
                             });
                         });
            });
        });

        $query->when($request->role && strtolower($request->role) !== 'all', fn ($q) => $q->where('users.role', $request->role));

        $query->when($request->status && strtolower($request->status) !== 'all', function ($q) use ($request) {
            if ($request->status === 'restricted') {
                return $q->whereNotNull('users.restriction_expires_at')
                         ->where('users.restriction_expires_at', '>', now());
            } else {
                return $q->where('users.status', $request->status);
            }
        });

        $query->when($request->has_biodata && $request->has_biodata !== 'all', function ($q) use ($request) {
            return $request->has_biodata === 'yes' ? $q->whereHas('biodata', fn($b) => $b->withTrashed()) : $q->whereDoesntHave('biodata', fn($b) => $b->withTrashed());
        });

        $query->when($request->biodata_status && strtolower($request->biodata_status) !== 'all', function ($q) use ($request) {
            if ($request->biodata_status === 'deleted') {
                return $q->whereHas('biodata', fn($b) => $b->onlyTrashed());
            } else {
                return $q->whereHas('biodata', fn($b) => $b->where('status', $request->biodata_status)->whereNull('deleted_at'));
            }
        });

        $sortBy = $request->sort_by ?? 'created_at';
        $sortDir = $request->sort_dir ?? 'desc';

        if ($sortBy === 'biodata_no') {
            $query->orderBy(\App\Models\Biodata::select('biodata_no')->withTrashed()->whereColumn('biodatas.user_id', 'users.id'), $sortDir);
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

   public function changeStatus(Request $request, $id)
    {
        // 🔴 ইউজারের সাথে তার বায়োডাটাও লোড করে নেওয়া হলো
        $user = User::with('biodata')->findOrFail($id);

        $newStatus = $request->status;
        $user->status = $newStatus;
        $user->save();

        // ── 🔴 যদি ইউজারকে ব্যান করা হয় ──
        if ($newStatus === 'banned') {

            // ১. ইউজারের বায়োডাটা থাকলে তা সাথে সাথে হাইড (লুকিয়ে) ফেলা
            if ($user->biodata) {
                $user->biodata->is_hidden = 1;
                $user->biodata->save();
            }

            // ২. ইউজারের সমস্ত লগইন সেশন (টোকেন) ডিলিট করে দেওয়া
            $user->tokens()->delete();

            // ৩. Reverb-এর মাধ্যমে ফ্রন্টএন্ডে রিয়েল-টাইম 'Kickout' সিগন্যাল পাঠানো
            try {
                $notificationObj = [
                    'id' => \Illuminate\Support\Str::uuid()->toString(),
                    'type' => 'BANNED_KICKOUT', // স্পেশাল টাইপ যা ফ্রন্টএন্ড ধরবে
                    'notifiable_type' => 'App\\Models\\User',
                    'notifiable_id' => $user->id,
                    'data' => [
                        'message' => 'আপনার অ্যাকাউন্টটি স্থায়ীভাবে ব্যান করা হয়েছে।'
                    ],
                    'read_at' => null,
                    'created_at' => now()->toISOString(),
                    'updated_at' => now()->toISOString(),
                ];

                event(new \App\Events\NotificationSent($user, $notificationObj));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Ban Kickout Error: ' . $e->getMessage());
            }
        }

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
   // ─── 🔴 ২. ইউজারের বিস্তারিত এবং রেস্ট্রিকশন হিস্ট্রি পাঠানো ───
    public function getUserDetails($id)
    {
        // 🔴 restrictionLogs রিলেশনটি কল করা হলো
        $user = User::with(['biodata' => fn($q) => $q->withTrashed(), 'restrictionLogs'])->findOrFail($id);
        $data = $user->toArray();

        // Total Spent & Other Stats
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

        // Fake Delete Checker
        $recentDeletesCount = \App\Models\BiodataDeletionLog::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $data['recent_delete_count'] = $recentDeletesCount;
        $data['is_suspicious'] = $recentDeletesCount > 1;

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

        // 🔴 রেস্ট্রিকশন লগের ডাটা অ্যারেতে যুক্ত করা হলো
        $data['restriction_logs'] = $user->restrictionLogs;

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

        // স্ট্যাটাস টগল করা
        $user->biodata->is_hidden = !$user->biodata->is_hidden;
        $user->biodata->save();

        $statusText = $user->biodata->is_hidden ? 'হাইড' : 'লাইভ';
        $messageText = $user->biodata->is_hidden
            ? 'আপনার বায়োডাটা অ্যাডমিন কর্তৃক হাইড (লুকায়িত) করা হয়েছে।'
            : 'আপনার বায়োডাটা অ্যাডমিন কর্তৃক পুনরায় লাইভ (পাবলিক) করা হয়েছে।';

        // 📩 ১. ইউজারকে ডাটাবেস নোটিফিকেশন পাঠানো
        $user->notify(new \App\Notifications\UserAlertNotification(
            "বায়োডাটা {$statusText}",
            $messageText,
            '/user/dashboard'
        ));

        // 🔴 ২. Reverb রিয়েল-টাইম ইভেন্ট ফায়ার করা
        try {
            $notificationData = [
                'title' => "বায়োডাটা {$statusText}",
                'message' => $messageText,
                'link' => '/user/dashboard'
            ];

            $notificationObj = [
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'type' => 'App\\Notifications\\UserAlertNotification',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $user->id,
                'data' => $notificationData,
                'read_at' => null,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];

            event(new \App\Events\NotificationSent($user, $notificationObj));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Visibility Toggle Realtime Notification Error: ' . $e->getMessage());
        }

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

// ─── 🔴 ১. ইউজারকে রেস্ট্রিক্ট করার ফাংশন (বায়োডাটা হাইড লজিক সহ) ───
    public function restrictUser(Request $request, $id) {
        $request->validate([
            'days' => 'required|integer',
            'reason' => 'nullable|string|max:1000'
        ]);

        // 🔴 ইউজারের সাথে তার বায়োডাটাও লোড করা হলো
        $user = User::with('biodata')->findOrFail($id);

        $days = (int) $request->days;
        $expiresAt = now()->addDays($days);

        // বর্তমান রেস্ট্রিকশনের মেয়াদ এবং কারণ আপডেট করা হচ্ছে
        $user->restriction_expires_at = $expiresAt;
        $user->restriction_reason = $request->reason;
        $user->save();

        // 🔴 ৩. ইউজারের যদি বায়োডাটা থাকে, তবে সেটি সাথে সাথে হাইড করে দেওয়া হলো
        if ($user->biodata) {
            $user->biodata->is_hidden = 1;
            $user->biodata->save();
        }

        // রেস্ট্রিকশন হিস্ট্রি (Log) ডাটাবেসে সেভ করা হচ্ছে
        \App\Models\UserRestrictionLog::create([
            'user_id'         => $user->id,
            'restricted_days' => $days,
            'reason'          => $request->reason ?? 'কোনো কারণ উল্লেখ করা হয়নি',
            'expires_at'      => $expiresAt,
        ]);

        $reasonText = $request->reason ?? 'কমিউনিটি গাইডলাইন ভঙ্গ';

        // 📩 ১. ডাটাবেস নোটিফিকেশন পাঠানো
        $user->notify(new \App\Notifications\UserAlertNotification(
            'অ্যাকাউন্ট রেস্ট্রিক্টেড!',
            "আপনার অ্যাকাউন্ট সাময়িকভাবে রেস্ট্রিক্ট করা হয়েছে। কারণ: {$reasonText}",
            '/user/dashboard'
        ));

        // 🔴 ২. রিয়েল-টাইম নোটিফিকেশন (Reverb) ফায়ার করা
        try {
            $notificationData = [
                'title' => 'অ্যাকাউন্ট রেস্ট্রিক্টেড!',
                'message' => "আপনার অ্যাকাউন্ট সাময়িকভাবে রেস্ট্রিক্ট করা হয়েছে। কারণ: {$reasonText}",
                'link' => '/user/dashboard'
            ];

            $notificationObj = [
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'type' => 'App\\Notifications\\UserAlertNotification',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $user->id,
                'data' => $notificationData,
                'read_at' => null,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];

            event(new \App\Events\NotificationSent($user, $notificationObj));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Restriction Realtime Notification Error: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'ইউজারকে সফলভাবে রেস্ট্রিক্ট করা হয়েছে এবং তার বায়োডাটা হাইড করা হয়েছে।']);
    }


   // ─── 🔴 ২. রেস্ট্রিকশন ম্যানুয়ালি তুলে নেওয়ার ফাংশন (রিয়েল-টাইম নোটিফিকেশন সহ) ───
    public function removeRestriction($id)
    {
        $user = User::findOrFail($id);

        // রেস্ট্রিকশনের মেয়াদ এবং কারণ মুছে ফেলা হচ্ছে
        $user->restriction_expires_at = null;
        $user->restriction_reason = null;
        $user->save();

        // লগ (History) টেবিলেও বর্তমান মেয়াদ শেষ করে দেওয়া হচ্ছে
        $latestLog = \App\Models\UserRestrictionLog::where('user_id', $user->id)->latest()->first();
        if ($latestLog) {
            $latestLog->expires_at = now(); // মেয়াদ ম্যানুয়ালি আজকে শেষ করে দেওয়া হলো
            $latestLog->save();
        }

        $notificationData = [
            'title' => 'অ্যাকাউন্ট রেস্ট্রিকশন বাতিল',
            'message' => 'আপনার অ্যাকাউন্টের ওপর থাকা রেস্ট্রিকশন অ্যাডমিন কর্তৃক তুলে নেওয়া হয়েছে। আপনি এখন আপনার অ্যাকাউন্টটি স্বাভাবিকভাবে ব্যবহার করতে পারবেন।',
            'link' => '/user/dashboard'
        ];

        // 📩 ১. ডাটাবেস নোটিফিকেশন পাঠানো
        $user->notify(new \App\Notifications\UserAlertNotification(
            $notificationData['title'],
            $notificationData['message'],
            $notificationData['link']
        ));

        // 🔴 ২. রিয়েল-টাইম নোটিফিকেশন (Reverb) ফায়ার করা 🔴
        try {
            $notificationObj = [
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'type' => 'App\\Notifications\\UserAlertNotification',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $user->id,
                'data' => $notificationData,
                'read_at' => null,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];

            event(new \App\Events\NotificationSent($user, $notificationObj));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Remove Restriction Realtime Notification Error: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'অ্যাকাউন্টের রেস্ট্রিকশন তুলে নেওয়া হয়েছে এবং নোটিফিকেশন পাঠানো হয়েছে!']);
    }

    public function sendGlobalNotification(Request $request)
    {
        // ডাটা ভ্যালিডেশন
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'link' => 'nullable|string'
        ]);

        $title = $request->title;
        $message = $request->message;
        $link = $request->link ?? '/user/dashboard';

        // 🔴 Job টি Queue-তে পাঠিয়ে দেওয়া হলো (সার্ভার সাথে সাথে রেসপন্স দিয়ে দেবে)
        SendGlobalNotificationJob::dispatch($title, $message, $link);

        return response()->json([
            'success' => true,
            'message' => 'সকল ইউজারকে নোটিফিকেশন পাঠানোর প্রক্রিয়া শুরু হয়েছে!'
        ]);
    }

    // ১. শুধুমাত্র অ্যাডমিন এবং মডারেটরদের লিস্ট
   // ১. অ্যাডমিন এবং মডারেটরদের লিস্ট (সুপার অ্যাডমিন সহ)
    public function getAdmins()
    {
        $admins = User::whereIn('role', ['super_admin', 'admin', 'moderator'])
            ->orderBy('role', 'desc') // সুপার অ্যাডমিনকে সবার উপরে দেখানোর জন্য
            ->get();

        return response()->json(['success' => true, 'data' => $admins]);
    }

    // ২. রোল আপডেট (ইউজারকে অ্যাডমিন বানানো বা অ্যাডমিন রিমুভ করা)
  public function updateUserRole(Request $request, $id)
    {
        $request->validate([
            'role' => 'required|in:user,admin,moderator'
        ]);

        $targetUser = User::findOrFail($id);
        $currentUser = auth()->user();

        // 🔴 সিকিউরিটি ১: কেউ সুপার অ্যাডমিনকে এডিট করতে পারবে না
        if ($targetUser->role === 'super_admin') {
            return response()->json(['success' => false, 'message' => 'সুপার অ্যাডমিনের রোল পরিবর্তন করা সম্ভব নয়!'], 403);
        }

        // 🔴 সিকিউরিটি ২: শুধুমাত্র সুপার অ্যাডমিনই নতুন কাউকে 'admin' বানাতে পারবে
        if ($request->role === 'admin' && $currentUser->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'অ্যাডমিন তৈরি করার ক্ষমতা শুধুমাত্র সুপার অ্যাডমিনের আছে!'], 403);
        }

        $oldRole = $targetUser->role;
        $targetUser->role = $request->role;
        $targetUser->save();

        // ─── 🔴 সিকিউরিটি ৩: অফলাইন/অনলাইন কন্ট্রোল লজিক ───

        // যদি কাউকে অ্যাডমিন প্যানেল থেকে বের করে সাধারণ ইউজার (user) বানানো হয়
        if ($request->role === 'user' && in_array($oldRole, ['admin', 'moderator'])) {
            // তার সব লগইন সেশন ডিলিট করে দিন (অফলাইন সিকিউরিটির জন্য)
            $targetUser->tokens()->delete();
            $actionType = 'ROLE_DEMOTED';
            $message = 'আপনার অ্যাডমিন অ্যাক্সেস বাতিল করা হয়েছে। নিরাপত্তার স্বার্থে আপনাকে লগআউট করা হচ্ছে।';
        } else {
            // প্রমোশন পেলে বা এক অ্যাডমিন থেকে অন্য অ্যাডমিনে গেলে টোকেন ডিলিট করার দরকার নেই
            $actionType = 'ROLE_UPDATED';
            $message = "আপনার অ্যাকাউন্টের রোল পরিবর্তন করে '{$request->role}' করা হয়েছে।";
        }

        // ─── 🔴 ৪. Reverb রিয়েল-টাইম ইভেন্ট ফায়ার করা ───
        try {
            $notificationObj = [
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'type' => $actionType, // ফ্রন্টএন্ড এই টাইপটি ধরে কাজ করবে
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $targetUser->id,
                'data' => [
                    'message' => $message,
                    'new_role' => $request->role
                ],
                'read_at' => null,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];

            // আপনার তৈরি করা গ্লোবাল ইভেন্ট ব্যবহার করা হলো
            event(new \App\Events\NotificationSent($targetUser, $notificationObj));

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Role Update Realtime Error: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => "রোল সফলভাবে '{$request->role}' এ পরিবর্তন করা হয়েছে।"
        ]);
    }

    // ─── ২. সাধারণ ইউজারকে অ্যাডমিন/মডারেটর হিসেবে প্রমোশন দেওয়া ───
   public function promoteUser(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'required|in:admin,moderator'
        ], [
            'email.exists' => 'এই ইমেইল দিয়ে কোনো ইউজার সিস্টেমে নেই!'
        ]);

        $currentUser = auth()->user();

        if ($request->role === 'admin' && $currentUser->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'অ্যাডমিন তৈরি করার ক্ষমতা শুধুমাত্র সুপার অ্যাডমিনের আছে!'], 403);
        }

        $user = User::where('email', $request->email)->first();

        if (in_array($user->role, ['admin', 'moderator', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'এই ইউজার ইতিমধ্যেই স্টাফ হিসেবে যুক্ত আছেন!'], 400);
        }

        // রোল আপডেট করা হলো
        $user->role = $request->role;
        $user->save();

        // ─── 🔴 রিয়েল-টাইম ইভেন্ট ফায়ার করা (প্রমোশনের জন্য) ───
        try {
            $notificationObj = [
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'type' => 'ROLE_PROMOTED', // নতুন টাইপ
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $user->id,
                'data' => [
                    'message' => "আপনাকে সিস্টেমে '{$request->role}' হিসেবে প্রমোশন দেওয়া হয়েছে!",
                    'new_role' => $request->role
                ],
                'read_at' => null,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];

            event(new \App\Events\NotificationSent($user, $notificationObj));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Promotion Realtime Error: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => "{$user->name}-কে সফলভাবে {$request->role} হিসেবে যুক্ত করা হয়েছে!"
        ]);
    }
}
