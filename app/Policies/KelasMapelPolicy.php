<?php

namespace App\Policies;

use App\Models\KelasMapel;
use App\Models\Sekolah;
use App\Models\User;
use App\Services\SchoolContext;

class KelasMapelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === 'aktif' && match ($user->role) {
            'siswa' => $user->siswa !== null,
            'guru' => $user->guru !== null,
            default => false,
        };
    }

    public function create(User $user, ?Sekolah $sekolah = null): bool
    {
        $school = $sekolah ?? app(SchoolContext::class)->school(request());

        return $school !== null && $school->hasActiveSubscription()
            && $user->guru?->sekolahAktif($school->id) !== null
            && ($user->isSchoolAdmin($school) || $this->isSubjectTeacher($user, $school->id));
    }

    public function view(User $user, KelasMapel $kelas): bool
    {
        return KelasMapel::query()->whereKey($kelas->id)->accessibleTo($user)->exists();
    }

    public function manageAcademic(User $user, KelasMapel $kelas): bool
    {
        return $kelas->status === 'aktif' && $this->view($user, $kelas)
            && ($this->administersSchool($user, $kelas)
                || ($this->isSubjectTeacher($user, $kelas->sekolah_id)
                    && $kelas->whitelist()->where('guru_id', $user->guru->id)->where('status', 'aktif')->exists()));
    }

    public function update(User $user, KelasMapel $kelas): bool
    {
        return $this->view($user, $kelas) && ($this->administersSchool($user, $kelas)
            || ($this->isSubjectTeacher($user, $kelas->sekolah_id) && $user->guru->id === $kelas->guru_pembuat_id));
    }

    public function reviewMembers(User $user, KelasMapel $kelas): bool
    {
        return $kelas->status === 'aktif' && $this->update($user, $kelas);
    }

    public function submit(User $user, KelasMapel $kelas): bool
    {
        return $user->role === 'siswa' && $this->view($user, $kelas);
    }

    public function viewRekap(User $user, KelasMapel $kelas): bool
    {
        return $this->view($user, $kelas) && ($user->role === 'siswa'
            || $this->administersSchool($user, $kelas) || $this->isSubjectTeacher($user, $kelas->sekolah_id));
    }

    private function administersSchool(User $user, KelasMapel $kelas): bool
    {
        return $kelas->sekolah_id !== null && $user->isSchoolAdmin($kelas->sekolah);
    }

    private function isSubjectTeacher(User $user, ?int $schoolId = null): bool
    {
        return $user->status === 'aktif' && $user->role === 'guru'
            && $user->guru?->jenisDiSekolah($schoolId) === 'guru_mapel';
    }
}
