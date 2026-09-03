<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SubjectsPageTest extends TestCase
{
    public function test_subjects_page_is_available_to_students(): void
    {
        $this->get(route('subjects.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Subjects'));
    }

    public function test_mathematics_page_displays_its_tasks(): void
    {
        $this->get(route('subjects.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('SubjectTasks')
                ->where('subject.name', 'Matematika')
                ->has('tasks', 4));
    }
}
