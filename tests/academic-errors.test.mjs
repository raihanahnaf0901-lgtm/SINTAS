import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { createServer } from 'vite';

const server = await createServer({ server: { middlewareMode: true, hmr: false } });
after(() => server.close());
const { errorMessage, validationErrors } = await server.ssrLoadModule('/resources/js/Components/AcademicUI.jsx');

test('validation summary shows the actual field error instead of a generic message', () => {
    const error = { response: { status: 422, data: { message: 'Periksa kembali isian formulir.', errors: { file: ['Ukuran berkas tugas maksimal 10 MB.'] } } } };
    assert.equal(errorMessage(error), 'Ukuran berkas tugas maksimal 10 MB.');
    assert.deepEqual(validationErrors(error), { file: 'Ukuran berkas tugas maksimal 10 MB.' });
});

test('JSON returned as text still exposes the file error', () => {
    const error = { response: { status: 422, data: JSON.stringify({ errors: { file: ['Format berkas tidak didukung.'] } }) } };
    assert.equal(errorMessage(error), 'Format berkas tidak didukung.');
    assert.deepEqual(validationErrors(error), { file: 'Format berkas tidak didukung.' });
});

test('invalid response reports an unreadable server error without showing HTML', () => {
    const error = { response: { status: 422, data: '<html>Server warning</html>' } };
    assert.match(errorMessage(error), /rincian kesalahannya tidak terbaca/);
    assert.doesNotMatch(errorMessage(error), /<html>|Periksa kembali isian formulir/);
    assert.deepEqual(validationErrors(error), {});
});

test('null data does not crash error handling and sessions retain their message', () => {
    assert.match(errorMessage({ response: { status: 422, data: null } }), /Server menolak/);
    assert.match(errorMessage({ response: { status: 419, data: {} } }), /Sesi kedaluwarsa/);
});
