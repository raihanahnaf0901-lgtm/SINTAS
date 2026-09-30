<?php

namespace App\Services;

use App\Models\Guru;
use App\Models\KelasMapel;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AcademicAccess
{
    public function write(KelasMapel $kelas, Closure $callback, string $ability = 'manageAcademic'): mixed
    {
        return DB::transaction(function () use ($kelas, $callback, $ability): mixed {
            $user = Auth::user();
            if ($user?->role === 'guru' && $user->guru !== null) {
                $user->setRelation('guru', Guru::query()->lockForUpdate()->findOrFail($user->guru->id));
            }
            $locked = KelasMapel::query()->lockForUpdate()->findOrFail($kelas->id);
            Gate::authorize($ability, $locked);

            return $callback($locked);
        });
    }
}
