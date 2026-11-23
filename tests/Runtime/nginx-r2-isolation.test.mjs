import test from 'node:test';
import assert from 'node:assert/strict';
import {mkdtempSync, mkdirSync, readFileSync, writeFileSync, rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {spawn, spawnSync} from 'node:child_process';
import {createServer as tcpServer} from 'node:net';
import {createServer as tlsServer} from 'node:https';
import {request as httpRequest} from 'node:http';
import {setTimeout as delay} from 'node:timers/promises';

// Prueba explícita con el mismo Nginx 1.24 de Ubuntu; no instala runtimes.
// En Windows adapta solo user, rutas temporales y el socket a loopback.
const nginx = process.env.HUBDIGITAL_NGINX_EXE || 'nginx';
const openssl = process.env.HUBDIGITAL_OPENSSL || (process.platform === 'win32' ? 'C:/Program Files/Git/usr/bin/openssl.exe' : 'openssl');
const template = readFileSync(new URL('../../deploy/oracle/nginx/r2-transport.conf.template', import.meta.url), 'utf8');
const path = value => value.replaceAll('\\', '/');
function request(url, {body, ...options} = {}) {
    return new Promise((resolve, reject) => {
        const outgoing = httpRequest(url, {...options, agent: false}, response => {
            const chunks = [];
            response.on('data', chunk => chunks.push(chunk));
            response.on('error', reject);
            response.on('end', () => resolve({status: response.statusCode, text: async () => Buffer.concat(chunks).toString(), arrayBuffer: async () => Buffer.concat(chunks)}));
        });
        outgoing.on('error', reject); outgoing.end(body);
    });
}
async function port() {
    const server = tcpServer();
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const result = server.address().port;
    await new Promise(resolve => server.close(resolve));
    return result;
}

test('DNS de R2 no impide arrancar el portal; transporte conserva TLS, métodos, URI y keepalive', {timeout: 30000}, async t => {
    const root = mkdtempSync(join(process.env.HUBDIGITAL_QA_TMPDIR || tmpdir(), 'hubdigital-nginx-r2-'));
    for (const folder of ['logs', 'client', 'proxy', 'temp']) mkdirSync(join(root, folder));
    const processes = [];
    let backend;
    function check(config) {
        return spawnSync(nginx, ['-p', path(root) + '/', '-c', config, '-t'], {encoding: 'utf8', timeout: 5000, windowsHide: true});
    }
    async function launch(config, url) {
        const result = check(config); assert.ifError(result.error); assert.equal(result.status, 0, result.stderr);
        const child = spawn(nginx, ['-p', path(root) + '/', '-c', config, '-g', 'daemon off;'], {cwd: root, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe']});
        let errors = ''; child.stderr.on('data', chunk => {errors += chunk;});
        processes.push({child, config});
        for (let attempt = 0; attempt < 3; attempt++) {
            await delay(200 * (attempt + 1));
            try { await request(url, {headers: {Host: 'localhost'}, signal: AbortSignal.timeout(1500)}); return; }
            catch (error) { if (attempt === 2) throw new Error(errors || error.message); }
        }
    }
    function transportConfig(name, listener, upstream) {
        const contents = template.replaceAll('__R2_HOST__', 'localhost')
            .replace(/^user www-data;\r?\n/m, '')
            .replace('/run/hubdigital/r2-transport/nginx.pid', path(join(root, name + '.pid')))
            .replace('/run/hubdigital/nginx-client', path(join(root, 'client')))
            .replace('/run/hubdigital/nginx-fastcgi', path(join(root, 'proxy')))
            .replace('listen unix:/run/hubdigital/r2-transport/r2.sock;', `listen 127.0.0.1:${listener};`)
            .replace('server localhost:443;', `server ${upstream};`)
            .replace('/etc/ssl/certs/ca-certificates.crt', path(join(root, 'ca.crt')));
        const config = path(join(root, name + '.conf'));
        writeFileSync(config, contents); return config;
    }
    try {
        const cert = spawnSync(openssl, ['req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-keyout', join(root, 'key.pem'), '-out', join(root, 'ca.crt'), '-days', '1', '-subj', '/CN=localhost', '-addext', 'subjectAltName=DNS:localhost'], {encoding: 'utf8', timeout: 10000, windowsHide: true});
        assert.ifError(cert.error); assert.equal(cert.status, 0, cert.stderr);
        const publicPort = await port(), r2Port = await port();
        const portalConfig = path(join(root, 'portal.conf'));
        writeFileSync(portalConfig, `worker_processes 1; pid ${path(join(root, 'portal.pid'))}; error_log stderr; events { worker_connections 64; } http { access_log off; server { listen 127.0.0.1:${publicPort}; location / { return 200 'portal local'; } } }`);
        const brokenConfig = transportConfig('r2-dns-fallido', r2Port, 'r2-no-existe.invalid:443');
        await t.test('DNS fallido afecta solo la instancia R2 y el frontal arranca con HTTP 200', async () => {
            const broken = check(brokenConfig); assert.ifError(broken.error);
            assert.notEqual(broken.status, 0); assert.match(broken.stderr, /host not found in upstream/);
            await launch(portalConfig, `http://127.0.0.1:${publicPort}`);
            const response = await request(`http://127.0.0.1:${publicPort}`, {signal: AbortSignal.timeout(2000)});
            assert.equal(response.status, 200); assert.equal(await response.text(), 'portal local');
        });
        let connections = 0;
        const received = [];
        backend = tlsServer({key: readFileSync(join(root, 'key.pem')), cert: readFileSync(join(root, 'ca.crt'))}, async (request, response) => {
            let body = ''; for await (const chunk of request) body += chunk;
            received.push({method: request.method, url: request.url, host: request.headers.host, auth: request.headers.authorization, body});
            response.end('R2 sintético');
        });
        backend.on('secureConnection', () => connections++);
        await new Promise(resolve => backend.listen(0, '127.0.0.1', resolve));
        const healthy = transportConfig('r2', r2Port, `127.0.0.1:${backend.address().port}`);
        const r2Url = `http://127.0.0.1:${r2Port}/objeto%20prueba?parte=1&orden=2`;
        await launch(healthy, r2Url);
        await t.test('GET/HEAD/PUT/DELETE conservan URI, Host, firma y reutilizan TLS', async () => {
            received.length = 0;
            const before = connections;
            for (const method of ['GET', 'HEAD', 'PUT', 'DELETE']) {
                const response = await request(r2Url, {method, headers: {Host: 'localhost', Authorization: 'firma-sintetica'}, ...(method === 'PUT' ? {body: 'contenido sintético'} : {}), signal: AbortSignal.timeout(2000)});
                assert.equal(response.status, 200); await response.arrayBuffer();
            }
            assert.deepEqual(received.map(r => r.method), ['GET', 'HEAD', 'PUT', 'DELETE']);
            for (const request of received) {
                assert.equal(request.url, '/objeto%20prueba?parte=1&orden=2');
                assert.equal(request.host, 'localhost'); assert.equal(request.auth, 'firma-sintetica');
            }
            assert.equal(received[2].body, 'contenido sintético');
            assert.ok(connections - before <= 1, 'Las peticiones reutilizan la conexión TLS del upstream.');
        });
        await t.test('rechaza Host ajeno y métodos no autorizados', async () => {
            assert.equal((await request(r2Url, {headers: {Host: 'otro.invalid'}, signal: AbortSignal.timeout(2000)})).status, 421);
            assert.equal((await request(r2Url, {method: 'POST', headers: {Host: 'localhost'}, body: 'rechazado', signal: AbortSignal.timeout(2000)})).status, 403);
        });
        await t.test('rechaza TLS con nombre ajeno y el portal continúa respondiendo', async () => {
            const badPort = await port();
            const badConfig = transportConfig('r2-tls-fallido', badPort, `127.0.0.1:${backend.address().port}`);
            writeFileSync(badConfig, readFileSync(badConfig, 'utf8').replace('proxy_ssl_name localhost;', 'proxy_ssl_name ajeno.invalid;'));
            const url = `http://127.0.0.1:${badPort}/`;
            await launch(badConfig, url);
            assert.equal((await request(url, {headers: {Host: 'localhost'}, signal: AbortSignal.timeout(2000)})).status, 502);
            assert.equal((await request(`http://127.0.0.1:${publicPort}`, {signal: AbortSignal.timeout(2000)})).status, 200);
        });
    } finally {
        for (const {child, config} of processes.reverse()) {
            const stopped = new Promise(resolve => {if (child.exitCode !== null) resolve(); else child.once('exit', resolve);});
            spawnSync(nginx, ['-p', path(root) + '/', '-c', config, '-s', 'quit'], {timeout: 5000, windowsHide: true, stdio: 'ignore'});
            await Promise.race([stopped, delay(3000)]);
            if (child.exitCode === null) child.kill();
        }
        if (backend) { backend.closeAllConnections(); await new Promise(resolve => backend.close(resolve)); }
        rmSync(root, {recursive: true, force: true});
    }
});
