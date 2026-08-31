<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\Guru;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    // API Login Khusus Siswa
    public function loginSiswa(Request $request)
    {
        $request->validate([
            'nama_lengkap' => 'required|string',
            'nis'          => 'required|string',
            'kelas_id'     => 'required|integer',
        ]);

        // Pencarian data yang presisi di database
        $siswa = Siswa::with('kelas')
            ->where('nama_lengkap', $request->nama_lengkap)
            ->where('nis', $request->nis)
            ->where('kelas_id', $request->kelas_id)
            ->first();

        if (!$siswa) {
            return response()->json([
                'success' => false,
                'message' => 'Data siswa tidak cocok atau tidak ditemukan.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login Siswa Berhasil!',
            'role'    => 'siswa',
            'data'    => $siswa
        ], 200);
    }

    // API Login Khusus Guru
    public function loginGuru(Request $request)
    {
        $request->validate([
            'nama'  => 'required|string',
            'nip'   => 'required|string',
        ]);

        $query = Guru::where('nama', $request->nama)
                     ->where('nip', $request->nip);

        // Jika guru mengisi gelar, lakukan pengecekan gelar juga
        if ($request->filled('gelar')) {
            $query->where('gelar', $request->gelar);
        }

        $guru = $query->first();

        if (!$guru) {
            return response()->json([
                'success' => false,
                'message' => 'Data guru tidak cocok atau tidak ditemukan.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login Guru Berhasil!',
            'role'    => 'guru',
            'data'    => $guru
        ], 200);
    }
}