<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class BiodataUnlockedBuyerNotification extends Notification implements ShouldQueue
{
    use Queueable;
    protected $biodataNo, $candidateName, $guardianMobile, $guardianRelationship, $contactEmail, $remainingConnections;

    public function __construct($biodataNo, $candidateName, $guardianMobile, $guardianRelationship, $contactEmail, $remainingConnections)
    {
        $this->biodataNo = $biodataNo;
        $this->candidateName = $candidateName;
        $this->guardianMobile = $guardianMobile;
        $this->guardianRelationship = $guardianRelationship;
        $this->contactEmail = $contactEmail;
        $this->remainingConnections = $remainingConnections;
    }

    public function via($notifiable) { return ['database', 'mail']; }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('যোগাযোগের তথ্য সংগ্রহ সফল! 🔓')
            ->view('emails.biodata_unlocked_buyer', [
                'biodataNo' => $this->biodataNo,
                'candidateName' => $this->candidateName,
                'guardianMobile' => $this->guardianMobile,
                'guardianRelationship' => $this->guardianRelationship,
                'contactEmail' => $this->contactEmail,
                'remainingConnections' => $this->remainingConnections,
                'purchasesUrl' => config('app.frontend_url') . '/user/purchases'
            ]);
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'যোগাযোগের তথ্য সংগ্রহ সফল!',
            'message' => "আপনি সফলভাবে বায়োডাটা #{$this->biodataNo} এর যোগাযোগের তথ্য সংগ্রহ করেছেন।",
            'link' => '/user/purchases'
        ];
    }
}
