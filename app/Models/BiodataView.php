<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BiodataView extends Model
{
    use HasFactory;

    // 🔴 এই লাইনটি অবশ্যই দিতে হবে
    protected $fillable = ['viewer_id', 'biodata_id', 'ip_address', 'user_agent'];


    public function viewer()
{
    return $this->belongsTo(User::class, 'viewer_id');
}

public function biodata()
{
    return $this->belongsTo(Biodata::class, 'biodata_id');
}

public function views()
{
    return $this->hasMany(BiodataView::class, 'biodata_id');
}

public function user()
{
    return $this->belongsTo(User::class, 'user_id');
}
}
