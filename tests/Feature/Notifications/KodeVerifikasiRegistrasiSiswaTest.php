<?php

namespace Tests\Feature\Notifications;

use App\Notifications\KodeVerifikasiRegistrasiSiswa;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\TestCase;

class KodeVerifikasiRegistrasiSiswaTest extends TestCase
{
    public function test_registration_email_contains_the_code_and_security_guidance(): void
    {
        $mail = (new KodeVerifikasiRegistrasiSiswa('123456', 10))
            ->toMail(new AnonymousNotifiable);

        $this->assertSame('Kode Verifikasi Pendaftaran SINTAS', $mail->subject);
        $this->assertContains('Kode verifikasi Anda: 123456', $mail->introLines);
        $this->assertContains('Kode ini berlaku selama 10 menit dan hanya dapat digunakan satu kali.', $mail->introLines);
        $this->assertContains(
            'Jangan berikan kode ini kepada siapa pun. Abaikan email ini jika Anda tidak membuat akun SINTAS.',
            $mail->introLines,
        );
    }
}
