<?php

namespace App\Http\Controllers;

use App\Models\KelasMapel;
use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use App\Services\AcademicAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PengumpulanTugasController extends Controller
{
    public function index(Request $request, KelasMapel $kelasMapel, Tugas $tugas): JsonResponse
    {
        abort_unless($tugas->kelas_mapel_id === $kelasMapel->id, 404);
        Gate::authorize('view', $kelasMapel);
        $query = $tugas->pengumpulan()->with('siswa:id,nama_lengkap,nis');
        if ($request->user()->role === 'siswa') {
            $query->where('siswa_id', $request->user()->siswa->id);
        }

        $submissions = $query->orderByDesc('submitted_at')->paginate(50);
        $grades = $tugas->penilaian()->whereIn('siswa_id', $submissions->getCollection()->pluck('siswa_id'))
            ->with('penilai:id,nama_lengkap')->get()->keyBy('siswa_id');
        $submissions->getCollection()->each(function (PengumpulanTugas $submission) use ($grades): void {
            $submission->setRelation('penilaian', $grades->get($submission->siswa_id));
        });

        return response()->json(['data' => $submissions]);
    }

    public function store(Request $request, KelasMapel $kelasMapel, Tugas $tugas, AcademicAccess $access): JsonResponse
    {
        abort_unless($tugas->kelas_mapel_id === $kelasMapel->id, 404);
        Gate::authorize('submit', $kelasMapel);
        $file = $request->file('file');
        $uploadError = $file instanceof UploadedFile ? $file->getError() : null;
        $data = $request->validate([
            'file' => ['bail', 'required_without:catatan_siswa', 'nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,zip', 'max:10240'],
            'catatan_siswa' => ['required_without:file', 'nullable', 'string', 'max:10000'],
        ], [
            'file.required_without' => 'Pilih berkas tugas atau tulis jawaban sebelum mengumpulkan.',
            'file.uploaded' => match ($uploadError) {
                UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara upload server tidak tersedia. Hubungi pengelola SINTAS untuk memperbaiki konfigurasi PHP.',
                UPLOAD_ERR_CANT_WRITE => 'Server tidak dapat menulis berkas. Hubungi pengelola SINTAS untuk memeriksa penyimpanan server.',
                UPLOAD_ERR_PARTIAL => 'Berkas hanya terkirim sebagian. Pilih ulang berkas dan coba kirim lagi.',
                default => 'Berkas gagal diterima server. Periksa ukuran berkas dan batas upload PHP, lalu pilih ulang berkas.',
            },
            'file.file' => 'Berkas tugas tidak valid. Silakan pilih ulang berkas.',
            'file.mimes' => 'Format berkas harus PDF, Word, JPG, PNG, atau ZIP.',
            'file.max' => 'Ukuran berkas tugas maksimal 10 MB.',
            'catatan_siswa.required_without' => 'Tulis jawaban atau pilih berkas tugas. Catatan boleh kosong jika ada berkas.',
            'catatan_siswa.max' => 'Jawaban atau catatan maksimal 10.000 karakter.',
        ]);
        $newPath = null;
        $oldPath = null;
        try {
            $submission = $access->write($kelasMapel, function () use ($request, $tugas, $data, &$newPath, &$oldPath): PengumpulanTugas {
                $siswa = $request->user()->siswa;
                if ($tugas->penilaian()->where('siswa_id', $siswa->id)->exists()) {
                    throw ValidationException::withMessages(['file' => 'Tugas sudah dinilai dan tidak dapat dikumpulkan ulang.']);
                }
                $existing = $tugas->pengumpulan()->where('siswa_id', $siswa->id)->first();
                $oldPath = $existing?->file_path;
                $newPath = $request->file('file')?->store('pengumpulan/'.$siswa->id, 'local');
                if ($request->hasFile('file') && ! $newPath) {
                    throw ValidationException::withMessages(['file' => 'Berkas gagal disimpan.']);
                }

                return $tugas->pengumpulan()->updateOrCreate(['siswa_id' => $siswa->id], [
                    'file_path' => $newPath ?: $oldPath,
                    'catatan_siswa' => $data['catatan_siswa'] ?? null,
                    'submitted_at' => now(),
                    'status' => now()->greaterThan($tugas->fresh()->deadline) ? 'terlambat' : 'dikumpulkan',
                ]);
            }, 'submit');
        } catch (\Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }
        if ($newPath && $oldPath && $newPath !== $oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return response()->json(['data' => $submission], 201);
    }

    public function download(Request $request, PengumpulanTugas $pengumpulan): StreamedResponse
    {
        Gate::authorize('view', $pengumpulan->tugas->kelasMapel);
        if ($request->user()->role === 'siswa') {
            abort_unless($request->user()->siswa->id === $pengumpulan->siswa_id, 403);
        }
        abort_unless($pengumpulan->file_path && Storage::disk('local')->exists($pengumpulan->file_path), 404);

        return Storage::disk('local')->download($pengumpulan->file_path);
    }
}
