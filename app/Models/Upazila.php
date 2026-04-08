<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Upazila extends Model
{
    protected $fillable = ['district_id', 'name', 'bn_name', 'url'];

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function unions()
    {
        return $this->hasMany(Union::class);
    }
}
