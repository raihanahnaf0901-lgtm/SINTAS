import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';

const server = await createServer({ server: { middlewareMode: true, hmr: false } });
after(() => server.close());
const { GradeSummary, GradeEditor } = await server.ssrLoadModule('/resources/js/Pages/Classroom/GradesPanel.jsx');
const { SubmissionAssessment, TaskCard } = await server.ssrLoadModule('/resources/js/Pages/Classroom/TasksPanel.jsx');
const render = (Component, props) => renderToStaticMarkup(createElement(Component, props));

test('ungraded submissions show a waiting state instead of a zero grade', () => {
    const html = render(GradeSummary, { grade: null });
    assert.match(html, /Penilaian/);
    assert.match(html, /Menunggu penilaian guru/);
    assert.doesNotMatch(html, /\/ 100/);
});

test('a zero grade remains visible and teacher feedback is escaped', () => {
    const html = render(GradeSummary, { grade: { nilai: '0.00', catatan: '<script>alert(1)</script>' } });
    assert.match(html, />0<span/);
    assert.match(html, /&lt;script&gt;/);
    assert.doesNotMatch(html, /<script>|Menunggu penilaian/);
});

test('teacher assessment offers create and edit while read-only access has no grading action', () => {
    const submission = { id: 1, has_file: true, penilaian: null };
    assert.match(render(SubmissionAssessment, { submission, canManage: true }), /Beri nilai/);
    assert.match(render(SubmissionAssessment, { submission: { ...submission, penilaian: { nilai: 80 } }, canManage: true }), /Edit nilai/);
    const readOnly = render(SubmissionAssessment, { submission, canManage: false });
    assert.doesNotMatch(readOnly, /Beri nilai|Edit nilai|<form/);
    assert.match(readOnly, /Periksa berkas tugas/);
});

test('inline grading fixes the student and task and preserves an existing zero grade', () => {
    const html = render(GradeEditor, { base: '/api/v1/kelas-mapel/3',
        grade: { siswa_id: 7, tugas_id: 8, nilai: '0.00', catatan: 'Periksa ulang' },
        target: { student: { id: 7, label: 'Siswa Satu' }, activity: { id: 8, label: 'Tugas Satu' } },
    });
    assert.match(html, /Siswa Satu/);
    assert.match(html, /Tugas Satu/);
    assert.match(html, /value="0.00"/);
    assert.match(html, /min="0" max="100" step="0.01"/);
    assert.match(html, /Periksa ulang/);
    assert.doesNotMatch(html, /<select/);
});

test('student task cards show assessment without download or teacher edit buttons', () => {
    const html = render(TaskCard, {
        task: { id: 8, kelas_mapel_id: 3, judul: 'Tugas Satu', jenis: 'tugas', deadline: '2099-01-01T00:00:00Z',
            pengumpulan: [{ id: 1, status: 'dikumpulkan', has_file: true }], penilaian: [{ nilai: '85.00' }] },
        permissions: { isTeacher: false }, canManage: false, canSubmit: true,
    });
    assert.match(html, /Penilaian/);
    assert.match(html, />85<span/);
    assert.doesNotMatch(html, /Unduh berkas|Periksa berkas|Beri nilai|Edit nilai|type="number"/);
});
