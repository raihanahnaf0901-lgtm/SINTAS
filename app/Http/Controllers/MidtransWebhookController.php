<?php

namespace App\Http\Controllers;

use App\Services\SchoolSubscriptionPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransWebhookController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, SchoolSubscriptionPayment $payments): JsonResponse
    {
        $payload = $request->validate([
            'order_id' => ['required', 'string', 'max:50'],
            'status_code' => ['required', 'string', 'max:3'],
            'gross_amount' => ['required', 'string', 'max:30'],
            'signature_key' => ['required', 'string', 'size:128'],
        ]);
        $payments->handleNotification($payload);

        return response()->json(['message' => 'Notifikasi pembayaran diterima.']);
    }
}
