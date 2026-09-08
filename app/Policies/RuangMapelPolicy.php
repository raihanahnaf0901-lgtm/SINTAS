<?php

namespace App\Policies;

use App\Models\RuangMapel;
use App\Models\User;

class RuangMapelPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return match ($user->role) {
            'siswa' => $user->siswa()->exists(),
            'guru' => $user->guru()->exists(),
            default => false,
        };
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RuangMapel $ruangMapel): bool
    {
        if ($user->role === 'guru') {
            return $user->guru()->whereKey($ruangMapel->guru_id)->exists();
        }

        if ($user->role === 'siswa') {
            return $user->siswa()->where('kelas_id', $ruangMapel->kelas_id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === 'guru' && $user->guru()->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RuangMapel $ruangMapel): bool
    {
        return $user->role === 'guru'
            && $user->guru()->whereKey($ruangMapel->guru_id)->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RuangMapel $ruangMapel): bool
    {
        return $this->update($user, $ruangMapel);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RuangMapel $ruangMapel): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RuangMapel $ruangMapel): bool
    {
        return false;
    }
}
