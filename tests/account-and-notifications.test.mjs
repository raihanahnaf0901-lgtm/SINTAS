import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { createServer } from 'vite';

const server = await createServer({ server: { middlewareMode: true, hmr: false } });
after(() => server.close());
const { saveProfileInformation } = await server.ssrLoadModule('/resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.jsx');
const { notificationDestination } = await server.ssrLoadModule('/resources/js/Pages/Notifications.jsx');

globalThis.route = (name, parameters) => ({ name, parameters });
after(() => { delete globalThis.route; });

test('saving an account name submits even when transform returns void in Inertia 3', (t) => {
    t.mock.method(globalThis, 'route', () => '/profile');
    let transformed;
    const submissions = [];
    saveProfileInformation({
        transform: (callback) => { transformed = callback({ name: ' Nama Baru ', email: ' SISWA@GMAIL.COM ' }); },
        patch: (url, options) => { submissions.push({ url, options, data: transformed }); },
    });
    assert.deepEqual(submissions, [{ url: '/profile', options: { preserveScroll: true }, data: { name: 'Nama Baru', email: 'siswa@gmail.com' } }]);
});

test('notifications link to the matching classroom section', () => {
    for (const [type, section] of Object.entries({ tugas: 'tugas', ujian: 'ujian', jadwal: 'jadwal', penilaian: 'nilai', rekap: 'rekap', permintaan_kelas: 'anggota' })) {
        assert.deepEqual(notificationDestination({ tipe: type, data: { kelas_mapel_id: 3 } }), {
            name: 'subjects.section', parameters: { subject: 3, section },
        });
    }
});

test('rejected membership notifications do not link into a forbidden classroom', () => {
    assert.deepEqual(notificationDestination({ tipe: 'keanggotaan_kelas', data: { kelas_mapel_id: 3, status: 'ditolak' } }), {
        name: 'subjects.index', parameters: undefined,
    });
});

test('notifications without a valid classroom do not create a broken link', () => {
    for (const data of [null, {}, { kelas_mapel_id: -1 }, { kelas_mapel_id: 'invalid' }]) {
        assert.equal(notificationDestination({ tipe: 'tugas', data }), null);
    }
});
