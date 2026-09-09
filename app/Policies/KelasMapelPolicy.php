<?php

namespace App\Policies;

use App\Models\KelasMapel;
use App\Models\User;

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

    public function create(User $user): bool
    {
        return $user->status === 'aktif' && $user->role === 'guru' && $user->guru?->jenis_guru === 'guru_mapel';
    }

    public function view(User $user, KelasMapel $kelas): bool
    {
        return KelasMapel::query()->whereKey($kelas->id)->accessibleTo($user)->exists();
    }

    public function manageAcademic(User $user, KelasMapel $kelas): bool
    {
        return $kelas->status === 'aktif' && $this->create($user) && $this->view($user, $kelas);
    }

    public function update(User $user, KelasMapel $kelas): bool
    {
        return $this->create($user) && $user->guru->id === $kelas->guru_pembuat_id && $this->view($user, $kelas);
    }

    public function reviewMembers(User $user, KelasMapel $kelas): bool
    {
        return $kelas->status === 'aktif' && $this->update($user, $kelas);
    }

    public function submit(User $user, KelasMapel $kelas): bool
    {
        return $user->role === 'siswa' && $this->view($user, $kelas);
    }
}
