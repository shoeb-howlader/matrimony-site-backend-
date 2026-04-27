<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedSearch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'filter_data',
    ];

    // ডাটাবেসের JSON কে অটোমেটিক Array তে কনভার্ট করার জন্য
    protected $casts = [
        'filter_data' => 'array',
    ];

    // ইউজারের সাথে রিলেশনশিপ
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function savedSearches()
{
    return $this->hasMany(SavedSearch::class);
}
}
