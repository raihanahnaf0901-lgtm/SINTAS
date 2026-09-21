<?php

namespace App\Http\Controllers;

use App\Models\KelasMapel;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Services\AcademicAccess;
use App\Services\AcademicNotification;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UjianController extends Controller
{
    public function index(Request $request, KelasMapel $kelasMapel): JsonResponse
    {
        Gate::authorize('view', $kelasMapel);
        $query = $kelasMapel->ujian()->orderBy('tanggal');
        if ($request->user()->role === 'siswa') {
            $query->with(['penilaian' => fn ($q) => $q->where('siswa_id', $request->user()->siswa->id)
                ->with('penilai:id,nama_lengkap')]);
        }

        return response()->json(['data' => $query->paginate(25)]);
    }

    public function grades(KelasMapel $kelasMapel, Ujian $ujian): JsonResponse
    {
        abort_unless($ujian->kelas_mapel_id === $kelasMapel->id, 404);
        Gate::authorize('manageAcademic', $kelasMapel);
        $students = Siswa::query()->select('id', 'nama_lengkap', 'nis')
            ->whereHas('anggotaKelas', fn ($q) => $q->where('kelas_mapel_id', $kelasMapel->id)->where('status', 'diterima'))
            ->orderBy('nama_lengkap')->orderBy('id')->paginate(50);
        $grades = $ujian->penilaian()->whereIn('siswa_id', $students->getCollection()->pluck('id'))
            ->with('penilai:id,nama_lengkap')->get()->keyBy('siswa_id');
        $students->getCollection()->each(function (Siswa $student) use ($grades): void {
            $student->setRelation('penilaian', $grades->get($student->id));
        });

        return response()->json(['data' => $students]);
    }

    public function store(Request $request, KelasMapel $kelasMapel, AcademicAccess $access, AcademicNotification $notifications): JsonResponse
    {
        Gate::authorize('manageAcademic', $kelasMapel);
        $data = $request->validate($this->rules());
        $ujian = $access->write($kelasMapel, function (KelasMapel $kelas) use ($request, $data, $notifications): Ujian {
            $ujian = $kelas->ujian()->create([...$data, 'guru_id' => $request->user()->guru->id]);
            $notifications->kelas($kelas, 'Jadwal ujian', $ujian->judul, 'ujian', ['ujian_id' => $ujian->id]);

            return $ujian;
        });

        return response()->json(['data' => $ujian], 201);
    }

    public function update(Request $request, KelasMapel $kelasMapel, Ujian $ujian, AcademicAccess $access): JsonResponse
    {
        abort_unless($ujian->kelas_mapel_id === $kelasMapel->id, 404);
        Gate::authorize('manageAcademic', $kelasMapel);
        $data = $request->validate($this->rules());
        $access->write($kelasMapel, fn () => $ujian->update($data));

        return response()->json(['data' => $ujian->refresh()]);
    }

    private function rules(): array
    {
        return ['judul' => ['required', 'string', 'max:255'], 'jenis_ujian' => ['required', Rule::in(['UH', 'US'])],
            'tanggal' => ['required', 'date_format:Y-m-d'], 'keterangan' => ['nullable', 'string', 'max:20000'],
            'google_form_url' => ['bail', 'nullable', 'string', 'max:2048', 'url:https',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $url = parse_url($value);
                    $path = $url['path'] ?? '';
                    $isForm = match (strtolower($url['host'] ?? '')) {
                        'forms.gle' => preg_match('~\A/[A-Za-z0-9_-]+/?\z~', $path) === 1,
                        'docs.google.com' => preg_match('~\A/forms/(?:u/[0-9]+/)?d/(?:e/)?[A-Za-z0-9_-]+/viewform/?\z~', $path) === 1,
                        default => false,
                    };
                    if (! $isForm || isset($url['user']) || isset($url['pass']) || ($url['port'] ?? 443) !== 443) {
                        $fail('Gunakan link responden Google Form dari forms.gle atau docs.google.com/forms/.../viewform, bukan link edit.');
                    }
                },
            ]];
    }
}
