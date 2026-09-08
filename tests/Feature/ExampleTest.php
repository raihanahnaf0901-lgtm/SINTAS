<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_shows_a_welcome_page_without_learning_data_to_guests(): void
    {
        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('auth.user', null)
                ->missing('subjects')
                ->missing('tasks')
                ->missing('activities')
                ->missing('summaries'));
    }

    public function test_authenticated_user_is_redirected_from_home_to_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertRedirectToRoute('dashboard');
    }
}
