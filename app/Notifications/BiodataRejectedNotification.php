<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class BiodataRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    public $reason;

    public function __construct($reason)
    {
        $this->reason = $reason;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }
   // ── 🔴 ইমেইল পাঠানোর মেথড ──
 public function toMail($notifiable)
    {
        $editUrl = config('app.frontend_url') . '/user/biodata/create';

        return (new MailMessage)
            ->subject('আপনার বায়োডাটা অনুমোদন করা সম্ভব হয়নি')
            // 🔴 কাস্টম ভিউ কল করা হচ্ছে
            ->view('emails.biodata_rejected', [
                'userName' => $notifiable->name ?? 'সম্মানিত ইউজার',
                'reason' => $this->reason,
                'editUrl' => $editUrl
            ]);
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
