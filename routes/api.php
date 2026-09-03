<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;

Route::get('/dashboard/siswa/{siswaId}', [DashboardController::class, 'getSiswaDashboard']);
Route::post('/login/siswa', [AuthController::class, 'loginSiswa']);
Route::post('/login/guru', [AuthController::class, 'loginGuru']);
