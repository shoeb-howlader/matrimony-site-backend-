<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BiodataApprovedNotification extends Notification
{
    use Queueable;

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'title'   => 'আলহামদুলিল্লাহ! আপনার বায়োডাটা অনুমোদিত হয়েছে',
            'message' => 'আপনার বায়োডাটাটি সফলভাবে লাইভ করা হয়েছে। এখন সবাই আপনার বায়োডাটা দেখতে পারবে।',
            'icon'    => 'i-heroicons-check-badge',
            'link'    => '/user/biodata/preview'
        ];
    }
}
