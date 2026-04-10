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


class Biodata extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     * We use guarded = [] because of the large number of fields (70+).
     * This allows all fields to be filled via $request->all().
     */
    // সিকিউরিটির জন্য fillable অথবা guarded ব্যবহার করুন
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Automate the generation of Biodata Numbers (ODF/ODM)
     */
    protected static function boot()
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
    }

    /**
     * Relationship: A Biodata belongs to a User account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // বর্তমান জেলার রিলেশন
    public function presentDistrict()
    {
        return $this->belongsTo(District::class, 'present_district_id');
    }

    // স্থায়ী জেলার রিলেশন (যা আপনার এরর দিচ্ছে)
    public function permanentDistrict()
    {
        return $this->belongsTo(District::class, 'permanent_district_id');
    }


    // ইচ্ছা করলে উপজেলা এবং ইউনিয়নের রিলেশনও যোগ করতে পারেন
    public function presentUpazila()
    {
        return $this->belongsTo(Upazila::class, 'present_upazila_id');
    }

    public function presentUnion()
    {
        return $this->belongsTo(Union::class, 'present_union_id');
    }

    /**
     * Scope for approved profiles only (Used in your search page)
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }


}
