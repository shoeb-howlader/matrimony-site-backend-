<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRestrictionLog extends Model
{
    protected $fillable = ['user_id', 'restricted_days', 'reason', 'expires_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
