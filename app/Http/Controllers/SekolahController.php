<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSekolahRequest;
use App\Models\Guru;
use App\Models\KeanggotaanSekolah;
use App\Models\Sekolah;
use App\Services\MidtransPayment;
use App\Services\SchoolContext;
use App\Services\SchoolSubscriptionPayment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SekolahController extends Controller
{
    public function page(): Response
    {
        Gate::authorize('viewAny', Sekolah::class);

        return Inertia::render('School');
    }

    public function index(Request $request, SchoolContext $context): JsonResponse
    {
        Gate::authorize('viewAny', Sekolah::class);
        $guru = $request->user()->guru;
        $memberships = $guru->keanggotaanSekolah()->with('sekolah')->orderBy('id')->get();
        $membership = $context->membership($request);
        $school = $membership?->sekolah;
        $owner = $school?->admin_guru_id === $guru->id;
        $serialize = fn (KeanggotaanSekolah $member): array => [
            'id' => $member->id, 'status' => $member->status,
            'jenis_guru' => $member->jenis_guru ?? $guru->jenis_guru,
            'sekolah' => [...$member->sekolah->only(['id', 'nama_sekolah', 'npsn', 'alamat', 'status', 'subscription_ends_at']),
                'kode_sekolah' => $member->sekolah->admin_guru_id === $guru->id || $member->status === 'diterima'
                    ? $member->sekolah->kode_sekolah : null,
                'subscription_active' => $member->sekolah->hasActiveSubscription(),
                'is_admin' => $request->user()->isSchoolAdmin($member->sekolah),
                'is_owner' => $member->sekolah->admin_guru_id === $guru->id],
        ];

        return response()->json([
            'membership' => $membership ? $serialize($membership) : null,
            'memberships' => $memberships->map($serialize)->values(),
            'can_register_school' => true,
            'can_join_school' => ! $memberships->contains(fn (KeanggotaanSekolah $member): bool => $member->sekolah->admin_guru_id !== $guru->id && in_array($member->status, ['pending', 'diterima'], true)),
            'plans' => collect(config('school-subscriptions.plans', []))->map(
                fn (array $plan, string $code): array => [
                    'code' => $code, 'name' => $plan['name'], 'amount' => $plan['amount'],
                    'duration_days' => $plan['duration_days'] ?? null, 'duration_months' => $plan['duration_months'] ?? null,
                ]
            )->values(),
            'payment_ready' => filled(config('school-subscriptions.server_key')),
            'sandbox' => (bool) config('school-subscriptions.sandbox', true),
            'payments' => $owner ? $school->payments()->latest('id')->limit(10)->get([
                'id', 'order_id', 'status', 'amount', 'plan_name', 'duration_days', 'duration_months', 'checkout_url', 'created_at',
            ]) : [],
        ]);
    }

    public function select(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Sekolah::class);
        $data = $request->validate(['sekolah_id' => ['required', 'integer', 'min:1']]);
        $member = $request->user()->guru->keanggotaanSekolah()->where('sekolah_id', $data['sekolah_id'])->firstOrFail();
        $request->session()->put('active_school_id', $member->sekolah_id);

        return response()->json(['message' => 'Sekolah pilihan diperbarui.']);
    }

    public function store(StoreSekolahRequest $request, MidtransPayment $gateway, SchoolSubscriptionPayment $payments): JsonResponse
    {
        $data = $request->validated();
        $gateway->ensureConfigured();
        $payments->plan($data['plan_code']);

        try {
            $school = DB::transaction(function () use ($request, $data): Sekolah {
                $guru = Guru::query()->lockForUpdate()->findOrFail($request->user()->guru->id);
                $school = Sekolah::query()->create([
                    'nama_sekolah' => $data['nama_sekolah'], 'npsn' => $data['npsn'],
                    'alamat' => $data['alamat'] ?? null, 'admin_guru_id' => $guru->id,
                ]);
                $guru->keanggotaanSekolah()->create([
                    'sekolah_id' => $school->id, 'status' => 'pending', 'jenis_guru' => 'guru_mapel',
                    'requested_at' => now(), 'reviewed_at' => null, 'reviewed_by' => null,
                ]);

                return $school;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['npsn' => 'Sekolah ini sudah didaftarkan. Muat ulang halaman dan periksa pendaftaran Anda.']);
        }

        $request->session()->put('active_school_id', $school->id);
        $payment = $payments->createCheckout($school, $data['plan_code']);

        return response()->json(['data' => $school, 'payment' => $payment,
            'message' => 'Pendaftaran tersimpan. Selesaikan pembayaran sekolah ini agar aktif.'], 201);
    }

    public function join(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Sekolah::class);
        $data = $request->validate(['kode_sekolah' => ['required', 'string', 'max:16']]);
        $membership = DB::transaction(function () use ($request, $data): KeanggotaanSekolah {
            $guru = Guru::query()->lockForUpdate()->findOrFail($request->user()->guru->id);
            $existing = $guru->keanggotaanSekolah()->whereIn('status', ['pending', 'diterima'])
                ->whereHas('sekolah', fn ($query) => $query->where('admin_guru_id', '!=', $guru->id))->exists();
            if ($existing) {
                throw ValidationException::withMessages(['kode_sekolah' => 'Sebagai anggota Anda hanya dapat bergabung ke satu sekolah. Selesaikan atau batalkan permintaan yang masih menunggu.']);
            }
            $school = Sekolah::query()->where('kode_sekolah', strtoupper(trim($data['kode_sekolah'])))->lockForUpdate()->first();
            if (! $school || ! $school->hasActiveSubscription()) {
                throw ValidationException::withMessages(['kode_sekolah' => 'Kode sekolah tidak tersedia atau langganan sekolah belum aktif.']);
            }
            if ($school->admin_guru_id === $guru->id) {
                throw ValidationException::withMessages(['kode_sekolah' => 'Anda sudah menjadi pemilik sekolah ini.']);
            }

            return $guru->keanggotaanSekolah()->updateOrCreate(['sekolah_id' => $school->id], [
                'status' => 'pending', 'jenis_guru' => 'guru_mapel', 'requested_at' => now(),
                'reviewed_at' => null, 'reviewed_by' => null,
            ]);
        });
        $request->session()->put('active_school_id', $membership->sekolah_id);

        return response()->json(['data' => $membership, 'message' => 'Permintaan terkirim. Tunggu persetujuan admin sekolah.'], 201);
    }

    public function cancel(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Sekolah::class);
        $data = $request->validate(['sekolah_id' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($request, $data): void {
            $guru = Guru::query()->lockForUpdate()->findOrFail($request->user()->guru->id);
            $membership = $guru->keanggotaanSekolah()->where('sekolah_id', $data['sekolah_id'])->with('sekolah')->firstOrFail();
            if ($membership->status !== 'pending' || $membership->sekolah->admin_guru_id === $guru->id) {
                throw ValidationException::withMessages(['sekolah' => 'Keanggotaan ini tidak dapat dibatalkan melalui permintaan bergabung.']);
            }
            $membership->update(['status' => 'ditolak', 'reviewed_at' => now()]);
        });

        return response()->json(['message' => 'Permintaan bergabung dibatalkan. Anda dapat memilih sekolah lain.']);
    }
}
