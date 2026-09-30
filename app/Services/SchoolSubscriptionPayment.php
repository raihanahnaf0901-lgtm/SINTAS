<?php

namespace App\Services;

use App\Models\Guru;
use App\Models\KeanggotaanSekolah;
use App\Models\KelasMapel;
use App\Models\PembayaranSekolah;
use App\Models\Sekolah;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class SchoolSubscriptionPayment
{
    public function __construct(private MidtransPayment $gateway) {}

    /** @return array{name: string, amount: int, duration_days: int|null, duration_months: int|null} */
    public function plan(string $planCode): array
    {
        $plan = config('school-subscriptions.plans', [])[$planCode] ?? null;
        $days = is_array($plan) ? ($plan['duration_days'] ?? null) : null;
        $months = is_array($plan) ? ($plan['duration_months'] ?? null) : null;
        $validDays = is_int($days) && $days > 0 && $months === null;
        $validMonths = is_int($months) && $months > 0 && $days === null;

        if (! is_array($plan) || ! is_string($plan['name'] ?? null) || trim($plan['name']) === ''
            || ! is_int($plan['amount'] ?? null) || $plan['amount'] < 1
            || (! $validDays && ! $validMonths)) {
            throw ValidationException::withMessages(['plan_code' => 'Paket langganan belum tersedia atau tidak valid.']);
        }

        return [
            'name' => $plan['name'], 'amount' => $plan['amount'],
            'duration_days' => $days, 'duration_months' => $months,
        ];
    }

    public function createCheckout(Sekolah $sekolah, string $planCode): PembayaranSekolah
    {
        $plan = $this->plan($planCode);
        $this->gateway->ensureConfigured();
        $isNew = false;

        $payment = DB::transaction(function () use ($sekolah, $planCode, $plan, &$isNew): PembayaranSekolah {
            Guru::query()->lockForUpdate()->findOrFail($sekolah->admin_guru_id);
            $school = Sekolah::query()->lockForUpdate()->findOrFail($sekolah->id);
            if ($school->status === 'nonaktif') {
                throw ValidationException::withMessages(['payment' => 'Sekolah dinonaktifkan. Hubungi pengelola SINTAS.']);
            }

            $pending = PembayaranSekolah::query()->where('sekolah_id', $school->id)
                ->whereIn('status', ['creating', 'pending'])->lockForUpdate()->first();

            if ($pending !== null) {
                if ($pending->plan_code !== $planCode) {
                    throw ValidationException::withMessages(['payment' => 'Selesaikan transaksi yang sudah ada sebelum memilih paket lain.']);
                }
                if (! $this->gateway->validCheckoutUrl($pending->checkout_url)) {
                    throw ValidationException::withMessages(['payment' => 'Transaksi sebelumnya sedang dikonfirmasi. Sinkronisasi status pembayaran terlebih dahulu; jangan membayar ulang.']);
                }

                return $pending;
            }

            $isNew = true;

            return PembayaranSekolah::query()->create([
                'sekolah_id' => $school->id, 'order_id' => 'SINTAS-'.Str::ulid(),
                'plan_code' => $planCode, 'plan_name' => $plan['name'],
                'amount' => $plan['amount'], 'duration_days' => $plan['duration_days'],
                'duration_months' => $plan['duration_months'], 'status' => 'creating',
            ]);
        });

        if (! $isNew) {
            return $payment;
        }

        $owner = Guru::query()->with('user')->findOrFail($sekolah->admin_guru_id);

        try {
            $response = $this->gateway->createTransaction($payment, $owner->user->email, $owner->nama_lengkap);
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['payment' => 'Koneksi pembayaran terputus. Transaksi disimpan; sinkronisasi status sebelum mencoba kembali.']);
        }

        if (! $response->successful()) {
            if (in_array($response->status(), [400, 401, 403, 422], true)) {
                PembayaranSekolah::query()->whereKey($payment->id)->where('status', 'creating')->update(['status' => 'failed']);
            }
            throw ValidationException::withMessages(['payment' => 'Halaman pembayaran belum berhasil dibuat. Periksa status transaksi atau hubungi pengelola SINTAS.']);
        }

        $url = $response->json('redirect_url');
        if (! $this->gateway->validCheckoutUrl($url)) {
            throw ValidationException::withMessages(['payment' => 'Tautan pembayaran tidak valid. Transaksi perlu diperiksa oleh pengelola SINTAS.']);
        }

        PembayaranSekolah::query()->whereKey($payment->id)->where('status', 'creating')
            ->update(['status' => 'pending', 'checkout_url' => $url]);

        return $payment->refresh();
    }

    public function synchronize(PembayaranSekolah $payment): PembayaranSekolah
    {
        $status = $this->gateway->status($payment->order_id);
        if ($status === null) {
            return $payment->refresh();
        }

        $this->applyVerifiedStatus($payment, $status);

        return $payment->refresh();
    }

    /** @param array<string, mixed> $payload */
    public function handleNotification(array $payload): void
    {
        abort_unless($this->gateway->verifySignature($payload), 403, 'Notifikasi pembayaran tidak sah.');
        $payment = PembayaranSekolah::query()->where('order_id', $payload['order_id'])->first();
        if ($payment !== null) {
            $this->synchronize($payment);
        }
    }

    /** @param array<string, mixed> $status */
    private function applyVerifiedStatus(PembayaranSekolah $payment, array $status): void
    {
        $amount = $status['gross_amount'] ?? null;
        $amountMatches = is_string($amount) && preg_match('/^\d+(?:\.0{1,2})?$/D', $amount)
            && ltrim(explode('.', $amount)[0], '0') === (string) $payment->amount;
        abort_unless(($status['order_id'] ?? null) === $payment->order_id
            && ($status['currency'] ?? null) === 'IDR' && $amountMatches, 422, 'Data pembayaran tidak cocok dengan tagihan sekolah.');

        $gatewayStatus = $status['transaction_status'] ?? null;
        abort_unless(is_string($gatewayStatus), 503, 'Status dari layanan pembayaran tidak valid.');

        DB::transaction(function () use ($payment, $status, $gatewayStatus): void {
            $ownerId = Sekolah::query()->findOrFail($payment->sekolah_id)->admin_guru_id;
            $owner = Guru::query()->with('user')->lockForUpdate()->findOrFail($ownerId);
            $school = Sekolah::query()->lockForUpdate()->findOrFail($payment->sekolah_id);
            $lockedPayment = PembayaranSekolah::query()->lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->activated_at !== null || $lockedPayment->status === 'paid') {
                return;
            }

            $paid = (string) ($status['status_code'] ?? '') === '200'
                && in_array($gatewayStatus, ['settlement', 'capture'], true)
                && (! isset($status['fraud_status']) || $status['fraud_status'] === 'accept')
                && ($gatewayStatus !== 'capture' || ($status['fraud_status'] ?? null) === 'accept');

            $lockedPayment->gateway_status = $gatewayStatus;
            $lockedPayment->transaction_id = is_string($status['transaction_id'] ?? null) ? $status['transaction_id'] : null;

            if (! $paid) {
                $lockedPayment->status = match ($gatewayStatus) {
                    'deny', 'failure' => 'failed',
                    'cancel' => 'cancelled',
                    'expire' => 'expired',
                    default => $lockedPayment->status,
                };
                $lockedPayment->save();

                return;
            }

            $lockedPayment->status = 'paid';
            $lockedPayment->paid_at = now();
            $lockedPayment->save();

            if ($school->status === 'nonaktif') {
                return;
            }

            $membership = KeanggotaanSekolah::query()->where('guru_id', $owner->id)
                ->where('sekolah_id', $school->id)->lockForUpdate()->firstOrFail();
            $membership->update(['status' => 'diterima', 'reviewed_at' => now(), 'reviewed_by' => $owner->id]);
            KelasMapel::query()->where('guru_pembuat_id', $owner->id)->whereNull('sekolah_id')
                ->update(['sekolah_id' => $school->id]);

            $start = $school->subscription_ends_at?->isFuture() ? $school->subscription_ends_at->copy() : now();
            $end = $lockedPayment->duration_months !== null
                ? $start->addMonthsNoOverflow($lockedPayment->duration_months)
                : $start->addDays($lockedPayment->duration_days);
            $school->update(['status' => 'aktif', 'subscription_ends_at' => $end]);
            $owner->user->assignRole(Role::findOrCreate('admin_sekolah', 'web'));
            $lockedPayment->update(['activated_at' => now()]);
        });
    }
}
