<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KodeVerifikasiRegistrasiSiswa extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $expiresInMinutes,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kode Verifikasi Pendaftaran SINTAS')
            ->greeting('Halo!')
            ->line('Gunakan kode berikut untuk memverifikasi pendaftaran akun siswa SINTAS Anda.')
            ->line('Kode verifikasi Anda: '.$this->code)
            ->line('Kode ini berlaku selama '.$this->expiresInMinutes.' menit dan hanya dapat digunakan satu kali.')
            ->line('Jangan berikan kode ini kepada siapa pun. Abaikan email ini jika Anda tidak membuat akun SINTAS.')
            ->salutation('Salam, Tim SINTAS');
    }
}
