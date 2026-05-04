<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $notification;

    public function __construct(User $user, $notification)
    {
        $this->user = $user;
        $this->notification = $notification;
    }

    public function broadcastOn(): array
    {
        // 🔴 যদি ইউজারের রোল admin হয়, তবে সে অ্যাডমিন চ্যানেলে লিসেন করবে
        if ($this->user->role === 'admin') {
            return [
                new PrivateChannel('admin-notifications'),
            ];
        }

        // সাধারণ ইউজারের জন্য তার নিজের আইডি চ্যানেল
        return [
            new PrivateChannel('App.Models.User.' . $this->user->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'NotificationSent';
    }
}
