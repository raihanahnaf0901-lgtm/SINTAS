<?php

namespace Tests\Feature;

use App\Models\KelasMapel;
use App\Models\Siswa;
use App\Models\Tugas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SubjectsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_subject_data(): void
    {
        $this->get('/mata-pelajaran')->assertRedirectToRoute('login');
        $this->getJson('/mata-pelajaran/1')->assertUnauthorized();
    }

    public function test_subjects_stay_empty_until_membership_is_approved(): void
    {
        $student = Siswa::factory()->create();
        $kelas = KelasMapel::factory()->create();
        $this->actingAs($student->user)->get('/mata-pelajaran')->assertInertia(fn (Assert $page) => $page->component('Subjects')->has('subjects', 0));
        $this->getJson('/mata-pelajaran/'.$kelas->id)->assertForbidden();
        $kelas->anggota()->create(['siswa_id' => $student->id, 'status' => 'diterima', 'join_method' => 'kode', 'requested_at' => now()]);
        $task = Tugas::factory()->create(['kelas_mapel_id' => $kelas->id]);
        $this->get('/mata-pelajaran')->assertInertia(fn (Assert $page) => $page->has('subjects', 1)->where('subjects.0.total', 1));
        $this->get('/mata-pelajaran/'.$kelas->id)->assertInertia(fn (Assert $page) => $page->component('SubjectTasks')
            ->where('subject.name', $kelas->nama_kelas_mapel)->has('tasks', 1)->where('tasks.0.title', $task->judul)
            ->where('tasks.0.completed', false));
    }

    public function test_unknown_subject_returns_not_found(): void
    {
        $this->actingAs(Siswa::factory()->create()->user)->get('/mata-pelajaran/999')->assertNotFound();
    }
}
