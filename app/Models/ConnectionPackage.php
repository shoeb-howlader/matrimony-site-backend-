<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConnectionPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'connection_count',
        'price',
        'discount_price',
        'badge_text',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'float',
        'discount_price' => 'float',
    ];
}
