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
        $channels = [
            // 🔴 ফিক্স: ইউজারের রোল যাই হোক না কেন (User, Moderator, Admin, Super Admin),
            // তাকে তার পার্সোনাল চ্যানেলে সিগন্যাল পাঠাতেই হবে।
            new PrivateChannel('App.Models.User.' . $this->user->id),
        ];

        // যদি স্পেশাল কোনো কারণে অ্যাডমিনদের গ্লোবাল চ্যানেলেও ব্রডকাস্ট করতে চান,
        // তবে নিচের কোডটি আনকমেন্ট করতে পারেন। কিন্তু পার্সোনাল নোটিফিকেশনের ক্ষেত্রে
        // এটি না রাখাই ভালো, নইলে একজনের রোল চেঞ্জ হলে অন্য অ্যাডমিনরাও নোটিফিকেশন পেয়ে যাবে।

        /*
        if (in_array($this->user->role, ['super_admin', 'admin', 'moderator'])) {
            $channels[] = new PrivateChannel('admin-notifications');
        }
        */

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'NotificationSent';
    }
}
