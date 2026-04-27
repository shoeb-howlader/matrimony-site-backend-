<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Biodata;

class BiodataPreference extends Model
{
    use HasFactory;

    // 🔴 এই লাইনটি না থাকলে লারাভেল 500 এরর দিবে
    protected $fillable = ['user_id', 'biodata_id', 'type'];
    // এই প্রেফারেন্সটি কোন বায়োডাটার?
    public function biodata()
    {
        return $this->belongsTo(Biodata::class, 'biodata_id');
    }

    // এই প্রেফারেন্সটি কোন ইউজারের?
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
