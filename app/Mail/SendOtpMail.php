<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;


class SendOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;

    // OTP রিসিভ করার জন্য কনস্ট্রাক্টর
    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    // মেইলের সাবজেক্ট এবং ভিউ সেট করা
    public function build()
    {
        return $this->subject('আপনার পাসওয়ার্ড রিসেট কোড (OTP)')
                    ->view('emails.otp'); // আমরা এই নামের একটি ভিউ তৈরি করবো
    }
}
