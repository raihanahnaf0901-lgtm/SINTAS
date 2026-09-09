<?php

namespace App\Services;

use App\Models\KelasMapel;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AcademicAccess
{
    public function write(KelasMapel $kelas, Closure $callback, string $ability = 'manageAcademic'): mixed
    {
        return DB::transaction(function () use ($kelas, $callback, $ability): mixed {
            $locked = KelasMapel::query()->lockForUpdate()->findOrFail($kelas->id);
            Gate::authorize($ability, $locked);

            return $callback($locked);
        });
    }
}
