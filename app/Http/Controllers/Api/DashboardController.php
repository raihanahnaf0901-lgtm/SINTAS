<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Penilaian;
use App\Models\Tugas;

class DashboardController extends Controller
{
    public function getSiswaDashboard($siswaId)
    {
        // 1. Hitung Ringkasan Progress Tugas & Ulangan
        $ringkasan = [
            'tugas' => [
                'selesai' => Penilaian::where('siswa_id', $siswaId)
                    ->whereHas('tugas', fn($q) => $q->where('jenis', 'Tugas'))
                    ->where('status', 'Selesai')->count(),
                'total' => Penilaian::where('siswa_id', $siswaId)
                    ->whereHas('tugas', fn($q) => $q->where('jenis', 'Tugas'))->count(),
            ],
            'ulangan_harian' => [
                'selesai' => Penilaian::where('siswa_id', $siswaId)
                    ->whereHas('tugas', fn($q) => $q->where('jenis', 'Ulangan Harian'))
                    ->where('status', 'Selesai')->count(),
                'total' => Penilaian::where('siswa_id', $siswaId)
                    ->whereHas('tugas', fn($q) => $q->where('jenis', 'Ulangan Harian'))->count(),
            ],
            'ulangan_semester' => [
                'selesai' => Penilaian::where('siswa_id', $siswaId)
                    ->whereHas('tugas', fn($q) => $q->where('jenis', 'Ulangan Semester'))
                    ->where('status', 'Selesai')->count(),
                'total' => Penilaian::where('siswa_id', $siswaId)
                    ->whereHas('tugas', fn($q) => $q->where('jenis', 'Ulangan Semester'))->count(),
            ],
        ];

        // 2. Ambil Daftar Deadline Terdekat
        $deadlineList = Penilaian::with(['tugas.jadwal.mapel'])
            ->where('siswa_id', $siswaId)
            ->where('status', 'Belum')
            ->get();

        return response()->json([
            'success'   => true,
            'ringkasan' => $ringkasan,
            'deadline'  => $deadlineList
        ], 200);
    }
}
