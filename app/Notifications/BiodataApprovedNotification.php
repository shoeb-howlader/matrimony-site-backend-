<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class BiodataApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }
    protected $biodata;

    // 🔴 বায়োডাটা অবজেক্টটি কনস্ট্রাক্টরে গ্রহণ করা হচ্ছে
    public function __construct($biodata)
    {
        $this->biodata = $biodata;
    }

    // ── 🔴 ইমেইল পাঠানোর মেথড ──
  public function toMail($notifiable)
    {
        $publicUrl = config('app.frontend_url') . '/biodata/' . $this->biodata->biodata_no;

        return (new MailMessage)
            ->subject('আপনার বায়োডাটা এখন লাইভ! 🎉')
            // 🔴 ডিফল্ট টেমপ্লেটের বদলে আমাদের বানানো কাস্টম ভিউ কল করছি
            ->view('emails.biodata_approved', [
                'userName' => $notifiable->name ?? 'সম্মানিত ইউজার',
                'biodataNo' => $this->biodata->biodata_no,
                'publicUrl' => $publicUrl
            ]);
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
