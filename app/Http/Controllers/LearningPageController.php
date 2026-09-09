<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\Jadwal;
use App\Models\KelasMapel;
use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use App\Services\LearningData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LearningPageController extends Controller
{
    public function dashboard(Request $request, LearningData $learning): Response
    {
        $user = $request->user();
        if ($user->role !== 'guru') {
            return Inertia::render('Dashboard', $learning->dashboard($user));
        }
        $rooms = KelasMapel::query()->accessibleTo($user)->where('status', 'aktif');
        $roomIds = (clone $rooms)->select('id');
        $ownedIds = (clone $rooms)->where('guru_pembuat_id', $user->guru->id)->select('id');

        return Inertia::render('TeacherDashboard', [
            'stats' => [
                'kelas' => (clone $rooms)->count(),
                'siswa' => AnggotaKelas::query()->whereIn('kelas_mapel_id', $roomIds)->where('status', 'diterima')->distinct()->count('siswa_id'),
                'pending' => AnggotaKelas::query()->whereIn('kelas_mapel_id', $ownedIds)->where('status', 'pending')->count(),
                'pengumpulan' => PengumpulanTugas::query()->whereHas('tugas', fn ($q) => $q->whereIn('kelas_mapel_id', $roomIds))
                    ->whereNotNull('submitted_at')->count(),
            ],
            'rooms' => $rooms->with('mapel')->withCount('tugas')->latest()->limit(6)->get(),
            'requests' => AnggotaKelas::query()->whereIn('kelas_mapel_id', $ownedIds)->where('status', 'pending')
                ->with(['siswa:id,nama_lengkap', 'kelasMapel:id,nama_kelas_mapel'])->latest('requested_at')->limit(6)->get(),
        ]);
    }

    public function overview(Request $request, string $section): Response
    {
        abort_unless(in_array($section, ['jadwal', 'tugas', 'ujian', 'nilai'], true), 404);
        Gate::authorize('viewAny', KelasMapel::class);
        $rooms = KelasMapel::query()->accessibleTo($request->user())->where('status', 'aktif')->select('id');
        $items = match ($section) {
            'jadwal' => Jadwal::query()->whereIn('kelas_mapel_id', $rooms)->with('kelasMapel:id,nama_kelas_mapel')->orderBy('hari')->orderBy('jam_mulai')->paginate(30),
            'tugas' => Tugas::query()->whereIn('kelas_mapel_id', $rooms)->with('kelasMapel:id,nama_kelas_mapel')->orderBy('deadline')->paginate(30),
            default => null,
        };

        return Inertia::render('LearningOverview', ['section' => $section, 'items' => $items]);
    }

    public function notifications(): Response
    {
        return Inertia::render('Notifications');
    }

    public function masterData(): Response
    {
        Gate::authorize('create', KelasMapel::class);

        return Inertia::render('MasterData');
    }
}
