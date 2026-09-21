import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';

const server = await createServer({ server: { middlewareMode: true, hmr: false } });
after(() => server.close());
const { ExamForm, ExamFormLink, ExamCard, ExamStudentAssessment } = await server.ssrLoadModule('/resources/js/Pages/Classroom/ExamsPanel.jsx');
const { gradePayload } = await server.ssrLoadModule('/resources/js/Pages/Classroom/GradesPanel.jsx');
const render = (Component, props) => renderToStaticMarkup(createElement(Component, props));

test('exam editor retains an existing optional Google Form responder link', () => {
    const html = render(ExamForm, {
        item: { id: 5, judul: 'Ulangan IPA', jenis_ujian: 'UH', tanggal: '2026-09-21', google_form_url: 'https://forms.gle/Test123' },
        path: '/api/v1/kelas-mapel/2/ujian',
    });

    assert.match(html, /Link Google Form \(opsional\)/);
    const linkInput = html.match(/<input[^>]*name="google_form_url"[^>]*>/)?.[0] ?? '';
    assert.match(linkInput, /type="url"/);
    assert.match(linkInput, /maxLength="2048"/);
    assert.match(linkInput, /value="https:\/\/forms.gle\/Test123"/);
    assert.match(html, /bukan link edit/);
    assert.doesNotMatch(linkInput, /required/);
});

test('supported Google Form responder URLs open securely in a separate tab', () => {
    const urls = [
        'https://forms.gle/Form123',
        'https://docs.google.com/forms/d/e/Form_123/viewform?usp=sf_link',
        'https://docs.google.com/forms/u/0/d/Form-123/viewform',
    ];

    for (const url of urls) {
        const html = render(ExamFormLink, { url });

        assert.match(html, /Buka soal di Google Form/);
        assert.match(html, /target="_blank" rel="noopener noreferrer"/);
        assert.ok(html.includes(`href="${url}"`));
    }
});

test('unsafe destinations and Google Form edit URLs are not rendered as exam links', () => {
    const urls = [
        null,
        'not-a-url',
        'javascript:alert(1)',
        'http://forms.gle/Form123',
        'https://example.com/forms/Form123',
        'https://forms.gle.example.com/Form123',
        'https://user:password@forms.gle/Form123',
        'https://forms.gle:8443/Form123',
        'https://docs.google.com/forms/d/Form123/edit',
        'https://docs.google.com/document/d/Form123/viewform',
    ];

    for (const url of urls) {
        assert.equal(render(ExamFormLink, { url }), '');
    }
});

test('student exam cards expose the form and their teacher grade without any editing controls', () => {
    const html = render(ExamCard, {
        item: { id: 5, judul: 'Ulangan IPA', jenis_ujian: 'UH', tanggal: '2026-09-21', google_form_url: 'https://forms.gle/Form123',
            penilaian: [{ nilai: '0.00', catatan: '<script>alert(1)</script>' }] },
        permissions: { isTeacher: false }, canManage: false,
    });

    assert.match(html, /Buka soal di Google Form/);
    assert.match(html, />0<span/);
    assert.match(html, /komponen ulangan harian/);
    assert.match(html, /&lt;script&gt;/);
    assert.doesNotMatch(html, /<script>|<form|<input|Beri nilai|Edit nilai|Edit ujian|Penilaian ujian|komponen tugas/);
});

test('ungraded student exams wait for teacher input and do not invent a completion state', () => {
    const html = render(ExamCard, {
        item: { id: 5, judul: 'Ulangan IPA', jenis_ujian: 'UH', tanggal: '2026-09-21', penilaian: [] },
        permissions: { isTeacher: false }, canManage: false,
    });

    assert.match(html, /Menunggu penilaian guru/);
    assert.doesNotMatch(html, /\/ 100|Buka soal|Sudah selesai|<input|<form/);
});

test('only permitted teachers get exam editing and grading actions', () => {
    const props = { item: { id: 5, judul: 'Ulangan IPA', jenis_ujian: 'UH' }, permissions: { isTeacher: true } };

    const teacher = render(ExamCard, { ...props, canManage: true });
    const readOnly = render(ExamCard, { ...props, canManage: false });

    assert.match(teacher, /Penilaian ujian/);
    assert.match(teacher, /Edit ujian/);
    assert.doesNotMatch(readOnly, /Penilaian ujian|Edit ujian|<form|<input/);
});

test('exam assessment offers manual create or edit depending on the existing grade', () => {
    const props = { student: { id: 7, nama_lengkap: 'Siswa Satu', nis: '123', penilaian: null }, exam: { id: 5, judul: 'Ulangan IPA', jenis_ujian: 'UH' } };

    assert.match(render(ExamStudentAssessment, { ...props, canManage: true }), /Beri nilai/);
    assert.match(render(ExamStudentAssessment, { ...props, student: { ...props.student, penilaian: { nilai: 80 } }, canManage: true }), /Edit nilai/);
    assert.doesNotMatch(render(ExamStudentAssessment, { ...props, canManage: false, editing: true }), /Beri nilai|Edit nilai|<form|<input/);
});

test('new inline exam grades fix the student and exam rather than defaulting to a task', () => {
    const html = render(ExamStudentAssessment, {
        student: { id: 7, nama_lengkap: 'Siswa Satu', penilaian: null },
        exam: { id: 5, judul: 'Ulangan IPA', jenis_ujian: 'UH' }, base: '/api/v1/kelas-mapel/2', canManage: true, editing: true,
    });

    assert.match(html, /Siswa Satu/);
    assert.match(html, /Ujian · Ulangan IPA/);
    assert.match(html, /type="number" min="0" max="100" step="0.01" required=""/);
    assert.match(html, /Simpan nilai/);
    assert.doesNotMatch(html, /Tugas · Ulangan IPA|<select/);
});

test('editing an exam grade preserves zero and escapes the student name and feedback', () => {
    const html = render(ExamStudentAssessment, {
        student: { id: 7, nama_lengkap: '<img src=x onerror=alert(1)>', penilaian: { ujian_id: 5, siswa_id: 7, nilai: '0.00', catatan: '<script>alert(2)</script>' } },
        exam: { id: 5, judul: '<script>alert(3)</script>', jenis_ujian: 'US' }, base: '/api/v1/kelas-mapel/2', canManage: true, editing: true,
    });

    assert.match(html, /Edit nilai/);
    assert.match(html, /value="0.00"/);
    assert.match(html, /&lt;img/);
    assert.match(html, /&lt;script&gt;/);
    assert.doesNotMatch(html, /<img|<script>/);
});

test('grade submissions send exactly one activity identifier and preserve a zero mark', () => {
    const input = { student: { id: 7 }, activity: { id: 5 }, data: { nilai: '0.00', catatan: 'Coba lagi' } };

    assert.deepEqual(gradePayload({ ...input, kind: 'ujian' }), { siswa_id: 7, ujian_id: 5, nilai: '0.00', catatan: 'Coba lagi' });
    assert.deepEqual(gradePayload({ ...input, kind: 'tugas' }), { siswa_id: 7, tugas_id: 5, nilai: '0.00', catatan: 'Coba lagi' });
});
