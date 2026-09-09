<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->notifikasi()->latest()->paginate(25),
            'belum_dibaca' => $request->user()->notifikasi()->whereNull('read_at')->count()]);
    }

    public function update(Request $request, int $notifikasi): JsonResponse
    {
        $notification = $request->user()->notifikasi()->findOrFail($notifikasi);
        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(['data' => $notification]);
    }
}
