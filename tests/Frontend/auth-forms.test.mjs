import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/auth-forms.js', import.meta.url), 'utf8')
    .replace("import { showNotification } from './hub-notifications';", '').replaceAll('export ', '');
const { authenticationDestination, requestAuthentication } = vm.runInNewContext(source + '\n({authenticationDestination,requestAuthentication})', { URL });

test('el destino conserva origen, perfil y segundo factor y rechaza redirecciones externas', () => {
    const origin = 'https://labinvepn.org';
    assert.equal(authenticationDestination({ redirect: '/portal/catalogo?vista=mapa' }, {}, origin), origin + '/portal/catalogo?vista=mapa');
    assert.equal(authenticationDestination({ two_factor: true, redirect: '/dashboard' }, { twoFactorUrl: '/two-factor-challenge' }, origin), origin + '/two-factor-challenge');
    assert.equal(authenticationDestination({}, { successUrl: '/login' }, origin), origin + '/login');
    assert.equal(authenticationDestination({ message: 'Respuesta genérica' }, {}, origin), null);
    for (const redirect of ['https://externo.example/', '//externo.example/', 'javascript:alert(1)']) assert.throws(() => authenticationDestination({ redirect }, {}, origin));
});

test('el POST interno conserva sesión y devuelve errores de campo sin exponer respuestas técnicas', async () => {
    const body = { fixture: true };
    let observed;
    const result = await requestAuthentication('/login', body, async (action, options) => {
        observed = { action, options };
        return { ok: false, status: 422, json: async () => ({ errors: { email: ['Credenciales incorrectas.'] } }) };
    });
    assert.equal(observed.options.body, body);
    assert.equal(observed.options.credentials, 'same-origin');
    assert.equal(observed.options.headers.Accept, 'application/json');
    assert.equal(result.ok, false);
    assert.equal(result.errors.email[0], 'Credenciales incorrectas.');
    assert.equal(result.message, 'Credenciales incorrectas.');
    const failure = await requestAuthentication('/login', body, async () => ({ ok: false, status: 500, json: async () => { throw new Error('HTML con detalles privados'); } }));
    assert.equal(failure.message, 'No se pudo completar la solicitud. Intenta nuevamente.');
    const success = await requestAuthentication('/login', body, async () => ({ ok: true, json: async () => ({ redirect: '/dashboard', csrf: 'renovado' }) }));
    assert.equal(success.ok, true);
    assert.equal(success.data.csrf, 'renovado');
});
