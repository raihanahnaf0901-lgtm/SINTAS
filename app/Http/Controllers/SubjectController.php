<?php

namespace App\Http\Controllers;

use App\Models\KelasMapel;
use App\Services\LearningData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function index(Request $request, LearningData $learning): Response
    {
        return Inertia::render('Subjects', ['subjects' => $learning->subjects($request->user())]);
    }

    public function show(Request $request, string $subject, LearningData $learning, string $section = 'tugas'): Response
    {
        $kelas = KelasMapel::query()->findOrFail($subject);
        Gate::authorize('view', $kelas);
        $user = $request->user();
        $permissions = [
            'manageAcademic' => $user->can('manageAcademic', $kelas),
            'reviewMembers' => $user->can('reviewMembers', $kelas),
            'update' => $user->can('update', $kelas),
            'submit' => $kelas->status === 'aktif' && $user->can('submit', $kelas),
            'viewRekap' => $user->role === 'siswa' || $user->guru?->jenis_guru === 'guru_mapel',
            'isTeacher' => $user->role === 'guru',
        ];
        abort_if($section === 'anggota' && ! $permissions['isTeacher'], 403);
        abort_if($section === 'pengaturan' && ! $permissions['update'], 403);
        abort_if($section === 'rekap' && ! $permissions['viewRekap'], 403);

        return Inertia::render('SubjectTasks', [
            'kelasMapel' => $kelas->load(['mapel', 'pembuat:id,nama_lengkap,gelar']),
            'permissions' => $permissions, 'section' => $section,
            'subject' => ['slug' => (string) $kelas->id, 'name' => $kelas->nama_kelas_mapel, 'icon' => 'book',
                'teacher' => $kelas->pembuat->nama_lengkap],
            'tasks' => $learning->tasks($request->user(), $kelas),
        ]);
    }
}
