<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\UserAlertNotification;
use App\Events\NotificationSent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SendGlobalNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $title;
    public $message;
    public $link;

    public function __construct($title, $message, $link = '/user/dashboard')
    {
        $this->title = $title;
        $this->message = $message;
        $this->link = $link;
    }

    public function handle()
    {
        // 🔴 Chunking: একসাথে ৫০০ জন করে ইউজার ধরে প্রসেস করবে, যাতে মেমরি ক্র্যাশ না করে
        User::chunk(500, function ($users) {
            foreach ($users as $user) {
                try {
                    // ১. ডাটাবেসে নোটিফিকেশন সেভ করা
                    $user->notify(new UserAlertNotification(
                        $this->title,
                        $this->message,
                        $this->link
                    ));

                    // ২. Reverb এর মাধ্যমে রিয়েল-টাইম পুশ করা
                    $notificationData = [
                        'title' => $this->title,
                        'message' => $this->message,
                        'link' => $this->link
                    ];

                    $notificationObj = [
                        'id' => Str::uuid()->toString(),
                        'type' => 'App\\Notifications\\UserAlertNotification',
                        'notifiable_type' => 'App\\Models\\User',
                        'notifiable_id' => $user->id,
                        'data' => $notificationData,
                        'read_at' => null,
                        'created_at' => now()->toISOString(),
                        'updated_at' => now()->toISOString(),
                    ];

                    event(new NotificationSent($user, $notificationObj));

                } catch (\Exception $e) {
                    Log::error('Global Notification Error for User ID ' . $user->id . ': ' . $e->getMessage());
                }
            }
        });
    }
}
