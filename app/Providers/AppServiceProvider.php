<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 🔴 ১. ফিন্যান্স এবং সেটিংস ম্যানেজ করার পারমিশন (শুধু সুপার অ্যাডমিন ও অ্যাডমিন পাবে)
        Gate::define('manage-finance-settings', function (User $user) {
            return in_array($user->role, ['super_admin', 'admin']);
        });

        // 🔴 ২. স্টাফ ম্যানেজ করার পারমিশন (শুধু সুপার অ্যাডমিন পাবে)
        Gate::define('manage-staff', function (User $user) {
            return $user->role === 'super_admin';
        });

        // 🔴 ৩. বায়োডাটা ও সাপোর্ট টিকিট ম্যানেজ করার পারমিশন (সবাই পাবে)
        Gate::define('manage-biodata', function (User $user) {
            return in_array($user->role, ['super_admin', 'admin', 'moderator']);
        });
    }
}
