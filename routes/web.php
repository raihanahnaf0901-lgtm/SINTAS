<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('home');

Route::get('/mata-pelajaran', function () {
    return Inertia::render('Subjects');
})->name('subjects.index');

Route::get('/mata-pelajaran/matematika', function () {
    return Inertia::render('SubjectTasks', [
        'subject' => [
            'name' => 'Matematika',
            'teacher' => 'Pak Budi Santoso',
        ],
        'tasks' => [
            [
                'id' => 1,
                'title' => 'Tugas: Aljabar',
                'details' => ['Deadline: 20 Okt 2023', 'Status: Selesai'],
                'completed' => true,
            ],
            [
                'id' => 2,
                'title' => 'Ulangan Harian 1',
                'details' => ['Tanggal: 25 Okt 2023', 'Nilai: 90'],
                'completed' => true,
            ],
            [
                'id' => 3,
                'title' => 'Tugas: Geometri',
                'details' => ['Deadline: 30 Okt 2023', 'Belum Selesai'],
                'completed' => false,
            ],
            [
                'id' => 4,
                'title' => 'Ulangan Semester Ganjil',
                'details' => ['Jadwal: 15 Des 2023', 'Belum Mengikuti'],
                'completed' => false,
            ],
        ],
    ]);
})->name('subjects.show');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
