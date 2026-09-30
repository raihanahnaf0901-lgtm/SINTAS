<?php

namespace App\Policies;

use App\Models\Sekolah;
use App\Models\User;

class SekolahPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === 'aktif' && $user->role === 'guru' && $user->guru !== null;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function manage(User $user, Sekolah $sekolah): bool
    {
        return $this->viewAny($user) && $user->isSchoolAdmin($sekolah);
    }

    public function pay(User $user, Sekolah $sekolah): bool
    {
        return $this->viewAny($user)
            && $user->guru->id === $sekolah->admin_guru_id
            && $sekolah->status !== 'nonaktif';
    }
}
