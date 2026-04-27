<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'biodata_no',
        'reason',
        'description',
        'attachment',
        'status',
    ];

    // যে ইউজার রিপোর্ট করেছে তার সাথে রিলেশন
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
