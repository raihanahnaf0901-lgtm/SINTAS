<?php

namespace App\Http\Controllers;

use App\Models\KelasMapel;
use App\Models\KomponenRekap;
use App\Models\KonfigurasiRekap;
use App\Models\NilaiRekapManual;
use App\Models\Siswa;
use App\Services\AcademicAccess;
use App\Services\RekapNilai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RekapController extends Controller
{
    public function index(Request $request, KelasMapel $kelasMapel): JsonResponse
    {
        Gate::authorize('view', $kelasMapel);
        $this->canRead($request);
        $query = $kelasMapel->konfigurasiRekap()->with('komponen');
        if ($request->user()->role === 'siswa') {
            $query->where('status', 'aktif');
        }

        return response()->json(['data' => $query->latest()->get()]);
    }

    public function store(Request $request, KelasMapel $kelasMapel, AcademicAccess $access): JsonResponse
    {
        Gate::authorize('manageAcademic', $kelasMapel);
        $data = $this->validateConfig($request);
        $config = $access->write($kelasMapel, function (KelasMapel $kelas) use ($request, $data): KonfigurasiRekap {
            if ($data['status'] === 'aktif') {
                $kelas->konfigurasiRekap()->where('status', 'aktif')->update(['status' => 'draft']);
            }
            $config = $kelas->konfigurasiRekap()->create([
                'nama_konfigurasi' => $data['nama_konfigurasi'], 'status' => $data['status'],
                'guru_id' => $request->user()->guru->id,
            ]);
            $config->komponen()->createMany($data['komponen']);

            return $config;
        });

        return response()->json(['data' => $config->load('komponen')], 201);
    }

    public function update(Request $request, KelasMapel $kelasMapel, KonfigurasiRekap $rekap, AcademicAccess $access): JsonResponse
    {
        abort_unless($rekap->kelas_mapel_id === $kelasMapel->id, 404);
        Gate::authorize('manageAcademic', $kelasMapel);
        $data = $this->validateConfig($request);
        $access->write($kelasMapel, function (KelasMapel $kelas) use ($rekap, $data): void {
            if ($data['status'] === 'aktif') {
                $kelas->konfigurasiRekap()->where('id', '!=', $rekap->id)->update(['status' => 'draft']);
            }
            $removed = $rekap->komponen()->whereNotIn('jenis', array_column($data['komponen'], 'jenis'));
            if ((clone $removed)->whereHas('nilaiManual')->exists()) {
                throw ValidationException::withMessages(['komponen' => 'Komponen berisi nilai manual tidak boleh dihapus. Gunakan bobot 0 bila tidak dipakai.']);
            }
            $removed->delete();
            $rekap->update(['nama_konfigurasi' => $data['nama_konfigurasi'], 'status' => $data['status']]);
            foreach ($data['komponen'] as $part) {
                $rekap->komponen()->updateOrCreate(['jenis' => $part['jenis']], $part);
            }
        });

        return response()->json(['data' => $rekap->refresh()->load('komponen')]);
    }

    public function manual(Request $request, KelasMapel $kelasMapel, KonfigurasiRekap $rekap, KomponenRekap $komponen, AcademicAccess $access): JsonResponse
    {
        abort_unless($rekap->kelas_mapel_id === $kelasMapel->id && $komponen->konfigurasi_rekap_id === $rekap->id, 404);
        Gate::authorize('manageAcademic', $kelasMapel);
        $data = $request->validate([
            'siswa_id' => ['required', 'integer', 'exists:siswa,id'],
            'nilai' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'catatan' => ['nullable', 'string', 'max:10000'],
        ]);
        $record = $access->write($kelasMapel, function (KelasMapel $kelas) use ($request, $komponen, $data): NilaiRekapManual {
            if ($komponen->fresh()->metode !== 'manual') {
                throw ValidationException::withMessages(['komponen' => 'Komponen ini tidak memakai metode manual.']);
            }
            if (! $kelas->anggota()->where('siswa_id', $data['siswa_id'])->where('status', 'diterima')->exists()) {
                throw ValidationException::withMessages(['siswa_id' => 'Siswa belum diterima di kelas ini.']);
            }

            return $komponen->nilaiManual()->updateOrCreate(['siswa_id' => $data['siswa_id']], [
                'nilai' => $data['nilai'], 'catatan' => $data['catatan'] ?? null,
                'dinilai_oleh' => $request->user()->guru->id, 'dinilai_at' => now(),
            ]);
        });

        return response()->json(['data' => $record]);
    }

    public function show(Request $request, KelasMapel $kelasMapel, KonfigurasiRekap $rekap, RekapNilai $calculator): JsonResponse
    {
        abort_unless($rekap->kelas_mapel_id === $kelasMapel->id, 404);
        Gate::authorize('view', $kelasMapel);
        $this->canRead($request);
        if ($request->user()->role === 'siswa') {
            abort_unless($rekap->status === 'aktif', 403);
        }
        $query = Siswa::query()->whereHas('anggotaKelas', fn ($q) => $q
            ->where('kelas_mapel_id', $kelasMapel->id)->where('status', 'diterima'));
        if ($request->user()->role === 'siswa') {
            $query->whereKey($request->user()->siswa->id);
        }
        $students = $query->orderBy('nama_lengkap')->paginate(50);
        $students->setCollection($calculator->calculate($rekap, $students->getCollection()));

        return response()->json(['data' => $students]);
    }

    private function canRead(Request $request): void
    {
        abort_if($request->user()->role === 'guru' && $request->user()->guru->jenis_guru !== 'guru_mapel', 403);
    }

    private function validateConfig(Request $request): array
    {
        $data = $request->validate([
            'nama_konfigurasi' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'aktif'])],
            'komponen' => ['required', 'array', 'min:1', 'max:3'],
            'komponen.*' => ['required', 'array:jenis,bobot,metode'],
            'komponen.*.jenis' => ['required', 'distinct', Rule::in(['tugas', 'UH', 'US'])],
            'komponen.*.bobot' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'komponen.*.metode' => ['required', Rule::in(['rata_rata', 'manual'])],
        ]);
        $total = array_sum(array_map(fn ($part) => (int) round((float) $part['bobot'] * 100), $data['komponen']));
        if ($total !== 10000) {
            throw ValidationException::withMessages(['komponen' => 'Total bobot harus tepat 100%.']);
        }

        return $data;
    }
}
