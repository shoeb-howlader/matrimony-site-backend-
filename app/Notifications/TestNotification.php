<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TestNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable)
    {
        return ['database']; // শুধু ডাটাবেজে সেভ হবে
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable)
    {
        return [
            'title'   => 'এটি একটি টেস্ট নোটিফিকেশন!',
            'message' => 'কমান্ড লাইন থেকে সফলভাবে নোটিফিকেশন পাঠানো হয়েছে।',
            'icon'    => 'i-heroicons-check-badge',
            'link'    => '/user/dashboard' // ক্লিক করলে কোথায় যাবে
        ];
    }
}
