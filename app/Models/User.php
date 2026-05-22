<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Biodata;
use App\Models\PurchasedBiodata;
use App\Models\BiodataView;
use App\Models\BiodataPreference;
use App\Models\SupportTicket;
use App\Models\LoginHistory;
use App\Models\UserRestrictionLog;
use Illuminate\Database\Eloquent\BroadcastsEvents;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

#[Fillable(['name', 'email', 'password', 'total_connections', 'role', 'restriction_expires_at', 'restriction_reason','mobile','google_id','avatar','gender'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;
    use BroadcastsEvents;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'total_connections' => 'integer',

        ];
    }

    // ─── Existing Relationships ───
    public function biodata() { return $this->hasOne(Biodata::class); }
    public function deletionLogs() { return $this->hasMany(BiodataDeletionLog::class); }
    public function purchasedBiodatas() { return $this->hasMany(PurchasedBiodata::class); }
    public function transactions(): HasMany { return $this->hasMany(Transaction::class); }

    // ─── 🔴 New Relationships for Admin Dashboard ───

    // ইউজার যেসব প্রোফাইল ভিজিট করেছে
    public function visitedProfiles() {
        return $this->hasMany(BiodataView::class, 'viewer_id');
    }

    // আনলক করা প্রোফাইল
    public function unlockedBiodatas() {
        return $this->hasMany(PurchasedBiodata::class, 'user_id');
    }

    // ইউজারের শর্টলিস্ট ও ইগনোর লিস্ট
    public function preferences() {
        return $this->hasMany(BiodataPreference::class, 'user_id');
    }

    // ইউজারের পাঠানো সকল সাপোর্ট টিকিট ও রিপোর্ট
    public function supportTickets() {
        return $this->hasMany(SupportTicket::class, 'user_id');
    }

public function isAdmin()
{
    // এখন থেকে যেখানেই isAdmin কল হবে, সে চেক করবে ইউজার এই ৩টির যেকোনো একটি কিনা
    return in_array($this->role, ['super_admin', 'admin', 'moderator']);
}

    // তার বিরুদ্ধে করা রিপোর্ট
    public function reportsReceived() {
        return $this->hasMany(\App\Models\Report::class, 'reported_user_id');
    }

    // তার করা রিপোর্ট
    public function reportsMade() {
        return $this->hasMany(\App\Models\Report::class, 'user_id');
    }

    // লগইন হিস্ট্রি
    public function loginHistories() {
        return $this->hasMany(\App\Models\LoginHistory::class, 'user_id')->latest();
    }

    // ইউজারের রেস্ট্রিকশন হিস্ট্রি বা লগ
    public function restrictionLogs() {
        return $this->hasMany(UserRestrictionLog::class, 'user_id')->latest();
    }


}
