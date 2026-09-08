<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KodeVerifikasiLoginSiswa extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $expiresInMinutes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kode Verifikasi Login SINTAS')
            ->greeting('Halo, '.$notifiable->name.'!')
            ->line('Kami menerima permintaan login ke akun siswa SINTAS Anda.')
            ->line('Kode verifikasi Anda: '.$this->code)
            ->line('Kode ini berlaku selama '.$this->expiresInMinutes.' menit dan hanya dapat digunakan satu kali.')
            ->line('Jangan berikan kode ini kepada siapa pun. Abaikan email ini jika Anda tidak meminta login.')
            ->salutation('Salam, Tim SINTAS');
    }
}
