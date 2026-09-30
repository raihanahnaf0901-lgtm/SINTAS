<?php

namespace App\Http\Middleware;

use App\Models\KelasMapel;
use App\Services\SchoolContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user()?->loadMissing(['guru', 'siswa.kelas']);
        $membership = app(SchoolContext::class)->membership($request);
        $school = $membership?->sekolah;
        $isAdmin = $school !== null && ($user?->isSchoolAdmin($school) ?? false);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'school' => $school ? [...$school->only(['id', 'nama_sekolah', 'status', 'subscription_ends_at']),
                    'kode_sekolah' => $membership->status === 'diterima' || $school->admin_guru_id === $user->guru->id ? $school->kode_sekolah : null,
                    'subscription_active' => $school->hasActiveSubscription(), 'is_admin' => $isAdmin,
                    'is_owner' => $school->admin_guru_id === $user->guru->id] : null,
                'schoolMembership' => $membership ? [
                    'status' => $membership->status, 'sekolah' => $school->only(['id', 'nama_sekolah']),
                ] : null,
                'canCreateClass' => $membership?->status === 'diterima' && ($user?->can('create', [KelasMapel::class, $school]) ?? false),
                'schoolTeacherType' => $membership?->jenis_guru ?? $user?->guru?->jenis_guru,
                'isSchoolAdmin' => $isAdmin,
            ],
            'unreadNotifications' => fn () => $request->user()?->notifikasi()->whereNull('read_at')->count() ?? 0,
            'pendingInvite' => fn () => $request->session()->get('kelas_invite'),
        ];
    }
}
