<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\SupportTicket;

class TicketResolvedNotification extends Notification
{
    use Queueable;

    protected $ticket;

    public function __construct(SupportTicket $ticket)
    {
        $this->ticket = $ticket;
    }

    public function via($notifiable)
    {
        return ['database']; // ডাটাবেসে নোটিফিকেশন সেভ হবে
    }

    public function toArray($notifiable)
    {
        return [
            'ticket_id' => $this->ticket->id,
            'subject' => $this->ticket->subject,
            'message' => 'আপনার সাপোর্ট টিকিটটি সমাধান করা হয়েছে।',
            'type' => 'support_ticket_resolved',
            'link' => '/user/support' // ইউজারের সাপোর্ট পেজের লিংক
        ];
    }
}
