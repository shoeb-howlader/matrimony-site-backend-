<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminAlertNotification extends Notification
{
    use Queueable;

    public $title;
    public $message;
    public $url;

    /**
     * Create a new notification instance.
     */
    public function __construct($title, $message, $url = '#')
    {
        $this->title = $title;
        $this->message = $message;
        $this->url = $url;
    }

    /**
     * Get the notification's delivery channels.
     * এখানে শুধু 'database' দেওয়া হয়েছে যেন এটি ডাটাবেসে সেভ হয়।
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     * এই ডাটাগুলোই ফ্রন্টএন্ডে notification.data.title হিসেবে যাবে।
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
