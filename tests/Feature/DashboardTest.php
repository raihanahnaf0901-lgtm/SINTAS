<?php

namespace Tests\Feature;

use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_redirects_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirectToRoute('login');
    }

    public function test_new_student_sees_zero_progress_and_empty_activities(): void
    {
        $student = Siswa::factory()->create();
        $this->actingAs($student->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard')
                ->has('summaries', 3)->where('summaries.0.total', 0)->where('summaries.0.completed', 0)
                ->has('activities.deadline', 0)->has('activities.susulan', 0)->has('subjects', 0)->has('jadwal', 0));
    }
}
