<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class BiodataViewedOwnerNotification extends Notification implements ShouldQueue
{
    use Queueable;
    protected $biodataNo, $buyerName, $buyerBiodataUrl;

    public function __construct($biodataNo, $buyerName, $buyerBiodataUrl = null)
    {
        $this->biodataNo = $biodataNo;
        $this->buyerName = $buyerName;
        $this->buyerBiodataUrl = $buyerBiodataUrl;
    }

    public function via($notifiable) { return ['database', 'mail']; }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('আপনার যোগাযোগের তথ্য সংগ্রহ করা হয়েছে! 👀')
            ->view('emails.biodata_viewed_owner', [
                'biodataNo' => $this->biodataNo,
                'buyerName' => $this->buyerName,
                'buyerBiodataUrl' => $this->buyerBiodataUrl,
                'dashboardUrl' => config('app.frontend_url') . '/user/dashboard'
            ]);
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'আপনার যোগাযোগের তথ্য সংগ্রহ করা হয়েছে!',
            'message' => "{$this->buyerName} আপনার বায়োডাটার যোগাযোগের তথ্য সংগ্রহ করেছেন।",
            'link' => '/user/dashboard'
        ];
    }
}
