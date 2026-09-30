<?php

namespace App\Http\Controllers;

use App\Models\PembayaranSekolah;
use App\Models\Sekolah;
use App\Services\SchoolSubscriptionPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PembayaranSekolahController extends Controller
{
    public function store(Request $request, Sekolah $sekolah, SchoolSubscriptionPayment $payments): JsonResponse
    {
        Gate::authorize('pay', $sekolah);
        $data = $request->validate(['plan_code' => ['required', 'string', 'max:64']]);
        $payment = $payments->createCheckout($sekolah, $data['plan_code']);

        return response()->json(['data' => $payment], 201);
    }

    public function synchronize(Request $request, PembayaranSekolah $pembayaran, SchoolSubscriptionPayment $payments): JsonResponse
    {
        Gate::authorize('pay', $pembayaran->sekolah);

        return response()->json(['data' => $payments->synchronize($pembayaran)]);
    }
}
