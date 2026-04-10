<?php

namespace App\Http\Controllers;


use App\Models\Biodata;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;



class BiodataController extends Controller
{
    /**
     * Display a listing of the resource.
     */
public function index(Request $request)
{
    try {
        // কার্ডের জন্য প্রয়োজনীয় ফিল্ডগুলো নির্দিষ্ট করুন
    $cardFields = [
        'id', 'user_id', 'biodata_no', 'type', 'birth_date',
        'height_inches', 'skin_tone', 'occupation_category',
        'present_district_id', 'status', 'permanent_district_id'
    ];
        // ১. Eager Loading যুক্ত করা হয়েছে যাতে জেলার নাম ফ্রন্টএন্ডে পাওয়া যায়
        $query = Biodata::query()
            ->select($cardFields) // শুধুমাত্র এই কলামগুলো ডাটাবেস থেকে আসবে
            ->with(['presentDistrict:id,bn_name', 'permanentDistrict:id,bn_name'])
            ->where('status', 'approved');

        // ২. টাইপ এবং বৈবাহিক অবস্থা
        // BiodataController.php
if ($request->filled('type') && $request->type !== 'all') {
    $query->where('type', $request->type);
}

if ($request->filled('marital_status') && $request->marital_status !== 'all') {
    $query->where('marital_status', $request->marital_status);
}

        // ৩. ডাইনামিক লোকেশন ফিল্টার (বর্তমান ঠিকানা)
        if ($request->filled('present_division')) $query->where('present_division_id', $request->present_division);
        if ($request->filled('present_district')) $query->where('present_district_id', $request->present_district);
        if ($request->filled('present_upazila'))  $query->where('present_upazila_id', $request->present_upazila);
        if ($request->filled('present_union'))    $query->where('present_union_id', $request->present_union);

        // ৪. ডাইনামিক লোকেশন ফিল্টার (স্থায়ী ঠিকানা)
        if ($request->filled('permanent_division')) $query->where('permanent_division_id', $request->permanent_division);
        if ($request->filled('permanent_district')) $query->where('permanent_district_id', $request->permanent_district);
        if ($request->filled('permanent_upazila'))  $query->where('permanent_upazila_id', $request->permanent_upazila);
        if ($request->filled('permanent_union'))    $query->where('permanent_union_id', $request->permanent_union);

        // ৫. বয়স সীমা (Age Range)
        if ($request->has('age_range')) {
            $range = is_array($request->age_range) ? $request->age_range : explode(',', $request->age_range);
            if (count($range) === 2) {
                $minAge = (int)$range[0];
                $maxAge = (int)$range[1];
                $dateMax = now()->subYears($minAge)->format('Y-m-d');
                $dateMin = now()->subYears($maxAge + 1)->addDay()->format('Y-m-d');
                $query->whereBetween('birth_date', [$dateMin, $dateMax]);
            }
        }

        // ৬. উচ্চতা (Height Range)
        if ($request->has('height_range')) {
            $range = is_array($request->height_range) ? $request->height_range : explode(',', $request->height_range);
            if (count($range) === 2) {
                $query->whereBetween('height_inches', [(int)$range[0], (int)$range[1]]);
            }
        }

        // ৭. ফিকহ (মাজহাব)
        if ($request->filled('mazhab')) {
            $query->where('mazhab', $request->mazhab);
        }

        // ৮. গাত্রবর্ণ (Skin Tone)
        if ($request->filled('skin_tones')) {
            $tones = is_array($request->skin_tones) ? $request->skin_tones : explode(',', $request->skin_tones);
            $query->whereIn('skin_tone', $tones);
        }

        // ৯. পেশা (Occupation)
        if ($request->filled('occupation_categories')) {
            $cats = is_array($request->occupation_categories) ? $request->occupation_categories : explode(',', $request->occupation_categories);
            $query->whereIn('occupation_category', $cats);
        }

        // ১০. শিক্ষাগত মাধ্যম ও দ্বীনি যোগ্যতা (LIKE Query for multiple strings)
        foreach (['edu_medias' => 'edu_media', 'deeni_qualifications' => 'deeni_qualification', 'special_categories' => 'special_category'] as $key => $column) {
            if ($request->filled($key)) {
                $items = is_array($request->$key) ? $request->$key : explode(',', $request->$key);
                $query->where(function ($q) use ($items, $column) {
                    foreach ($items as $item) {
                        $q->orWhere($column, 'LIKE', '%' . $item . '%');
                    }
                });
            }
        }
        // ১১. অর্থনৈতিক অবস্থা ফিল্টার
if ($request->filled('family_financial_statuses')) {
    $financialStatuses = $request->input('family_financial_statuses');

    // যদি ফ্রন্টএন্ড থেকে কমা সেপারেটেড স্ট্রিং আসে (যেমন: "মধ্যবিত্ত,নিম্নবিত্ত")
    if (is_string($financialStatuses)) {
        $financialStatuses = explode(',', $financialStatuses);
    }

    if (is_array($financialStatuses) && count($financialStatuses) > 0) {
        // নিশ্চিত হোন কলামের নাম 'family_financial_status' কি না (আপনার মাইগ্রেশন অনুযায়ী)
        $query->whereIn('family_financial_status', $financialStatuses);
    }
}

        // রেজাল্ট রিটার্ন
        return response()->json($query->latest()->paginate(12));

    } catch (\Exception $e) {
        return response()->json(['error' => 'Server Error', 'message' => $e->getMessage()], 500);
    }
}
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
   public function show($id)
{
    // রিলেশনসহ ডাটা কল করা (যেমন: presentDistrict, permanentDivision ইত্যাদি)
    $biodata = Biodata::find($id);

    if (!$biodata) {
        return response()->json(['message' => 'বায়োডাটা পাওয়া যায়নি'], 404);
    }

    return response()->json($biodata, 200);
}

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Biodata $biodata)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Biodata $biodata)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Biodata $biodata)
    {
        //
    }
}
