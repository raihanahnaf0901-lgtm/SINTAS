<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Notifications\KodeVerifikasiLoginSiswa;
use Tests\TestCase;

class KodeVerifikasiLoginSiswaTest extends TestCase
{
    public function test_email_contains_the_code_expiration_and_escapes_the_student_name(): void
    {
        $user = User::factory()->make([
            'name' => "Raihan <script>alert('xss')</script>",
        ]);
        $notification = new KodeVerifikasiLoginSiswa('123456', 10);

        $rendered = $notification->toMail($user)->render();

        $this->assertStringContainsString('123456', $rendered);
        $this->assertStringContainsString('10 menit', $rendered);
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $rendered);
    }
}
