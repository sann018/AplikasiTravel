<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountVerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $token,
    ) {
    }

    public function build(): self
    {
        return $this->subject('Kode Verifikasi Akun RuslyTravel')
            ->view('emails.account-verification-code')
            ->with(['token' => $this->token]);
    }
}
