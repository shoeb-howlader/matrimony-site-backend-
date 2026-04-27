<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasedBiodata extends Model
{
    use HasFactory;

    // ডাটা ইনসার্ট করার অনুমতি
    protected $fillable = [
        'user_id',
        'biodata_id',
    ];

    /**
     * কোন ইউজার এই বায়োডাটা কিনেছে
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * কোন বায়োডাটা কেনা হয়েছে
     */
    public function biodata(): BelongsTo
    {
        return $this->belongsTo(Biodata::class);
    }

}
