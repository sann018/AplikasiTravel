<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class PasswordResetCodeMail extends Mailable
{
    public function __construct(
        public readonly string $token,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Reset Password RuslyTravel')
            ->text('emails.password-reset-code');
    }
}
