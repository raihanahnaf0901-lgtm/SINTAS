import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { createInertiaApp } from '@inertiajs/react';
import { createElement } from 'react';
import { renderToString } from 'react-dom/server';
import { createServer } from 'vite';

const server = await createServer({ server: { middlewareMode: true, hmr: false } });
after(() => server.close());
const { default: AcademicProfileForm } = await server.ssrLoadModule('/resources/js/Pages/Profile/Partials/AcademicProfileForm.jsx');

async function renderProfile(siswa) {
    const response = await createInertiaApp({
        page: { component: 'AcademicProfileForm', url: '/profile', version: null, props: {
            auth: { user: { name: 'Siswa', username: 'siswa', email_verified_at: '2026-09-21T00:00:00Z' } },
            siswa,
        } },
        resolve: () => AcademicProfileForm,
        render: renderToString,
        setup: ({ App, props }) => createElement(App, props),
    });
    return response.body;
}

test('student profile offers a text input instead of a school-class dropdown', async () => {
    const html = await renderProfile({ nis: '12345678', kelas_siswa: 'X PPLG 1' });
    assert.match(html, /Kelas siswa/);
    assert.match(html, /<input[^>]*type="text"[^>]*name="kelas_siswa"[^>]*value="X PPLG 1"/);
    assert.match(html, /placeholder="Contoh: X PPLG 1"/);
    assert.doesNotMatch(html, /<select|Kelas sekolah|name="kelas_id"/);
});

test('a cleared class stays empty even when an old master-class relationship remains', async () => {
    const html = await renderProfile({ nis: '12345678', kelas_siswa: null, kelas_id: 3, kelas: { nama_kelas: 'Kelas lama' } });
    assert.match(html, /name="kelas_siswa"[^>]*value=""/);
});

test('typed class names are escaped when rendered back into the profile', async () => {
    const html = await renderProfile({ nis: '12345678', kelas_siswa: '<script>alert(1)</script>' });
    assert.match(html, /&lt;script&gt;alert\(1\)&lt;\/script&gt;/);
    assert.doesNotMatch(html, /<script>alert\(1\)<\/script>/);
});
