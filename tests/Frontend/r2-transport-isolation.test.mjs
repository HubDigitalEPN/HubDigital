import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync, mkdtempSync, mkdirSync, writeFileSync, existsSync, rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {spawnSync} from 'node:child_process';

const read = path => readFileSync(new URL('../../' + path, import.meta.url), 'utf8').replaceAll('\r\n', '\n');
const activation = read('deploy/oracle/scripts/activate-release.sh');
const unit = read('deploy/oracle/systemd/hubdigital-r2-transport.service');
const generator = read('deploy/oracle/scripts/configure-r2-transport.php');
const bash = process.env.HUBDIGITAL_BASH || (process.platform === 'win32' ? 'C:/Program Files/Git/bin/bash.exe' : 'bash');

test('R2 tiene configuración, PID y unidad independientes del frontal; restaura sus metadatos tras reiniciar', () => {
    assert.match(generator, /\$config = '\/etc\/hubdigital\/r2-transport\.conf'/);
    assert.match(generator, /\$persistentHost = '\/etc\/hubdigital\/r2-origin-host'/);
    const writeHost = generator.indexOf('file_put_contents($persistentHost,');
    assert.ok(writeHost > 0 && generator.indexOf('unlink($legacyConfig)', generator.indexOf('$plantilla =')) > writeHost);
    assert.match(unit, /ExecStartPre=.*install -m 0440 -o root -g www-data \/etc\/hubdigital\/r2-origin-host \/run\/hubdigital\/r2-transport\/origin-host/);
    assert.match(unit, /ExecStart=.*nginx -c \/etc\/hubdigital\/r2-transport\.conf/);
    assert.match(unit, /chmod 0600 \/run\/hubdigital\/r2-transport\/r2\.sock/);
    assert.match(unit, /StartLimitBurst=3/);
    assert.match(unit, /Restart=on-failure/);
    assert.doesNotMatch(unit, /(?:Requires|BindsTo|PartOf)=.*nginx\.service/);
    assert.doesNotMatch(read('deploy/oracle/systemd/nginx-hubdigital.conf'), /(?:Requires|Wants|BindsTo|PartOf)=.*r2-transport/);
    for (const path of ['deploy/oracle/scripts/install-base.sh', 'deploy/oracle/scripts/update-service-units.sh', 'scripts/deployment/crear-paquete-oci-core.ps1']) {
        assert.ok(read(path).includes('hubdigital-r2-transport.service'), path);
    }
});

const start = activation.indexOf('if [[ -f /etc/hubdigital/r2-transport.conf ]]');
const end = activation.indexOf('\nfi', start) + 3;
assert.ok(start > activation.indexOf('systemctl restart php8.4-fpm nginx') && end > start);
const block = activation.slice(start, end);
for (const [name, present, restartCode, expected] of [
    ['R2 disponible', true, 0, ['enable hubdigital-r2-transport.service', 'restart hubdigital-r2-transport.service']],
    ['DNS de R2 fallido conserva la activación del portal', true, 1, ['enable hubdigital-r2-transport.service', 'restart hubdigital-r2-transport.service']],
    ['endpoint no configurado desactiva solo el transporte', false, 0, ['disable --now hubdigital-r2-transport.service']],
]) test(name, () => {
    const run = spawnSync(bash, ['--noprofile', '--norc', '-c', `set -euo pipefail
systemctl() { printf '%s\\n' "$*"; if [[ "$1" == restart ]]; then return ${restartCode}; fi; }
${block.replace('[[ -f /etc/hubdigital/r2-transport.conf ]]', present ? 'true' : 'false')}
printf 'PORTAL_CONTINUA\\n'
`], {encoding: 'utf8', timeout: 5000, windowsHide: true});
    assert.ifError(run.error);
    assert.equal(run.status, 0, run.stderr);
    assert.deepEqual(run.stdout.trim().split('\n'), [...expected, 'PORTAL_CONTINUA']);
    if (restartCode) assert.match(run.stderr, /portal sigue activo/);
});

for (const failReload of [false, true]) test(`reparación operativa: ${failReload ? 'restaura respaldo si falla la recarga' : 'migra sin cambiar la release'}`, () => {
    const root = mkdtempSync(join(process.env.HUBDIGITAL_QA_TMPDIR || tmpdir(), 'hubdigital-r2-repair-'));
    try {
        for (const directory of ['etc/nginx/conf.d', 'etc/hubdigital', 'etc/systemd/system/nginx.service.d', 'run/hubdigital/r2-transport', 'source/scripts', 'source/nginx', 'source/systemd']) mkdirSync(join(root, directory), {recursive: true});
        const old = 'upstream hubdigital_r2_origin {\n    server 0123456789abcdef0123456789abcdef.r2.cloudflarestorage.com:443;\n}\n';
        const legacy = join(root, 'etc/nginx/conf.d/hubdigital-r2-transport.conf');
        writeFileSync(legacy, old);
        writeFileSync(join(root, 'etc/systemd/system/nginx.service.d/hubdigital.conf'), 'dropin anterior');
        writeFileSync(join(root, 'source/nginx/r2-transport.conf.template'), read('deploy/oracle/nginx/r2-transport.conf.template'));
        for (const name of ['hubdigital-r2-transport.service', 'nginx-hubdigital.conf']) writeFileSync(join(root, 'source/systemd', name), read('deploy/oracle/systemd/' + name));
        const script = read('deploy/oracle/scripts/repair-r2-dns-isolation.sh')
            .replace('[[ "${EUID}" -eq 0 ]]', 'true')
            .replaceAll('/etc/', '${HUBDIGITAL_REPAIR_TEST_ROOT}/etc/')
            .replaceAll('/run/', '${HUBDIGITAL_REPAIR_TEST_ROOT}/run/')
            .replaceAll('/var/backups/', '${HUBDIGITAL_REPAIR_TEST_ROOT}/var/backups/');
        const scriptPath = join(root, 'source/scripts/repair.sh');
        writeFileSync(scriptPath, script);
        const mocks = `
nginx() { printf 'nginx %s\\n' "$*" >> "$HUBDIGITAL_REPAIR_TEST_ROOT/calls"; }
systemd-analyze() { :; }
chown() { :; }
chmod() { :; }
stat() { printf '600:www-data:www-data\\n'; }
install() { local args=() directory=0; while (( $# )); do case "$1" in -o|-g|-m) shift 2;; -d) directory=1; shift;; *) args+=("$1"); shift;; esac; done; if (( directory )); then mkdir -p "\u0024{args[@]}"; else command cp "\u0024{args[@]}"; fi; }
cp() { if [[ "$1 $2" == '-a --parents' ]]; then mkdir -p "$(dirname "$4$3")"; command cp "$3" "$4$3"; else command cp "$@"; fi; }
systemctl() {
 printf 'systemctl %s\\n' "$*" >> "$HUBDIGITAL_REPAIR_TEST_ROOT/calls"
 if [[ "$1" == is-enabled || "$1" == is-active && "\u0024{2:-}" == hubdigital-r2-transport.service ]]; then return 1; fi
 if [[ "$HUBDIGITAL_REPAIR_FAIL_RELOAD" == 1 && "$1 \u0024{2:-}" == 'reload nginx' && ! -f "$HUBDIGITAL_REPAIR_TEST_ROOT/failed" ]]; then touch "$HUBDIGITAL_REPAIR_TEST_ROOT/failed"; return 1; fi
}
export -f nginx systemd-analyze chown chmod stat install cp systemctl
bash "$HUBDIGITAL_REPAIR_SCRIPT"
`;
        const result = spawnSync(bash, ['--noprofile', '--norc', '-c', mocks], {encoding: 'utf8', timeout: 10000, windowsHide: true,
            env: {...process.env, HUBDIGITAL_REPAIR_TEST_ROOT: process.platform === 'win32' ? '/' + root[0].toLowerCase() + root.slice(2).replaceAll('\\', '/') : root, HUBDIGITAL_REPAIR_SCRIPT: scriptPath.replaceAll('\\', '/'), HUBDIGITAL_REPAIR_FAIL_RELOAD: failReload ? '1' : '0'},
        });
        assert.ifError(result.error);
        assert.ok(existsSync(join(root, 'calls')), result.stderr + result.stdout);
        const calls = readFileSync(join(root, 'calls'), 'utf8');
        if (failReload) {
            assert.notEqual(result.status, 0, result.stderr);
            assert.ok(existsSync(legacy), result.stderr + result.stdout + calls);
            assert.equal(readFileSync(legacy, 'utf8'), old);
            assert.equal(readFileSync(join(root, 'etc/systemd/system/nginx.service.d/hubdigital.conf'), 'utf8'), 'dropin anterior');
            assert.equal(existsSync(join(root, 'etc/hubdigital/r2-transport.conf')), false);
            assert.doesNotMatch(calls, /systemctl restart hubdigital-r2-transport/);
        } else {
            assert.equal(result.status, 0, result.stderr);
            assert.equal(existsSync(legacy), false);
            assert.equal(readFileSync(join(root, 'etc/hubdigital/r2-origin-host'), 'utf8'), '0123456789abcdef0123456789abcdef.r2.cloudflarestorage.com');
            assert.ok(calls.indexOf('systemctl reload nginx') < calls.indexOf('systemctl restart hubdigital-r2-transport'));
            assert.doesNotMatch(calls, /systemctl (?:stop|restart) nginx|artisan|php8\.4-fpm/);
        }
    } finally { rmSync(root, {recursive: true, force: true}); }
});
