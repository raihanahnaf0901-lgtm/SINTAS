<?php

use App\Http\Controllers\AnggotaKelasController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\TugasController;
use App\Http\Controllers\Auth\OtpAuthController;
use App\Http\Controllers\JadwalController;
use App\Http\Controllers\KelasMapelController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PengumpulanTugasController;
use App\Http\Controllers\PenilaianController;
use App\Http\Controllers\RekapController;
use App\Http\Controllers\UjianController;
use App\Http\Controllers\WhitelistGuruKelasController;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['web', 'auth', EnsureActiveAccount::class])->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('master-data', [MasterDataController::class, 'index']);
    Route::post('mapel', [MasterDataController::class, 'mapel']);
    Route::post('kelas', [MasterDataController::class, 'kelas']);
    Route::patch('profil/siswa', [OtpAuthController::class, 'completeProfile']);
    Route::get('notifikasi', [NotifikasiController::class, 'index']);
    Route::patch('notifikasi/{notifikasi}/baca', [NotifikasiController::class, 'update']);
    Route::get('pengumpulan/{pengumpulan}/file', [PengumpulanTugasController::class, 'download']);
    Route::post('kelas-mapel/gabung', [AnggotaKelasController::class, 'store'])->middleware('throttle:10,1');
    Route::get('kelas-mapel', [KelasMapelController::class, 'index']);
    Route::post('kelas-mapel', [KelasMapelController::class, 'store']);

    Route::prefix('kelas-mapel/{kelasMapel}')->group(function (): void {
        Route::get('/', [KelasMapelController::class, 'show']);
        Route::patch('/', [KelasMapelController::class, 'update']);
        Route::get('whitelist', [WhitelistGuruKelasController::class, 'index']);
        Route::post('whitelist', [WhitelistGuruKelasController::class, 'store']);
        Route::get('anggota', [AnggotaKelasController::class, 'index']);
        Route::patch('anggota/{anggota}', [AnggotaKelasController::class, 'update']);
        Route::get('jadwal', [JadwalController::class, 'index']);
        Route::post('jadwal', [JadwalController::class, 'store']);
        Route::patch('jadwal/{jadwal}', [JadwalController::class, 'update']);
        Route::get('tugas', [TugasController::class, 'index']);
        Route::post('tugas', [TugasController::class, 'store']);
        Route::patch('tugas/{tugas}', [TugasController::class, 'update']);
        Route::get('tugas/{tugas}/pengumpulan', [PengumpulanTugasController::class, 'index']);
        Route::post('tugas/{tugas}/pengumpulan', [PengumpulanTugasController::class, 'store']);
        Route::get('ujian', [UjianController::class, 'index']);
        Route::post('ujian', [UjianController::class, 'store']);
        Route::patch('ujian/{ujian}', [UjianController::class, 'update']);
        Route::get('penilaian', [PenilaianController::class, 'index']);
        Route::put('penilaian', [PenilaianController::class, 'store']);
        Route::get('rekap', [RekapController::class, 'index']);
        Route::post('rekap', [RekapController::class, 'store']);
        Route::get('rekap/{rekap}', [RekapController::class, 'show']);
        Route::put('rekap/{rekap}', [RekapController::class, 'update']);
        Route::put('rekap/{rekap}/komponen/{komponen}/manual', [RekapController::class, 'manual']);
    });
});
