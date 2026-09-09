<?php

namespace App\Services;

use App\Models\Jadwal;
use App\Models\KelasMapel;
use App\Models\Tugas;
use App\Models\Ujian;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LearningData
{
    public function subjects(User $user): Collection
    {
        return KelasMapel::query()->accessibleTo($user)->where('status', 'aktif')->with('pembuat:id,nama_lengkap,gelar')
            ->withCount(['tugas', 'ujian',
                'tugas as tugas_selesai' => fn (Builder $q) => $this->completedTasks($q, $user),
                'ujian as ujian_selesai' => fn (Builder $q) => $q->whereHas('penilaian', fn ($n) => $n->where('siswa_id', $user->siswa?->id ?? 0)),
            ])->get()->map(fn (KelasMapel $kelas): array => [
                'slug' => (string) $kelas->id, 'name' => $kelas->nama_kelas_mapel, 'icon' => 'book',
                'teacher' => trim($kelas->pembuat->nama_lengkap.' '.$kelas->pembuat->gelar),
                'completed' => $kelas->tugas_selesai + $kelas->ujian_selesai,
                'total' => $kelas->tugas_count + $kelas->ujian_count,
                'tasks' => $kelas->tugas_count + $kelas->ujian_count - $kelas->tugas_selesai - $kelas->ujian_selesai,
            ]);
    }

    public function dashboard(User $user): array
    {
        $rooms = KelasMapel::query()->accessibleTo($user)->where('status', 'aktif')->select('id');
        $tasks = Tugas::query()->whereIn('kelas_mapel_id', $rooms);
        $exams = Ujian::query()->whereIn('kelas_mapel_id', $rooms);
        $counts = [
            ['total' => (clone $tasks)->count(), 'completed' => $this->completedTasks(clone $tasks, $user)->count()],
        ];
        foreach (['UH', 'US'] as $type) {
            $query = (clone $exams)->where('jenis_ujian', $type);
            $counts[] = ['total' => (clone $query)->count(), 'completed' => $query
                ->whereHas('penilaian', fn ($q) => $q->where('siswa_id', $user->siswa?->id ?? 0))->count()];
        }
        $summaries = collect(config('learning.summaries'))->values()
            ->map(fn ($style, $index) => [...$style, ...$counts[$index]])->all();
        $unfinished = (clone $tasks)->whereDoesntHave('penilaian', fn ($q) => $q->where('siswa_id', $user->siswa?->id ?? 0))
            ->whereDoesntHave('pengumpulan', fn ($q) => $q->where('siswa_id', $user->siswa?->id ?? 0)->whereNotNull('submitted_at'));
        $activities = [];
        foreach (['deadline' => '>=', 'susulan' => '<'] as $key => $operator) {
            $activities[$key] = (clone $unfinished)->where('deadline', $operator, now())
                ->with('kelasMapel:id,nama_kelas_mapel')->orderBy('deadline')->limit(50)->get()
                ->map(fn (Tugas $task) => ['id' => $task->id, 'kelas_mapel_id' => $task->kelas_mapel_id, 'subject' => $task->kelasMapel->nama_kelas_mapel,
                    'title' => $task->judul, 'due' => $task->deadline->format('d M Y H:i'),
                    'icon' => 'clipboard', 'color' => 'bg-teal-50 text-teal-700']);
        }
        $jadwal = Jadwal::query()->whereIn('kelas_mapel_id', $rooms)
            ->with('kelasMapel:id,nama_kelas_mapel')->orderBy('hari')->orderBy('jam_mulai')->get();

        return ['summaries' => $summaries, 'activities' => $activities, 'subjects' => $this->subjects($user), 'jadwal' => $jadwal];
    }

    public function tasks(User $user, KelasMapel $kelas): Collection
    {
        $siswaId = $user->siswa?->id ?? 0;
        $tasks = $kelas->tugas()->with([
            'penilaian' => fn ($q) => $q->where('siswa_id', $siswaId),
            'pengumpulan' => fn ($q) => $q->where('siswa_id', $siswaId)->whereNotNull('submitted_at'),
        ])->orderBy('deadline')->get()->map(fn (Tugas $task) => [
            'id' => 'tugas-'.$task->id, 'title' => $task->judul,
            'details' => ['Deadline: '.$task->deadline->format('d M Y H:i'), $task->deskripsi ?? ''],
            'completed' => $task->penilaian->isNotEmpty() || $task->pengumpulan->isNotEmpty(), 'type' => 'tugas',
        ]);
        $exams = $kelas->ujian()->with(['penilaian' => fn ($q) => $q->where('siswa_id', $siswaId)])
            ->orderBy('tanggal')->get()->map(fn (Ujian $exam) => [
                'id' => 'ujian-'.$exam->id, 'title' => $exam->judul,
                'details' => ['Tanggal: '.$exam->tanggal->format('d M Y'), $exam->keterangan ?? ''],
                'completed' => $exam->penilaian->isNotEmpty(), 'type' => $exam->jenis_ujian === 'UH' ? 'harian' : 'semester',
            ]);

        return $tasks->concat($exams)->values();
    }

    private function completedTasks(Builder $query, User $user): Builder
    {
        return $query->where(fn ($q) => $q
            ->whereHas('penilaian', fn ($n) => $n->where('siswa_id', $user->siswa?->id ?? 0))
            ->orWhereHas('pengumpulan', fn ($p) => $p->where('siswa_id', $user->siswa?->id ?? 0)->whereNotNull('submitted_at')));
    }
}
