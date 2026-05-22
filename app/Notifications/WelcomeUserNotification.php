<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class WelcomeUserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        //
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

   public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('ম্যাট্রিমনি প্ল্যাটফর্মে আপনাকে স্বাগতম! 🎉')
            ->view('emails.welcome', [
                'userName' => $notifiable->name,
                // 🔴 এখানে আপনার দেওয়া বায়োডাটা তৈরির লিংকটি বসিয়ে দেওয়া হলো
                'dashboardUrl' => config('app.frontend_url') . '/user/biodata/create'
            ]);
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'স্বাগতম!',
            'message' => 'আমাদের প্ল্যাটফর্মে যুক্ত হওয়ার জন্য ধন্যবাদ। আপনার প্রোফাইল সম্পূর্ণ করুন।',
            'icon' => 'i-heroicons-hand-raised',
            'link' => '/user/biodata/create'
        ];
    }
}
