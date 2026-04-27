<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BiodataDeletionLog extends Model
{
    use HasFactory;

    // কোন ফিল্ডগুলোতে ডাটা ইনসার্ট করা যাবে তা বলে দেওয়া
    protected $fillable = [
        'user_id',
        'biodata_no',
        'reason',
        'feedback',
        'restored_at',
    'admin_note',
    ];

    // 🔴 রিলেশনশিপ: এই লগটি কোন ইউজারের? 🔴
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
