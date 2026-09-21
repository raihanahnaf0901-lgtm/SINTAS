import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { createServer } from 'vite';

const server = await createServer({ server: { middlewareMode: true, hmr: false } });
after(() => server.close());
const { Field } = await server.ssrLoadModule('/resources/js/Pages/Auth/Partials/AuthFields.jsx');
const { default: Icon } = await server.ssrLoadModule('/resources/js/Components/Icon.jsx');

for (const field of ['password', 'password_confirmation', 'current_password']) {
test(`${field} starts hidden with an accessible eye button by default`, () => {
    const html = renderToStaticMarkup(createElement(Field, {
        label: 'Password SINTAS', field, type: 'password',
        form: { data: { password: '' }, errors: {}, setData() {}, clearErrors() {} },
    }));

    assert.match(html, /type="password"/);
    assert.match(html, /<button[^>]*type="button"[^>]*aria-label="Tampilkan password"[^>]*aria-pressed="false"/);
    assert.match(html, /<svg aria-hidden="true"/);
    assert.doesNotMatch(html, />Show<|>Hide</);
});
}

test('email fields do not have a password visibility button', () => {
    const html = renderToStaticMarkup(createElement(Field, {
        label: 'Email', field: 'email', type: 'email',
        form: { data: { email: '' }, errors: {}, setData() {}, clearErrors() {} },
    }));
    assert.match(html, /type="email"/);
    assert.doesNotMatch(html, /<button/);
});

test('visible and hidden password icons are distinct SVGs', () => {
    const eye = renderToStaticMarkup(createElement(Icon, { name: 'eye' }));
    const eyeOff = renderToStaticMarkup(createElement(Icon, { name: 'eyeOff' }));

    assert.match(eye, /<svg/);
    assert.match(eyeOff, /<svg/);
    assert.notEqual(eye, eyeOff);
});
