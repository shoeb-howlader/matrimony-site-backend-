<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'category', 'biodata_no', 'subject', 'message', 'attachment', 'status', 'admin_reply'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // 🔴 নতুন: যার বিরুদ্ধে অভিযোগ করা হয়েছে (Reported Biodata)
    public function reportedBiodata()
    {
        // biodata_no কলামের মাধ্যমে Biodata টেবিলের সাথে কানেকশন
        return $this->belongsTo(Biodata::class, 'biodata_no', 'biodata_no');
    }
}
