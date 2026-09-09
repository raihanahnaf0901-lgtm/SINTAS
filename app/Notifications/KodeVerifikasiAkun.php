<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KodeVerifikasiAkun extends Notification
{
    public function __construct(public readonly string $code, public readonly string $purpose, public readonly int $minutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = match ($this->purpose) {
            'register' => 'Pendaftaran',
            'reset_password' => 'Reset Password',
            default => 'Login',
        };

        return (new MailMessage)->subject('Kode Verifikasi '.$label.' SINTAS')
            ->greeting('Halo, '.$notifiable->name.'!')
            ->line('Kode verifikasi: '.$this->code)
            ->line('Kode berlaku '.$this->minutes.' menit dan hanya dapat digunakan sekali.')
            ->line('Jangan bagikan kode ini. Abaikan jika Anda tidak mengajukan permintaan ini.');
    }
}
