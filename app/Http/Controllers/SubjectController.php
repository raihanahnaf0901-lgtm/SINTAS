<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function index(): Response
    {
        $subjects = collect(config('learning.subjects'))
            ->map(fn (array $subject, string $slug): array => [
                'slug' => $slug,
                'name' => $subject['name'],
                'teacher' => $subject['teacher'],
                'icon' => $subject['icon'],
                'completed' => collect($subject['tasks'])->where('completed', true)->count(),
                'total' => count($subject['tasks']),
            ])->values();

        return Inertia::render('Subjects', ['subjects' => $subjects]);
    }

    public function show(string $subject): Response
    {
        $subjects = config('learning.subjects');

        abort_unless(array_key_exists($subject, $subjects), 404);

        $selectedSubject = $subjects[$subject];

        return Inertia::render('SubjectTasks', [
            'subject' => [
                'slug' => $subject,
                'name' => $selectedSubject['name'],
                'teacher' => $selectedSubject['teacher'],
                'icon' => $selectedSubject['icon'],
            ],
            'tasks' => $selectedSubject['tasks'],
        ]);
    }
}
