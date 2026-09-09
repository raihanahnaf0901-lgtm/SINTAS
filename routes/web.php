<?php

use App\Http\Controllers\KelasMapelController;
use App\Http\Controllers\LearningPageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubjectController;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn (Request $request) => $request->user() ? to_route('dashboard') : Inertia::render('Welcome'))->name('home');

Route::get('/kelas-mapel/undangan/{token}', [KelasMapelController::class, 'invite'])
    ->middleware('throttle:30,1')->name('kelas-mapel.invite');

Route::middleware(['auth', EnsureActiveAccount::class])->group(function (): void {
    Route::get('/dashboard', [LearningPageController::class, 'dashboard'])->name('dashboard');
    Route::get('/mata-pelajaran', [SubjectController::class, 'index'])->name('subjects.index');
    Route::get('/mata-pelajaran/{subject}', [SubjectController::class, 'show'])->name('subjects.show');
    Route::get('/mata-pelajaran/{subject}/{section}', [SubjectController::class, 'show'])
        ->whereIn('section', ['tugas', 'jadwal', 'ujian', 'anggota', 'nilai', 'rekap', 'pengaturan'])->name('subjects.section');
    Route::get('/aktivitas/{section}', [LearningPageController::class, 'overview'])
        ->whereIn('section', ['jadwal', 'tugas', 'ujian', 'nilai'])->name('learning.overview');
    Route::get('/notifikasi', [LearningPageController::class, 'notifications'])->name('notifications.index');
    Route::get('/data-sekolah', [LearningPageController::class, 'masterData'])->name('master-data.index');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
