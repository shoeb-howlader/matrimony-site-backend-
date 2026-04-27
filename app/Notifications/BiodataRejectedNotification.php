<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BiodataRejectedNotification extends Notification
{
    use Queueable;
    public $reason;

    public function __construct($reason)
    {
        $this->reason = $reason;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'title'   => 'আপনার বায়োডাটাটি সাময়িকভাবে বাতিল করা হয়েছে',
            'message' => 'কারণ: ' . $this->reason . '। অনুগ্রহ করে তথ্যগুলো সংশোধন করে পুনরায় সাবমিট করুন।',
            'icon'    => 'i-heroicons-exclamation-triangle',
            'link'    => '/user/biodata/create'
        ];
    }
}
