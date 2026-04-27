<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'biodata_id'];

    // ফেভারিটটি কোন ইউজারের
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ফেভারিট করা বায়োডাটার ডিটেইলস
    public function biodata()
    {
        return $this->belongsTo(Biodata::class);
    }
}
