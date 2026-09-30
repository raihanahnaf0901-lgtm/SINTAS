<?php

namespace App\Services;

use App\Models\KeanggotaanSekolah;
use App\Models\Sekolah;
use Illuminate\Http\Request;

class SchoolContext
{
    public function membership(Request $request): ?KeanggotaanSekolah
    {
        $guru = $request->user()?->guru;
        if (! $guru) {
            return null;
        }

        $memberships = $guru->keanggotaanSekolah()->with('sekolah');
        if ($request->filled('sekolah_id')) {
            $data = $request->validate(['sekolah_id' => ['required', 'integer', 'min:1']]);

            return $memberships->where('sekolah_id', $data['sekolah_id'])->firstOrFail();
        }

        $selectedId = $request->hasSession() ? $request->session()->get('active_school_id') : null;
        if ($selectedId !== null) {
            $selected = (clone $memberships)->where('sekolah_id', $selectedId)->first();
            if ($selected) {
                return $selected;
            }
        }

        return $memberships->orderByRaw("CASE status WHEN 'diterima' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->orderBy('id')->first();
    }

    public function school(Request $request): ?Sekolah
    {
        $membership = $this->membership($request);

        return $membership?->status === 'diterima' && $membership->sekolah->status === 'aktif'
            ? $membership->sekolah : null;
    }
}
