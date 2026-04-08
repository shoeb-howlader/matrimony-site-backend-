<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    protected $fillable = ['name', 'bn_name', 'url'];

    public function districts()
    {
        return $this->hasMany(District::class);
    }
}
