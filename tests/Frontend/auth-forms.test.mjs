import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/auth-forms.js', import.meta.url), 'utf8')
    .replace("import { showNotification } from './hub-notifications';", '').replaceAll('export ', '');
const { authenticationDestination, requestAuthentication } = vm.runInNewContext(source + '\n({authenticationDestination,requestAuthentication})', { URL, AbortSignal });

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
    assert.ok(observed.options.signal instanceof AbortSignal);
    assert.equal(result.ok, false);
    assert.equal(result.errors.email[0], 'Credenciales incorrectas.');
    assert.equal(result.message, 'Credenciales incorrectas.');
    const failure = await requestAuthentication('/login', body, async () => ({ ok: false, status: 500, json: async () => { throw new Error('HTML con detalles privados'); } }));
    assert.equal(failure.message, 'No se pudo completar la solicitud. Intenta nuevamente.');
    const success = await requestAuthentication('/login', body, async () => ({ ok: true, json: async () => ({ redirect: '/dashboard', csrf: 'renovado' }) }));
    assert.equal(success.ok, true);
    assert.equal(success.data.csrf, 'renovado');
});

test('una respuesta incompleta conserva el error de tiempo de espera', async () => {
    const expiration = new DOMException('Tiempo agotado', 'TimeoutError');
    const { requestAuthentication: request } = vm.runInNewContext(source + '\n({requestAuthentication})', {
        AbortSignal: { timeout(ms) {
            assert.equal(ms, 20000);
            return AbortSignal.abort(expiration);
        } },
    });
    await assert.rejects(request('/login', {}, async (_url, { signal }) => ({
        ok: true,
        json: async () => signal.throwIfAborted(),
    })), expiration);
});

test('un login fallido deja el formulario listo para verificar sin reiniciar el widget', async () => {
    let submit, requests = 0;
    const token = { value: 'valid-token' };
    const button = { disabled: false, textContent: 'Iniciar Sesión' };
    const feedback = { hidden: true, setAttribute() {} };
    const attributes = new Map(), dispatched = [];
    const form = {
        matches: () => true, action: '/login',
        querySelector: selector => ({
            '[name="cf-turnstile-response"]': token,
            '[data-auth-feedback]': feedback,
        })[selector],
        querySelectorAll: selector => selector === 'button[type=submit]' ? [button] : [],
        setAttribute: (name, value) => attributes.set(name, value),
        removeAttribute: name => attributes.delete(name),
        dispatchEvent: event => dispatched.push(event.type),
    };
    const { bootAuthForms } = vm.runInNewContext(source + '\n({bootAuthForms})', {
        AbortSignal,
        document: { addEventListener: (_type, handler) => { submit = handler; } },
        fetch: async () => {
            requests++;
            return { ok: false, status: 503, json: async () => ({}) };
        },
        FormData: class {},
        CustomEvent: class { constructor(type) { this.type = type; } },
    });
    bootAuthForms({});
    const event = { target: form, preventDefault() {} };
    await submit(event);
    assert.equal(token.value, '');
    assert.equal(button.disabled, true);
    assert.equal(button.textContent, 'Iniciar Sesión');
    assert.equal(attributes.has('aria-busy'), false);
    assert.equal(feedback.hidden, false);
    assert.match(feedback.textContent, /No se pudo completar/);
    assert.deepEqual(dispatched, ['hub-turnstile-consumed']);
    await submit(event);
    assert.equal(requests, 1);
});

test('un formulario anterior retoma la página del middleware sin aceptar destinos externos ni ocultar errores', async () => {
    const origin = 'https://labinvepn.org';
    const htmlResponse = (url, status = 200) => ({
        ok: status === 200, status, redirected: true, url,
        json: async () => { throw new SyntaxError('La respuesta final es HTML.'); },
    });
    const result = await requestAuthentication('/login', {}, async () => htmlResponse(origin + '/dashboard'));
    assert.equal(result.ok, true);
    assert.equal(authenticationDestination(result.data, {}, origin), origin + '/dashboard');

    const external = await requestAuthentication('/login', {}, async () => htmlResponse('https://externo.example/dashboard'));
    assert.throws(() => authenticationDestination(external.data, {}, origin));

    const expired = await requestAuthentication('/login', {}, async () => htmlResponse(origin + '/login', 419));
    assert.equal(expired.ok, false);
    assert.equal(expired.message, 'La sesión venció. Vuelve a abrir este formulario.');

    const json = await requestAuthentication('/login', {}, async () => ({
        ok: true, redirected: true, url: origin + '/dashboard',
        json: async () => ({ redirect: '/portal/catalogo?vista=mapa', csrf: 'renovado' }),
    }));
    assert.equal(authenticationDestination(json.data, {}, origin), origin + '/portal/catalogo?vista=mapa');
    assert.equal(json.data.csrf, 'renovado');
});

test('un acceso aceptado deja un enlace al destino aunque Livewire no termine la navegación', async () => {
    const origin = 'https://labinvepn.org';
    const feedback = { hidden: true, setAttribute() {}, append(_space, link) { this.link = link; } };
    const button = { disabled: false, textContent: 'Iniciar sesión' };
    const form = {
        id: 'hub-login-form', matches: () => true, action: '/login', dataset: {},
        querySelector: selector => selector === '[data-auth-feedback]' ? feedback : null,
        querySelectorAll: selector => selector === 'button[type=submit]' ? [button] : [],
        setAttribute() {}, removeAttribute() {},
    };
    let submit, destination;
    const { bootAuthForms } = vm.runInNewContext(source + '\n({bootAuthForms})', {
        URL, AbortSignal, location: { origin }, FormData: class {},
        document: {
            addEventListener(_event, handler) { submit = handler; },
            createElement(tag) { assert.equal(tag, 'a'); return {}; },
        },
        fetch: async () => ({ ok: true, json: async () => ({ redirect: '/portal/catalogo?vista=mapa' }) }),
    });
    bootAuthForms({ navigate(url) { destination = url; } });
    await submit({ target: form, preventDefault() {} });
    assert.equal(destination, origin + '/portal/catalogo?vista=mapa');
    assert.equal(feedback.textContent, 'Sesión iniciada.');
    assert.equal(feedback.link.href, destination);
    assert.equal(feedback.link.textContent, 'Continuar');
    assert.equal(button.disabled, true);
});
