<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UserAlertNotification extends Notification
{
    use Queueable;

    protected $title;
    protected $message;
    protected $link;

    public function __construct($title, $message, $link = null)
    {
        $this->title = $title;
        $this->message = $message;
        $this->link = $link;
    }

    // নোটিফিকেশনটি কোথায় সেভ হবে (আমরা ডাটাবেসে সেভ করবো)
    public function via($notifiable)
    {
        return ['database'];
    }

    // ডাটাবেসে ডাটা কীভাবে সেভ হবে (আপনার ফ্রন্টএন্ডের ডিজাইনের সাথে মিলিয়ে)
    public function toArray($notifiable)
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'link' => $this->link ?? '/user/dashboard',
        ];
    }
}
