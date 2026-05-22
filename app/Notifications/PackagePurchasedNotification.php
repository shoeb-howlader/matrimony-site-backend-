<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class PackagePurchasedNotification extends Notification implements ShouldQueue
{
    use Queueable;

protected $transaction;
protected $packageName;

    // কনস্ট্রাক্টরে প্যাকেজের বিস্তারিত তথ্য গ্রহণ করা হচ্ছে
   public function __construct($transaction, $packageName)
{
    $this->transaction = $transaction;
    $this->packageName = $packageName;
}

    public function via($notifiable)
    {
        return ['database', 'mail']; // ডাটাবেস এবং ইমেইল দুটোতেই যাবে
    }

public function toMail($notifiable)
{
    return (new MailMessage)
        ->subject('পেমেন্ট সফল এবং প্যাকেজ সক্রিয় হয়েছে! 🎉')
        ->view('emails.package_purchased', [
            'userName'      => $notifiable->name ?? 'সম্মানিত ইউজার',
            'packageName'   => $this->packageName,
            'amount'        => $this->transaction->amount,
            'connections'   => $this->transaction->connections_added,
            'transactionId' => $this->transaction->transaction_id,
            'paymentMethod' => $this->transaction->payment_method,
            'dateTime'      => $this->transaction->created_at->format('d M, Y | h:i A'),
            'totalBalance'  => $notifiable->total_connections, // ইউজারের বর্তমান মোট ব্যালেন্স
            'dashboardUrl'  => config('app.frontend_url') . '/user/dashboard'
        ]);
}

    public function toArray($notifiable)
    {
        return [
            'title' => 'প্যাকেজ ক্রয় সফল!',
            'message' => "আপনি ৳{$this->transaction->amount} দিয়ে '{$this->packageName}' প্যাকেজটি কিনেছেন। আপনার অ্যাকাউন্টে {$this->transaction->connections_added}টি কানেকশন যুক্ত হয়েছে।",
            'icon' => 'i-heroicons-shopping-bag',
            'link' => '/user/purchases'
        ];
    }
}
