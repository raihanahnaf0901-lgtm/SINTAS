<?php

use App\Http\Controllers\KeanggotaanRuangMapelController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RuangMapelController;
use App\Http\Controllers\SubjectController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::get('/', function (Request $request): Response|RedirectResponse {
    if ($request->user()) {
        return to_route('dashboard');
    }

    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/dashboard', function (): Response {
        return Inertia::render('Dashboard', [
            'summaries' => config('learning.summaries'),
            'activities' => config('learning.activities'),
            'subjects' => collect(config('learning.subjects'))
                ->map(fn (array $subject, string $slug): array => [
                    'slug' => $slug,
                    'name' => $subject['name'],
                    'icon' => $subject['icon'],
                    'tasks' => collect($subject['tasks'])->where('completed', false)->count(),
                ])->values(),
        ]);
    })->name('dashboard');

    Route::get('/mata-pelajaran', [SubjectController::class, 'index'])->name('subjects.index');
    Route::get('/mata-pelajaran/{subject}', [SubjectController::class, 'show'])->name('subjects.show');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('ruang-mapel')->name('ruang-mapels.')->group(function (): void {
        Route::get('/', [RuangMapelController::class, 'index'])->name('index');
        Route::post('/', [RuangMapelController::class, 'store'])->name('store');
        Route::post('/gabung', [KeanggotaanRuangMapelController::class, 'store'])->name('join');
        Route::patch('/permintaan/{keanggotaanRuangMapel}', [KeanggotaanRuangMapelController::class, 'update'])
            ->name('memberships.update');
        Route::get('/{ruangMapel}', [RuangMapelController::class, 'show'])->name('show');
    });
});

require __DIR__.'/auth.php';
