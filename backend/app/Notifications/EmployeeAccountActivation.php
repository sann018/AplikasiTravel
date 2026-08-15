<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class EmployeeAccountActivation extends ResetPassword
{
    /** Menampilkan tautan reset sebagai aktivasi akun pegawai yang mudah dipahami. */
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Aktivasi Akun RuslyTravel')
            ->greeting('Halo,')
            ->line('Akun pegawai RuslyTravel Anda telah dibuat oleh Admin.')
            ->line('Silakan tentukan kata sandi pribadi untuk mengaktifkan akun.')
            ->action('Atur Kata Sandi dan Aktifkan Akun', $url)
            ->line('Tautan ini berlaku selama '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' menit.')
            ->line('Jika Anda tidak mengenali pembuatan akun ini, hubungi Admin RuslyTravel.');
    }
}
