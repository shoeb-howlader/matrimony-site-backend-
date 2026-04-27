<?php

namespace App\Http\Controllers;

use App\Models\Biodata;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Models\PurchasedBiodata;
use App\Models\User;



class BiodataController extends Controller
{
  /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $cardFields = [
                'id', 'user_id', 'biodata_no', 'type', 'date_of_birth',
                'height_inches', 'skin_tone', 'occupation_category',
                'present_district_id', 'status', 'permanent_district_id',
                'marital_status', 'is_verified'
            ];

            $query = Biodata::query()
                ->select($cardFields)
                ->with(['presentDistrict:id,bn_name', 'permanentDistrict:id,bn_name'])
                ->where('status', 'approved')
                ->where('is_hidden', false);

                // 🔴 নতুন: বায়োডাটা নাম্বার দিয়ে সার্চ
    if ($request->filled('biodata_no')) {
        // exact match করতে চাইলে:
       // $query->where('biodata_no', $request->biodata_no);

        // অথবা আংশিক ম্যাচ (LIKE) করতে চাইলে:
         $query->where('biodata_no', 'LIKE', '%' . $request->biodata_no . '%');
    }
            // 1. Type
            if ($request->filled('biodataType') && $request->biodataType !== 'all') {
                $query->where('type', $request->biodataType);
            }

            // 2. Marital Status
            if ($request->filled('maritalStatus') && $request->maritalStatus !== 'all') {
                $query->where('marital_status', $request->maritalStatus);
            }

            // 3. Location Filters (Present)
            if ($request->filled('present_division_id')) $query->where('present_division_id', $request->present_division_id);
            if ($request->filled('present_district_id')) $query->where('present_district_id', $request->present_district_id);
            if ($request->filled('present_upazila_id'))  $query->where('present_upazila_id', $request->present_upazila_id);
            if ($request->filled('present_union_id'))    $query->where('present_union_id', $request->present_union_id);

            // 4. Location Filters (Permanent)
            if ($request->filled('permanent_division_id')) $query->where('permanent_division_id', $request->permanent_division_id);
            if ($request->filled('permanent_district_id')) $query->where('permanent_district_id', $request->permanent_district_id);
            if ($request->filled('permanent_upazila_id'))  $query->where('permanent_upazila_id', $request->permanent_upazila_id);
            if ($request->filled('permanent_union_id'))    $query->where('permanent_union_id', $request->permanent_union_id);

            // 5. Age Range
            if ($request->has('age_range')) {
                $range = is_array($request->age_range) ? $request->age_range : explode(',', $request->age_range);
                if (count($range) === 2) {
                    $minAge = (int)$range[0];
                    $maxAge = (int)$range[1];
                    $dateMax = now()->subYears($minAge)->format('Y-m-d');
                    $dateMin = now()->subYears($maxAge + 1)->addDay()->format('Y-m-d');
                    $query->whereBetween('date_of_birth', [$dateMin, $dateMax]);
                }
            }

            // 6. Height Range
            if ($request->has('height_range')) {
                $range = is_array($request->height_range) ? $request->height_range : explode(',', $request->height_range);
                if (count($range) === 2) {
                    $query->whereBetween('height_inches', [(int)$range[0], (int)$range[1]]);
                }
            }

            // 7. Mazhab
            if ($request->filled('mazhab')) {
                $query->where('mazhab', $request->mazhab);
            }

            // 8. Complexion (Skin Tone)
            if ($request->filled('complexion')) {
                $tones = is_string($request->complexion) ? explode(',', $request->complexion) : $request->complexion;
                $query->whereIn('skin_tone', $tones);
            }

            // 9. Occupation Category (পেশা)
            if ($request->filled('occupation_category')) {
                $cats = is_string($request->occupation_category) ? explode(',', $request->occupation_category) : $request->occupation_category;
                $query->whereIn('occupation_category', $cats);
            }

            // 10. Education Medium
            if ($request->filled('edu_medium')) {
                $mediums = is_string($request->edu_medium) ? explode(',', $request->edu_medium) : $request->edu_medium;
                $query->whereIn('edu_medium', $mediums);
            }

            // 11. Deeni Titles
            if ($request->filled('edu_deeni_titles')) {
                $titles = is_string($request->edu_deeni_titles) ? explode(',', $request->edu_deeni_titles) : $request->edu_deeni_titles;
                $query->where(function ($q) use ($titles) {
                    foreach ($titles as $title) {
                        $q->orWhere('edu_deeni_titles', 'LIKE', '%' . $title . '%');
                    }
                });
            }

            // 12. Special Categories
            if ($request->filled('special_category')) {
                $categories = is_string($request->special_category) ? explode(',', $request->special_category) : $request->special_category;
                $query->where(function ($q) use ($categories) {
                    foreach ($categories as $cat) {
                        $q->orWhere('special_category', 'LIKE', '%' . $cat . '%');
                    }
                });
            }

            // 13. Family Financial Status
            if ($request->filled('family_financial_status')) {
                $financialStatuses = is_string($request->family_financial_status) ? explode(',', $request->family_financial_status) : $request->family_financial_status;
                $query->whereIn('family_financial_status', $financialStatuses);
            }
            // 🔴 যদি ইউজার লগইন করা থাকে, তবে তার ইগনোর করা আইডিগুলো বাদ দিন
    if (Auth::guard('sanctum')->check()) {
        $userId = Auth::guard('sanctum')->id();
        $ignoredIds = Ignore::where('user_id', $userId)->pluck('biodata_id');

        if ($ignoredIds->isNotEmpty()) {
            $query->whereNotIn('id', $ignoredIds);
        }
    }
            // ════════ 🔴 ৩. নতুন সর্টিং লজিক (Sorting) 🔴 ════════
    $sort = $request->input('sort', 'newest'); // ডিফল্ট হিসেবে 'newest' ধরবে

    if ($sort === 'age_asc') {
        // বয়স (কম থেকে বেশি): জন্মতারিখ নতুন থেকে পুরানো
        $query->orderBy('date_of_birth', 'desc');
    } elseif ($sort === 'age_desc') {
        // বয়স (বেশি থেকে কম): জন্মতারিখ পুরানো থেকে নতুন
        $query->orderBy('date_of_birth', 'asc');
    } else {
        // newest বা ডিফল্ট: নতুন বায়োডাটা আগে দেখাবে
        $query->latest(); // এটি orderBy('created_at', 'desc') এর কাজ করে
    }
        // ════════ 🔴 সম্পূর্ণ সর্টিং লজিক 🔴 ════════
    $sort = $request->input('sort', 'newest');

    switch ($sort) {
        case 'updated':
            // সম্প্রতি আপডেট করা প্রোফাইল
            $query->orderBy('updated_at', 'desc');
            break;

        case 'popular':
            // সবচেয়ে জনপ্রিয় (যেহেতু আমরা biodata_views টেবিল বানিয়েছি, তাই withCount ব্যবহার করবো)
            // *নোট: এর জন্য Biodata মডেলে views() নামে একটি relationship থাকতে হবে
            $query->withCount('views')->orderBy('views_count', 'desc');
            break;

        case 'education':
            // শিক্ষাগত যোগ্যতা (উচ্চ থেকে নিম্ন) - কাস্টম SQL Ranking
            // আপনার ডাটাবেসের ভ্যালু অনুযায়ী নামগুলো মিলিয়ে নিবেন
            $query->orderByRaw("CASE
                WHEN edu_highest_qual LIKE '%মাস্টার্স%' OR edu_highest_qual LIKE '%দাওরায়ে হাদিস%' THEN 1
                WHEN edu_highest_qual LIKE '%স্নাতক%' OR edu_highest_qual LIKE '%ফাযিল%' THEN 2
                WHEN edu_highest_qual LIKE '%এইচ.এস.সি%' OR edu_highest_qual LIKE '%আলিম%' THEN 3
                WHEN edu_highest_qual LIKE '%এস.এস.সি%' OR edu_highest_qual LIKE '%দাখিল%' THEN 4
                ELSE 5 END ASC"
            );
            break;

        case 'age_asc':
            // বয়স কম থেকে বেশি (জন্মতারিখ নতুন থেকে পুরানো)
            $query->orderBy('date_of_birth', 'desc');
            break;

        case 'age_desc':
            // বয়স বেশি থেকে কম (জন্মতারিখ পুরানো থেকে নতুন)
            $query->orderBy('date_of_birth', 'asc');
            break;

        case 'height_asc':
            // উচ্চতা খাটো থেকে লম্বা
            $query->orderBy('height_inches', 'asc');
            break;

        case 'height_desc':
            // উচ্চতা লম্বা থেকে খাটো
            $query->orderBy('height_inches', 'desc');
            break;

        case 'newest':
        default:
            // নতুন বায়োডাটা (ডিফল্ট)
            $query->latest();
            break;
    }

            return response()->json($query->latest()->paginate(12));

        } catch (\Exception $e) {
            return response()->json(['error' => 'Server Error', 'message' => $e->getMessage()], 500);
        }
    }

  /**
     * 🔴 Helper Method for Dynamic Status Update
     */
    private function getUpdatedStatus($currentStatus)
    {
        if ($currentStatus === 'approved' || $currentStatus === 'pending') {
            return 'edited';
        }
        return $currentStatus;
    }

    /**
     * Save Step 1 (General Information)
     */
    public function saveStep1(Request $request)
    {
        $validatedData = $request->validate([
            'biodataType'   => 'required|string|in:Male,Female,male,female',
            'maritalStatus' => 'required|string',
            'birthDay'      => 'required|string|size:2',
            'birthMonth'    => 'required|string|size:2',
            'birthYear'     => 'required|string|size:4',
            'height'        => 'required|integer|min:48|max:96',
            'complexion'    => 'required|string',
            'weight'        => 'required|integer|min:30|max:150',
            'bloodGroup'    => 'required|string',
            'nationality'   => 'required|string',
        ]);

        $user = Auth::user();
        $formattedBirthDate = $validatedData['birthYear'] . '-' . $validatedData['birthMonth'] . '-' . $validatedData['birthDay'];
        $type = ucfirst(strtolower($validatedData['biodataType']));

        $biodataExists = Biodata::where('user_id', $user->id)->first();
        $currentStatus = $biodataExists ? $this->getUpdatedStatus($biodataExists->status) : 'incomplete';

        $biodata = Biodata::updateOrCreate(
            ['user_id' => $user->id],
            [
                'type'           => $type,
                'marital_status' => $validatedData['maritalStatus'],
                'date_of_birth'  => $formattedBirthDate,
                'height_inches'  => $validatedData['height'],
                'skin_tone'      => $validatedData['complexion'],
                'weight'         => $validatedData['weight'],
                'blood_group'    => $validatedData['bloodGroup'],
                'nationality'    => $validatedData['nationality'],
                'current_step'   => DB::raw('GREATEST(current_step, 2)'),
                'status'         => $currentStatus
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Step 1 saved successfully',
            'current_step' => $biodata->current_step
        ], 200);
    }

    /**
     * Save Step 2 (Address Information)
     */
    public function saveStep2(Request $request)
    {
        $validatedData = $request->validate([
            'permanent_country'      => 'required|string',
            'permanent_home_details' => 'required|string|max:255',
            'permanent_division_id'  => 'nullable|required_if:permanent_country,বাংলাদেশ',
            'permanent_district_id'  => 'nullable|required_if:permanent_country,বাংলাদেশ',
            'permanent_upazila_id'   => 'nullable|required_if:permanent_country,বাংলাদেশ',
            'permanent_union_id'     => 'nullable|required_if:permanent_country,বাংলাদেশ',
            'present_country'        => 'required|string',
            'present_home_details'   => 'required|string|max:255',
            'present_division_id'    => 'nullable|required_if:present_country,বাংলাদেশ',
            'present_district_id'    => 'nullable|required_if:present_country,বাংলাদেশ',
            'present_upazila_id'     => 'nullable|required_if:present_country,বাংলাদেশ',
            'present_union_id'       => 'nullable|required_if:present_country,বাংলাদেশ',
            'grew_up_details'        => 'required|string|max:255',
        ]);

        $user = Auth::user();
        $biodata = Biodata::where('user_id', $user->id)->first();

        if (!$biodata) {
            return response()->json(['success' => false, 'message' => 'প্রথমে সাধারণ তথ্য পূরণ করুন।'], 400);
        }

        $biodata->update([
            'permanent_country'      => $validatedData['permanent_country'],
            'permanent_home_details' => $validatedData['permanent_home_details'],
            'permanent_division_id'  => $validatedData['permanent_country'] === 'বাংলাদেশ' ? $validatedData['permanent_division_id'] : null,
            'permanent_district_id'  => $validatedData['permanent_country'] === 'বাংলাদেশ' ? $validatedData['permanent_district_id'] : null,
            'permanent_upazila_id'   => $validatedData['permanent_country'] === 'বাংলাদেশ' ? $validatedData['permanent_upazila_id'] : null,
            'permanent_union_id'     => $validatedData['permanent_country'] === 'বাংলাদেশ' ? $validatedData['permanent_union_id'] : null,

            'present_country'        => $validatedData['present_country'],
            'present_home_details'   => $validatedData['present_home_details'],
            'present_division_id'    => $validatedData['present_country'] === 'বাংলাদেশ' ? $validatedData['present_division_id'] : null,
            'present_district_id'    => $validatedData['present_country'] === 'বাংলাদেশ' ? $validatedData['present_district_id'] : null,
            'present_upazila_id'     => $validatedData['present_country'] === 'বাংলাদেশ' ? $validatedData['present_upazila_id'] : null,
            'present_union_id'       => $validatedData['present_country'] === 'বাংলাদেশ' ? $validatedData['present_union_id'] : null,

            'grew_up_details'        => $validatedData['grew_up_details'],
            'current_step'           => DB::raw('GREATEST(current_step, 3)'),
            'status'                 => $this->getUpdatedStatus($biodata->status)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Step 2 saved successfully',
            'current_step' => $biodata->fresh()->current_step
        ], 200);
    }

    /**
     * Save Step 3 (Education Information)
     */
    public function saveStep3(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'edu_medium'               => 'required|string',
                'edu_highest_qual'         => 'required|string',
                'edu_below_ssc_class'      => 'nullable|string|max:255',
                'edu_ssc_year'             => 'nullable|string|max:255',
                'edu_ssc_group'            => 'nullable|string|max:255',
                'edu_ssc_result'           => 'nullable|string|max:255',
                'edu_hsc_ongoing_year'     => 'nullable|string|max:255',
                'edu_hsc_year'             => 'nullable|string|max:255',
                'edu_hsc_group'            => 'nullable|string|max:255',
                'edu_hsc_result'           => 'nullable|string|max:255',
                'edu_after_ssc_medium'     => 'nullable|string|max:255',
                'edu_diploma_subject'      => 'nullable|string|max:255',
                'edu_diploma_institute'    => 'nullable|string|max:255',
                'edu_diploma_ongoing_year' => 'nullable|string|max:255',
                'edu_diploma_year'         => 'nullable|string|max:255',
                'edu_bachelor_subject'     => 'nullable|string|max:255',
                'edu_bachelor_institute'   => 'nullable|string|max:255',
                'edu_bachelor_ongoing_year'=> 'nullable|string|max:255',
                'edu_bachelor_year'        => 'nullable|string|max:255',
                'edu_bachelor_result'      => 'nullable|string|max:255',
                'edu_master_subject'       => 'nullable|string|max:255',
                'edu_master_institute'     => 'nullable|string|max:255',
                'edu_master_year'          => 'nullable|string|max:255',
                'edu_master_result'        => 'nullable|string|max:255',
                'edu_doctorate_subject'    => 'nullable|string|max:255',
                'edu_doctorate_institute'  => 'nullable|string|max:255',
                'edu_doctorate_year'       => 'nullable|string|max:255',
                'edu_qawmi_madrasa'        => 'nullable|string|max:255',
                'edu_qawmi_year'           => 'nullable|string|max:255',
                'edu_qawmi_result'         => 'nullable|string|max:255',
                'edu_takmil_madrasa'       => 'nullable|string|max:255',
                'edu_takmil_year'          => 'nullable|string|max:255',
                'edu_takmil_result'        => 'nullable|string|max:255',
                'edu_other_details'        => 'nullable|string',
                'edu_deeni_titles'         => 'nullable|array',
            ]);

            $user = Auth::user();
            $biodata = Biodata::where('user_id', $user->id)->first();

            if (!$biodata) return response()->json(['success' => false, 'message' => 'প্রথমে সাধারণ তথ্য (Step 1) পূরণ করুন।'], 400);

            $biodata->update(array_merge($validatedData, [
                'current_step' => DB::raw('GREATEST(current_step, 4)'),
                'status'       => $this->getUpdatedStatus($biodata->status)
            ]));

            return response()->json(['success' => true, 'message' => 'Step 3 saved successfully', 'current_step' => $biodata->fresh()->current_step], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Save Step 4 (Family Information)
     */
    public function saveStep4(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'father_name'               => 'required|string|max:255',
                'father_status'             => 'required|string',
                'father_occupation'         => 'required|string|max:255',
                'mother_name'               => 'required|string|max:255',
                'mother_status'             => 'required|string',
                'mother_occupation'         => 'required|string|max:255',
                'brothers_count'            => 'required|string',
                'brothers_details'          => 'nullable|string',
                'sisters_count'             => 'required|string',
                'sisters_details'           => 'nullable|string',
                'uncles_details'            => 'nullable|string',
                'family_financial_status'   => 'required|string',
                'family_home_type'          => 'required|string|max:255',
                'family_asset_details'      => 'required|string',
                'family_islamic_details'    => 'required|string',
            ]);

            $user = Auth::user();
            $biodata = Biodata::where('user_id', $user->id)->first();

            if (!$biodata) return response()->json(['success' => false, 'message' => 'প্রথমে সাধারণ তথ্য পূরণ করুন।'], 400);

            if ($validatedData['brothers_count'] === '0') $validatedData['brothers_details'] = null;
            if ($validatedData['sisters_count'] === '0') $validatedData['sisters_details'] = null;

            $biodata->update(array_merge($validatedData, [
                'current_step' => DB::raw('GREATEST(current_step, 5)'),
                'status'       => $this->getUpdatedStatus($biodata->status)
            ]));

            return response()->json(['success' => true, 'message' => 'Step 4 saved successfully', 'current_step' => $biodata->fresh()->current_step], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Save Step 5 (Personal Information)
     */
    public function saveStep5(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'outdoor_dressup_details'      => 'required|string',
                'started_prayer_details'       => 'required|string',
                'missed_prayers'               => 'required|string',
                'mahram_nonmahram_details'     => 'required|string',
                'can_recite_quran_details'     => 'required|string',
                'mazhab'                       => 'required|string',
                'watch_movies_or_listen_songs' => 'required|string',
                'any_disease_details'          => 'required|string',
                'deen_effort'                  => 'required|string',
                'mazar_belief'                 => 'required|string',
                'islamic_books_read'           => 'required|string',
                'favorite_scholars'            => 'required|string',
                'hobby_and_wish_details'       => 'required|string',
                'candidate_mobile_number'      => 'required|string',
                'special_category'             => 'nullable|array',
                'niqab_details'                => 'nullable|string',
                'niqab_started_from'           => 'nullable|string',
                'from_when_kept_beard_details' => 'nullable|string',
                'wear_clothes_above_anckle'    => 'nullable|string',
                'photo'                        => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            ]);

            $user = Auth::user();
            $biodata = Biodata::where('user_id', $user->id)->first();

            if (!$biodata) return response()->json(['success' => false, 'message' => 'প্রথমে সাধারণ তথ্য পূরণ করুন।'], 400);

            if ($request->hasFile('photo')) {
                if ($biodata->candidate_photo) {
                    Storage::disk('public')->delete($biodata->candidate_photo);
                }
                $path = $request->file('photo')->store('candidate_photos', 'public');
                $validatedData['candidate_photo'] = $path;
            }

            if (isset($validatedData['special_category'])) {
                $validatedData['special_category'] = json_encode($validatedData['special_category'], JSON_UNESCAPED_UNICODE);
            }

            unset($validatedData['photo']);

            $biodata->update(array_merge($validatedData, [
                'current_step' => DB::raw('GREATEST(current_step, 6)'),
                'status'       => $this->getUpdatedStatus($biodata->status)
            ]));

            return response()->json(['success' => true, 'message' => 'Step 5 saved successfully', 'current_step' => $biodata->fresh()->current_step], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Save Step 6 (Occupational Information)
     */
    public function saveStep6(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'occupation_category' => 'required|string|max:255',
                'occupation_details'  => 'required|string',
                'monthly_income'      => 'nullable|string|max:255',
            ]);

            $user = Auth::user();
            $biodata = Biodata::where('user_id', $user->id)->first();

            if (!$biodata) return response()->json(['success' => false, 'message' => 'প্রথমে সাধারণ তথ্য পূরণ করুন।'], 400);

            $biodata->update(array_merge($validatedData, [
                'current_step' => DB::raw('GREATEST(current_step, 7)'),
                'status'       => $this->getUpdatedStatus($biodata->status)
            ]));

            return response()->json(['success' => true, 'message' => 'Step 6 saved successfully', 'current_step' => $biodata->fresh()->current_step], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Save Step 7 (Marriage Information)
     */
    public function saveStep7(Request $request)
    {
        try {
            $user = Auth::user();
            $biodata = Biodata::where('user_id', $user->id)->first();

            if (!$biodata) return response()->json(['success' => false, 'message' => 'প্রথমে সাধারণ তথ্য পূরণ করুন।'], 400);

            $rules = [
                'guardians_consent' => 'required|string|max:255',
                'view_on_marriage'  => 'required|string',
            ];

            if (strtolower($biodata->type) === 'male') {
                $rules['capable_of_keeping_wife_in_veil'] = 'required|string|max:255';
                $rules['allow_wife_to_study']             = 'required|string|max:255';
                $rules['allow_wifes_job']                 = 'required|string|max:255';
                $rules['where_live_after_marriage']       = 'required|string|max:255';
                $rules['want_dowry']                      = 'required|string|max:255';
            } else {
                $rules['want_to_work_after_marriage']     = 'required|string|max:255';
                $rules['want_to_study_after_marriage']    = 'required|string|max:255';
                $rules['continue_working_after_marriage'] = 'nullable|string|max:255';
            }

            $validatedData = $request->validate($rules);

            $biodata->update(array_merge($validatedData, [
                'current_step' => DB::raw('GREATEST(current_step, 8)'),
                'status'       => $this->getUpdatedStatus($biodata->status)
            ]));

            return response()->json(['success' => true, 'message' => 'Step 7 saved successfully', 'current_step' => $biodata->fresh()->current_step], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Save Step 8 (Expected Partner Information)
     */
    public function saveStep8(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'partner_min_age'             => 'required|integer',
                'partner_max_age'             => 'required|integer|gte:partner_min_age',
                'partner_complexion'          => 'required|array',
                'partner_min_height_inches'   => 'required|integer',
                'partner_max_height_inches'   => 'required|integer|gte:partner_min_height_inches',
                'partner_education_details'   => 'required|string',
                'partner_district'            => 'required|array',
                'partner_marital_status'      => 'required|array',
                'partner_occupation'          => 'required|string',
                'partner_financial_status'    => 'required|array',
                'partner_qualities_details'   => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
            }

            $validatedData = $validator->validated();
            $user = Auth::user();
            $biodata = Biodata::where('user_id', $user->id)->first();

            if (!$biodata) return response()->json(['success' => false, 'message' => 'প্রথমে সাধারণ তথ্য পূরণ করুন।'], 400);

            $fieldsToEncode = ['partner_complexion', 'partner_marital_status', 'partner_financial_status', 'partner_district'];
            foreach($fieldsToEncode as $field) {
                if (isset($validatedData[$field])) {
                    $validatedData[$field] = json_encode($validatedData[$field], JSON_UNESCAPED_UNICODE);
                }
            }

            $biodata->update(array_merge($validatedData, [
                'current_step' => DB::raw('GREATEST(current_step, 9)'),
                'status'       => $this->getUpdatedStatus($biodata->status)
            ]));

            return response()->json(['success' => true, 'message' => 'Step 8 saved successfully', 'current_step' => $biodata->fresh()->current_step], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Save Step 9 (Declaration)
     */
    public function saveStep9(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'parents_aware' => 'required|string',
                'is_truthful'   => 'required|boolean',
                'accept_terms'  => 'required|string',
            ]);

            $user = Auth::user();
            $biodata = Biodata::where('user_id', $user->id)->first();

            if (!$biodata) return response()->json(['success' => false, 'message' => 'প্রথমে সাধারণ তথ্য পূরণ করুন।'], 400);

            $biodata->update(array_merge($validatedData, [
                'current_step' => DB::raw('GREATEST(current_step, 10)'),
                'status'       => $this->getUpdatedStatus($biodata->status)
            ]));

            return response()->json(['success' => true, 'message' => 'Step 9 saved successfully', 'current_step' => $biodata->fresh()->current_step], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Save Step 10 (Contact Information & Decision)
     */
    public function saveStep10(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name'                  => 'required|string|max:255',
                'guardian_mobile'       => 'required|string|max:20',
                'guardian_relationship' => 'required|string|max:255',
                'contact_email'         => 'required|email|max:255',
                'submit_type'           => 'required|in:draft,pending' // মোডাল থেকে আসছে
            ]);

            $user = Auth::user();
            $biodata = Biodata::where('user_id', $user->id)->first();

            if (!$biodata) return response()->json(['success' => false, 'message' => 'প্রথমে সাধারণ তথ্য পূরণ করুন।'], 400);

            $biodata->name = $validatedData['name'];
            $biodata->guardian_mobile = $validatedData['guardian_mobile'];
            $biodata->guardian_relationship = $validatedData['guardian_relationship'];
            $biodata->contact_email = $validatedData['contact_email'];
            $biodata->current_step = 10;
            // 🔴 এখানে স্ট্যাটাস ইউজারের সিদ্ধান্ত অনুযায়ী সেভ হবে
            $biodata->status = $validatedData['submit_type'];
            $biodata->save();

            return response()->json([
                'success'     => true,
                'message'     => 'বায়োডাটা সফলভাবে সংরক্ষিত হয়েছে!',
                'submit_type' => $biodata->status
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Final Submit from Preview Page
     */
    public function submitFinal(Request $request)
    {
        try {
            $user = Auth::user();
            $biodata = Biodata::where('user_id', $user->id)->first();

            if (!$biodata) return response()->json(['success' => false, 'message' => 'বায়োডাটা পাওয়া যায়নি।'], 404);

            $biodata->status = 'pending';
            $biodata->save();

            return response()->json(['success' => true, 'message' => 'বায়োডাটা সফলভাবে সাবমিট হয়েছে!'], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Toggle Biodata Visibility (Hide/Unhide)
     */
    public function toggleVisibility(Request $request)
    {
        try {
            $biodata = Biodata::where('user_id', Auth::id())->first();
            if (!$biodata) return response()->json(['success' => false, 'message' => 'বায়োডাটা পাওয়া যায়নি।'], 404);

            $biodata->is_hidden = !$biodata->is_hidden;
            $biodata->save();

            $message = $biodata->is_hidden ? 'আপনার বায়োডাটা হাইড করা হয়েছে।' : 'আপনার বায়োডাটা পাবলিক করা হয়েছে।';

            return response()->json(['success' => true, 'message' => $message, 'is_hidden' => $biodata->is_hidden], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'সার্ভার এরর!'], 500);
        }
    }

    /**
     * Fetch the user's current draft
     */
    public function getDraft()
    {
        $biodata = Biodata::where('user_id', Auth::id())->first();

        if (!$biodata) {
            return response()->json(['data' => null], 200);
        }

        $birthDate = $biodata->date_of_birth ? Carbon::parse($biodata->date_of_birth) : null;

        return response()->json([
            'data' => [
                'status'        => $biodata->status,
                'biodata_no'    => $biodata->biodata_no,
                'is_hidden'     => (bool) $biodata->is_hidden,

                'biodataType'   => strtolower($biodata->type),
                'maritalStatus' => $biodata->marital_status,
                'birthDay'      => $birthDate ? $birthDate->format('d') : '',
                'birthMonth'    => $birthDate ? $birthDate->format('m') : '',
                'birthYear'     => $birthDate ? $birthDate->format('Y') : '',
                'height'        => (string) $biodata->height_inches,
                'complexion'    => $biodata->skin_tone,
                'weight'        => $biodata->weight,
                'bloodGroup'    => $biodata->blood_group,
                'nationality'   => $biodata->nationality ?? 'বাংলাদেশী',

                'permanent_country'      => $biodata->permanent_country ?? 'বাংলাদেশ',
                'permanent_division_id'  => $biodata->permanent_division_id,
                'permanent_district_id'  => $biodata->permanent_district_id,
                'permanent_upazila_id'   => $biodata->permanent_upazila_id,
                'permanent_union_id'     => $biodata->permanent_union_id,
                'permanent_home_details' => $biodata->permanent_home_details,

                'present_country'        => $biodata->present_country ?? 'বাংলাদেশ',
                'present_division_id'    => $biodata->present_division_id,
                'present_district_id'    => $biodata->present_district_id,
                'present_upazila_id'     => $biodata->present_upazila_id,
                'present_union_id'       => $biodata->present_union_id,
                'present_home_details'   => $biodata->present_home_details,

                'grew_up_details'        => $biodata->grew_up_details,

                'edu_medium'                 => $biodata->edu_medium,
                'edu_highest_qual'           => $biodata->edu_highest_qual,
                'edu_below_ssc_class'        => $biodata->edu_below_ssc_class,
                'edu_ssc_year'               => $biodata->edu_ssc_year,
                'edu_ssc_group'              => $biodata->edu_ssc_group,
                'edu_ssc_result'             => $biodata->edu_ssc_result,
                'edu_hsc_ongoing_year'       => $biodata->edu_hsc_ongoing_year,
                'edu_hsc_year'               => $biodata->edu_hsc_year,
                'edu_hsc_group'              => $biodata->edu_hsc_group,
                'edu_hsc_result'             => $biodata->edu_hsc_result,
                'edu_after_ssc_medium'       => $biodata->edu_after_ssc_medium,
                'edu_diploma_subject'        => $biodata->edu_diploma_subject,
                'edu_diploma_institute'      => $biodata->edu_diploma_institute,
                'edu_diploma_ongoing_year'   => $biodata->edu_diploma_ongoing_year,
                'edu_diploma_year'           => $biodata->edu_diploma_year,
                'edu_bachelor_subject'       => $biodata->edu_bachelor_subject,
                'edu_bachelor_institute'     => $biodata->edu_bachelor_institute,
                'edu_bachelor_ongoing_year'  => $biodata->edu_bachelor_ongoing_year,
                'edu_bachelor_year'          => $biodata->edu_bachelor_year,
                'edu_bachelor_result'        => $biodata->edu_bachelor_result,
                'edu_master_subject'         => $biodata->edu_master_subject,
                'edu_master_institute'       => $biodata->edu_master_institute,
                'edu_master_year'            => $biodata->edu_master_year,
                'edu_master_result'          => $biodata->edu_master_result,
                'edu_doctorate_subject'      => $biodata->edu_doctorate_subject,
                'edu_doctorate_institute'    => $biodata->edu_doctorate_institute,
                'edu_doctorate_year'         => $biodata->edu_doctorate_year,

                'edu_qawmi_madrasa'          => $biodata->edu_qawmi_madrasa,
                'edu_qawmi_year'             => $biodata->edu_qawmi_year,
                'edu_qawmi_result'           => $biodata->edu_qawmi_result,
                'edu_takmil_madrasa'         => $biodata->edu_takmil_madrasa,
                'edu_takmil_year'            => $biodata->edu_takmil_year,
                'edu_takmil_result'          => $biodata->edu_takmil_result,

                'edu_other_details'          => $biodata->edu_other_details,
                'edu_deeni_titles'           => $biodata->edu_deeni_titles ?? [],

                'father_name'                => $biodata->father_name,
                'father_status'              => $biodata->father_status,
                'father_occupation'          => $biodata->father_occupation,
                'mother_name'                => $biodata->mother_name,
                'mother_status'              => $biodata->mother_status,
                'mother_occupation'          => $biodata->mother_occupation,
                'brothers_count'             => $biodata->brothers_count !== null ? (string) $biodata->brothers_count : '',
                'brothers_details'           => $biodata->brothers_details,
                'sisters_count'              => $biodata->sisters_count !== null ? (string) $biodata->sisters_count : '',
                'sisters_details'            => $biodata->sisters_details,
                'uncles_details'             => $biodata->uncles_details,
                'family_financial_status'    => $biodata->family_financial_status,
                'family_home_type'           => $biodata->family_home_type,
                'family_asset_details'       => $biodata->family_asset_details,
                'family_islamic_details'     => $biodata->family_islamic_details,

                'current_step'               => $biodata->current_step,

                'outdoor_dressup_details'      => $biodata->outdoor_dressup_details,
                'niqab_details'                => $biodata->niqab_details,
                'niqab_started_from'           => $biodata->niqab_started_from,
                'from_when_kept_beard_details' => $biodata->from_when_kept_beard_details,
                'wear_clothes_above_anckle'    => $biodata->wear_clothes_above_anckle,
                'started_prayer_details'       => $biodata->started_prayer_details,
                'missed_prayers'               => $biodata->missed_prayers,
                'mahram_nonmahram_details'     => $biodata->mahram_nonmahram_details,
                'can_recite_quran_details'     => $biodata->can_recite_quran_details,
                'mazhab'                       => $biodata->mazhab,
                'watch_movies_or_listen_songs' => $biodata->watch_movies_or_listen_songs,
                'any_disease_details'          => $biodata->any_disease_details,
                'deen_effort'                  => $biodata->deen_effort,
                'mazar_belief'                 => $biodata->mazar_belief,
                'islamic_books_read'           => $biodata->islamic_books_read,
                'favorite_scholars'            => $biodata->favorite_scholars,
                'special_category'             => json_decode($biodata->special_category) ?? [],
                'hobby_and_wish_details'       => $biodata->hobby_and_wish_details,
                'candidate_mobile_number'      => $biodata->candidate_mobile_number,
                'candidate_photo'              => $biodata->candidate_photo,

                'occupation_category'          => $biodata->occupation_category,
                'occupation_details'           => $biodata->occupation_details,
                'monthly_income'               => $biodata->monthly_income,

                'guardians_consent'               => $biodata->guardians_consent,
                'view_on_marriage'                => $biodata->view_on_marriage,
                'capable_of_keeping_wife_in_veil' => $biodata->capable_of_keeping_wife_in_veil,
                'allow_wife_to_study'             => $biodata->allow_wife_to_study,
                'allow_wifes_job'                 => $biodata->allow_wifes_job,
                'where_live_after_marriage'       => $biodata->where_live_after_marriage,
                'want_dowry'                      => $biodata->want_dowry,
                'want_to_work_after_marriage'     => $biodata->want_to_work_after_marriage,
                'want_to_study_after_marriage'    => $biodata->want_to_study_after_marriage,
                'continue_working_after_marriage' => $biodata->continue_working_after_marriage,

                'partner_min_age'             => $biodata->partner_min_age ? (string)$biodata->partner_min_age : '',
                'partner_max_age'             => $biodata->partner_max_age ? (string)$biodata->partner_max_age : '',
                'partner_complexion'          => $biodata->partner_complexion,
                'partner_min_height_inches'   => $biodata->partner_min_height_inches ? (string)$biodata->partner_min_height_inches : '',
                'partner_max_height_inches'   => $biodata->partner_max_height_inches ? (string)$biodata->partner_max_height_inches : '',
                'partner_education_details'   => $biodata->partner_education_details,
                'partner_district'            => $biodata->partner_district,
                'partner_marital_status'      => $biodata->partner_marital_status,
                'partner_occupation'          => $biodata->partner_occupation,
                'partner_financial_status'    => $biodata->partner_financial_status,
                'partner_qualities_details'   => $biodata->partner_qualities_details,

                'parents_aware'         => $biodata->parents_aware,
                'is_truthful'           => $biodata->is_truthful !== null ? (string)$biodata->is_truthful : '',
                'accept_terms'          => $biodata->accept_terms,

                'name'                  => $biodata->name,
                'guardian_mobile'       => $biodata->guardian_mobile,
                'guardian_relationship' => $biodata->guardian_relationship,
                'contact_email'         => $biodata->contact_email,
            ]
        ], 200);
    }

    /**
     * পাবলিক ভিউ এর জন্য একটি নির্দিষ্ট বায়োডাটা ফেচ করা
     */
public function show($biodata_no)
    {
        try {
            $biodata = Biodata::with([
                'presentDivision:id,bn_name',
                'presentDistrict:id,bn_name',
                'presentUpazila:id,bn_name',
                'presentUnion:id,bn_name',
                'permanentDivision:id,bn_name',
                'permanentDistrict:id,bn_name',
                'permanentUpazila:id,bn_name',
                'permanentUnion:id,bn_name'
            ])
            ->where('biodata_no', $biodata_no)
            ->where('status', 'approved')
            ->where('is_hidden', false)
            ->first();

            if (!$biodata) {
                return response()->json([
                    'success' => false,
                    'message' => 'বায়োডাটা খুঁজে পাওয়া যায়নি অথবা এটি প্রাইভেট করা আছে।'
                ], 404);
            }

            $hasAccessToContact = false;
            $isAdmin = false;
            $contactInfo = null;

            // 🔴 চেক করা হচ্ছে ইউজার লগইন করা আছে কিনা এবং সে কিনেছে কিনা
            if (auth('sanctum')->check()) {
                $user = auth('sanctum')->user();

                // অ্যাডমিন হলে
                if ($user->role === 'admin' || $user->is_admin == 1) {
                    $isAdmin = true;
                    $hasAccessToContact = true;
                }
                // নিজের বায়োডাটা হলে
                elseif ($user->id == $biodata->user_id) {
                    $hasAccessToContact = true;
                }
                // আগে কিনে থাকলে
                else {
                    $isPurchased = \App\Models\PurchasedBiodata::where('user_id', $user->id)
                                        ->where('biodata_id', $biodata->id)
                                        ->exists();
                    if ($isPurchased) {
                        $hasAccessToContact = true;
                    }
                }
            }

            // 🔴 যদি অ্যাক্সেস থাকে, তবে আলাদা একটি অ্যারেতে যোগাযোগের তথ্য রেডি করা
            if ($hasAccessToContact) {
                $contactInfo = [
                    'name' => $biodata->name,
                    'guardian_relationship' => $biodata->guardian_relationship,
                    'phone' => $biodata->guardian_mobile ?? $biodata->guardian_mobile,
                    'email' => $biodata->contact_email
                ];
            }

            // 🔴 নিরাপত্তা: মূল ডাটাবেজ মডেল থেকে সবসময় এগুলো হাইড করে দেওয়া
            $hiddenFields = ['name', 'guardian_mobile', 'contact_email', 'guardian_relationship'];

            if (!$isAdmin) {
                $hiddenFields[] = 'candidate_photo';
                $hiddenFields[] = 'candidate_mobile_number';
            }

            $biodata->makeHidden($hiddenFields);

            // 🔴 রেসপন্সে মূল ডাটার পাশাপাশি contact_info এবং is_purchased পাঠানো হচ্ছে
            return response()->json([
                'success' => true,
                'data' => $biodata,
                'is_purchased' => $hasAccessToContact,
                'contact_info' => $contactInfo
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'সার্ভার এরর!',
                'error' => $e->getMessage()
            ], 500);
        }
    }

/**
     * ইউজারের বায়োডাটা ডিলিট করার লজিক
     */
    public function deleteBiodata(Request $request)
    {
        // ১. রিকোয়েস্ট ভ্যালিডেশন
        $request->validate([
            'password' => 'required|string',
            'reason'   => 'required|string',
            'feedback' => 'nullable|string',
        ]);

        $user = auth()->user();

        // ২. সিকিউরিটি চেক: ইউজারের বর্তমান পাসওয়ার্ড সঠিক কিনা যাচাই করা
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'আপনার প্রদত্ত পাসওয়ার্ডটি সঠিক নয়। অনুগ্রহ করে আবার চেষ্টা করুন।'
            ], 400); // 400 Bad Request
        }

        // ৩. ইউজারের বায়োডাটা খুঁজে বের করা
        $biodata = Biodata::where('user_id', $user->id)->first();

        if (!$biodata) {
            return response()->json([
                'success' => false,
                'message' => 'আপনার কোনো সক্রিয় বায়োডাটা পাওয়া যায়নি।'
            ], 404);
        }

        // ৪. ডিলিট লগ তৈরি করা
        DB::table('biodata_deletion_logs')->insert([
            'user_id' => $user->id,
            'biodata_no' => $biodata->biodata_no,
            'reason' => $request->reason,
            'feedback' => $request->feedback,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 🔴 ৫. স্প্যাম/বারবার ডিলিট চেকার: গত ৩০ দিনে কয়বার ডিলিট করেছে?
        $recentDeletesCount = DB::table('biodata_deletion_logs')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        // 🔴 ৬. রেস্ট্রিকশন লজিক প্রয়োগ করা
        // যদি বিয়ে এই ওয়েবসাইটে (married_here) বা অন্য কোথাও (married_outside) ঠিক হয়
        if (in_array($request->reason, ['married_here', 'married_outside'])) {
            $user->restriction_expires_at = now()->addMonths(6); // ৬ মাসের রেস্ট্রিকশন
            $user->save();
        }
        // যদি গত ৩০ দিনে ১ বারের বেশি (অর্থাৎ বর্তমানটাসহ ২ বার বা তার বেশি) ডিলিট করে
        elseif ($recentDeletesCount > 1) {
            $user->restriction_expires_at = now()->addMonths(3); // স্প্যামিং রোধে ৩ মাসের রেস্ট্রিকশন
            $user->save();
        }

        // ৭. বায়োডাটা ডিলিট করা
        $biodata->delete();

        return response()->json([
            'success' => true,
            'message' => 'আপনার বায়োডাটা সফলভাবে মুছে ফেলা হয়েছে।'
        ]);
    }

    // app/Http/Controllers/BiodataController.php
// BiodataController.php

/**
 * ইউজারের সংগৃহীত (Unlocked) বায়োডাটার তালিকা নিয়ে আসা
 * * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function getUnlockedList(Request $request)
{
    $user = $request->user();

    // User মডেলে ডিফাইন করা purchasedBiodatas রিলেশন ব্যবহার করা হচ্ছে
    $unlockedBiodatas = $user->purchasedBiodatas()
        ->with(['biodata' => function($query) {
            // Soft Delete হওয়া বায়োডাটাগুলোকেও অন্তর্ভুক্ত করার জন্য withTrashed()
            $query->withTrashed()->select('id', 'biodata_no', 'status', 'is_hidden', 'deleted_at');
        }])
        ->latest()
        ->get()
        ->map(function ($item) {
            $biodata = $item->biodata;

            // ১. বায়োডাটাটি কি সফট ডিলিট করা হয়েছে?
            $isDeleted = $biodata && $biodata->trashed();

            // ২. বায়োডাটাটি কি হাইড করা? (is_hidden কলাম ১ হলে)
            $isHidden = $biodata && $biodata->is_hidden == 1;

            // ৩. বায়োডাটাটি কি সক্রিয়? (Approved হতে হবে এবং ডিলিট বা হাইড হওয়া যাবে না)
            $isActive = $biodata &&
                        $biodata->status === 'approved' &&
                        !$isDeleted &&
                        !$isHidden;

            return [
                'id' => $item->id,
                'biodata_no'    => $biodata ? $biodata->biodata_no : 'N/A',
                'unlocked_date' => $item->created_at->format('Y-m-d'),
                'is_active'     => $isActive,
                'is_hidden'     => $isHidden,
                'is_deleted'    => $isDeleted,
                'status'        => $biodata ? ($isDeleted ? 'deleted' : $biodata->status) : 'deleted',
            ];
        });

    return response()->json($unlockedBiodatas);
}
/**
 * current_step এর ওপর ভিত্তি করে প্রোফাইল কমপ্লিশন স্ট্যাটাস দেখা
 */
public function getCompletionStats()
{
    $user = Auth::user();
    $biodata = Biodata::where('user_id', $user->id)->first();

    // বায়োডাটা না থাকলে ০%
    if (!$biodata) {
        return response()->json([
            'percentage' => 0,
            'steps' => $this->getInitialStepsState()
        ]);
    }


    $currentStep = (int) $biodata->current_step;
    $stepLabels = [
        1 => 'সাধারণ তথ্য', 2 => 'ঠিকানা', 3 => 'শিক্ষাগত যোগ্যতা', 4 => 'পারিবারিক তথ্য',
        5 => 'ব্যক্তিগত তথ্য', 6 => 'পেশাগত তথ্য', 7 => 'বিয়ে সংক্রান্ত তথ্য',
        8 => 'প্রত্যাশিত জীবনসঙ্গী', 9 => 'অঙ্গীকারনামা', 10 => 'যোগাযোগের তথ্য'
    ];

    $stepsStatus = [];
    foreach ($stepLabels as $stepNum => $label) {
        $stepsStatus[] = [
            'id' => $stepNum,
            'label' => $label,
            // যদি current_step এর মান এই স্টেপ নাম্বারের সমান বা বেশি হয়, তবে এটি কমপ্লিট
            'completed' => $currentStep >= $stepNum
        ];
    }

    // ১টি স্টেপ = ১০% (১০টি স্টেপের জন্য)
    $percentage = $currentStep * 10;

    return response()->json([
        'percentage' => $percentage > 100 ? 100 : $percentage,
        'steps' => $stepsStatus
    ]);
}
}
