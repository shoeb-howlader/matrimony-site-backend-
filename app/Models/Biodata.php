<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Division;
use App\Models\District;
use App\Models\Upazila;
use App\Models\Union;
use App\Models\BiodataPreference;
use Illuminate\Database\Eloquent\SoftDeletes;

class Biodata extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     * We use guarded = [] because of the large number of fields (70+).
     * This allows all fields to be filled via $request->all().
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    // 🔴 এই অংশটুকু আপনার মডেলে মিসিং ছিল! এটি Array ডাটাকে ডাটাবেসে সেভ করতে সাহায্য করে।
    protected $casts = [
        'edu_deeni_titles' => 'array',
        'is_verified' => 'boolean',
    ];

    /**
     * Automate the generation of Biodata Numbers (ODF/ODM)
     */
   /* protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Determine prefix based on gender/type
            $prefix = $model->type === 'Male' ? 'ODM' : 'ODF';

            // Find the latest entry for this gender to increment the number
            $latest = self::where('type', $model->type)
                          ->latest('id')
                          ->first();

            if ($latest) {
                // Extract number from ODF-1001 -> 1001 and increment
                $lastNumber = (int) str_replace($prefix . '-', '', $latest->biodata_no);
                $newNumber = $lastNumber + 1;
            } else {
                // Start from 1000 if no records exist
                $newNumber = 1000;
            }

            $model->biodata_no = $prefix . '-' . $newNumber;
        });
    }*/

    /**
     * Relationship: A Biodata belongs to a User account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ════════════ বর্তমান ঠিকানা (Present Address) ════════════
    public function presentDivision()
    {
        return $this->belongsTo(Division::class, 'present_division_id');
    }

    public function presentDistrict()
    {
        return $this->belongsTo(District::class, 'present_district_id');
    }

    public function presentUpazila()
    {
        return $this->belongsTo(Upazila::class, 'present_upazila_id');
    }

    public function presentUnion()
    {
        return $this->belongsTo(Union::class, 'present_union_id');
    }

    // ════════════ স্থায়ী ঠিকানা (Permanent Address) ════════════
    public function permanentDivision()
    {
        return $this->belongsTo(Division::class, 'permanent_division_id');
    }

    public function permanentDistrict()
    {
        return $this->belongsTo(District::class, 'permanent_district_id');
    }

    public function permanentUpazila()
    {
        return $this->belongsTo(Upazila::class, 'permanent_upazila_id');
    }

    public function permanentUnion()
    {
        return $this->belongsTo(Union::class, 'permanent_union_id');
    }

    public function views()
{
    return $this->hasMany(BiodataView::class, 'biodata_id');
}

   // 🔴 রিলেশনশিপটি আপনার মডেলের নাম অনুযায়ী হবে 🔴
    public function preferences()
    {
        return $this->hasMany(BiodataPreference::class, 'biodata_id');
    }
    /**
     * Scope for approved profiles only (Used in your search page)
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
