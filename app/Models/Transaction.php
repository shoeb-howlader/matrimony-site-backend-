<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'connection_package_id',
        'transaction_id',
        'amount',
        'connections_added',
        'status',
        'payment_method'
    ];

    /**
     * User Relationship
     * একটি ট্রানজেকশন একজন ইউজারের সাথে সম্পর্কিত
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Package Relationship
     * একটি ট্রানজেকশন একটি কানেকশন প্যাকেজের সাথে সম্পর্কিত
     */
    public function connectionPackage()
    {
        return $this->belongsTo(ConnectionPackage::class, 'connection_package_id');
    }
}
