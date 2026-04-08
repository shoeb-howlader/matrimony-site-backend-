<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    protected $fillable = ['division_id', 'name', 'bn_name', 'lat', 'lon', 'url'];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function upazilas()
    {
        return $this->hasMany(Upazila::class);
    }
}
