<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SubjectsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_the_subject_list(): void
    {
        $this->get(route('subjects.index'))
            ->assertRedirectToRoute('login');
    }

    public function test_guests_are_redirected_to_login_from_subject_details(): void
    {
        $this->get(route('subjects.show', ['subject' => 'matematika']))
            ->assertRedirectToRoute('login');
    }

    public function test_guests_cannot_request_learning_data_as_json(): void
    {
        foreach (['/dashboard', '/mata-pelajaran', '/mata-pelajaran/matematika'] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
    }

    public function test_subjects_page_is_available_to_students(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('subjects.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Subjects')
                ->has('subjects', 10)
                ->where('subjects.0.slug', 'matematika')
                ->where('subjects.0.completed', 2)
                ->where('subjects.0.total', 4)
                ->where('subjects.2.slug', 'fisika')
                ->where('subjects.2.total', 0));
    }

    public function test_mathematics_page_displays_its_tasks(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('subjects.show', ['subject' => 'matematika']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('SubjectTasks')
                ->where('subject.name', 'Matematika')
                ->has('tasks', 4)
                ->where('tasks.0.title', 'Tugas: Aljabar')
                ->where('tasks.0.type', 'tugas')
                ->where('tasks.0.completed', true)
                ->where('tasks.1.type', 'harian')
                ->where('tasks.3.type', 'semester'));
    }

    public function test_each_subject_opens_its_own_detail_page(): void
    {
        $this->actingAs(User::factory()->create());

        $subjects = [
            'matematika' => 'Matematika',
            'bahasa-indonesia' => 'Bahasa Indonesia',
            'fisika' => 'Fisika',
            'biologi' => 'Biologi',
            'sejarah' => 'Sejarah',
            'ekonomi' => 'Ekonomi',
            'kimia' => 'Kimia',
            'geografi' => 'Geografi',
            'seni-budaya' => 'Seni Budaya',
            'penjasorkes' => 'Penjasorkes',
        ];

        foreach ($subjects as $slug => $name) {
            $this->get(route('subjects.show', ['subject' => $slug]))
                ->assertInertia(fn (Assert $page) => $page
                    ->component('SubjectTasks')
                    ->where('subject.slug', $slug)
                    ->where('subject.name', $name));
        }
    }

    public function test_subject_without_tasks_has_an_empty_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('subjects.show', ['subject' => 'fisika']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('SubjectTasks')
                ->where('subject.name', 'Fisika')
                ->where('subject.icon', 'atom')
                ->has('tasks', 0));
    }

    public function test_unknown_subject_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('subjects.show', ['subject' => 'tidak-ada']))
            ->assertNotFound();
    }

    public function test_login_returns_user_to_the_subject_they_requested(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $subjectUrl = route('subjects.show', ['subject' => 'fisika']);

        $this->get($subjectUrl)->assertRedirectToRoute('login');

        $this->post(route('login'), [
            'role' => 'admin',
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect($subjectUrl);

        $this->assertAuthenticatedAs($user);
    }
}
