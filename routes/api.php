<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

Route::post('/login/siswa', [AuthController::class, 'loginSiswa']);
Route::post('/login/guru', [AuthController::class, 'loginGuru']);