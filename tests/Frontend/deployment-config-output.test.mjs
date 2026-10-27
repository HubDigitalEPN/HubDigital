import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';

const script = readFileSync(new URL('../../deploy/oracle/scripts/verify-temporaries.sh', import.meta.url), 'utf8').replaceAll('\r\n', '\n');
const production = readFileSync(new URL('../../scripts/deployment/produccion-vm.sh.template', import.meta.url), 'utf8').replaceAll('\r\n', '\n');
const helperStart = production.indexOf('\nrelease_command() (');
const helperEnd = production.indexOf('\n)\n', helperStart);
assert.ok(helperStart >= 0 && helperEnd > helperStart, 'Falta la compatibilidad de salida completa de PROD');
const productionHelper = production.slice(helperStart + 1, helperEnd + 2);
const bash = process.env.HUBDIGITAL_BASH || (process.platform === 'win32'
    ? join(process.env.ProgramFiles || 'C:/Program Files', 'Git', 'bin', 'bash.exe') : 'bash');

for (const [daemon, args, expected] of [
    ['nginx', '-T', 'client_body_temp_path /run/hubdigital/nginx-client'],
    ['php-fpm8.4', '-tt', 'php_admin_value[sys_temp_dir] = /run/hubdigital/php-tmp'],
]) {
    // Ejecutar el bloque real del script; no requiere root ni un daemon instalado.
    const start = script.indexOf(`\n${daemon} `);
    const end = script.indexOf('\n}', start);
    assert.ok(start >= 0 && end > start, `Falta la comprobación de ${daemon}`);
    const check = script.slice(start + 1, end + 2);
    // El bundle ya publicado conserva estas tuberías; el asistente externo
    // debe corregir su lectura sin editar el contenido identificado por Git.
    const legacyCheck = `set -euo pipefail\n${daemon} ${args} 2>&1 | grep -Fq "$HUBDIGITAL_QA_EXPECTED" || exit 65`;
    for (const [mode, command] of [
        ['script corregido', check],
        ['bundle anterior con asistente PROD', `${productionHelper}\nexport -f ${daemon}\nrelease_command env "$BASH" --noprofile --norc -c "$HUBDIGITAL_QA_LEGACY"`],
    ]) {
        for (const [scenario, line, code, status] of [
            ['ruta correcta al inicio de una salida grande', expected, 0, 0],
            ['ruta ausente', 'otra configuración', 0, 65],
            ['daemon fallido aunque imprima la ruta', expected, 7, 65],
        ]) test(`${daemon}, ${mode}: ${scenario}`, () => {
            // Un proceso externo y más de 1 MiB fuerzan la escritura después del match:
            // un lector que cierre anticipadamente puede provocar EPIPE/SIGPIPE.
            const producer = "process.stdout.write(process.env.HUBDIGITAL_QA_CONFIG_LINE+'\\n'+'x'.repeat(1024*1024)+'\\n',()=>{process.exitCode=Number(process.env.HUBDIGITAL_QA_CONFIG_EXIT);});";
            const result = spawnSync(bash, ['--noprofile', '--norc', '-c', `set -euo pipefail\n${daemon}() { "$HUBDIGITAL_QA_NODE" -e "$HUBDIGITAL_QA_PRODUCER"; }\n${command}\n`], {
                encoding: 'utf8', timeout: 20000, maxBuffer: 128 * 1024,
                env: { ...process.env, HUBDIGITAL_QA_NODE: process.execPath.replaceAll('\\', '/'), HUBDIGITAL_QA_PRODUCER: producer, HUBDIGITAL_QA_CONFIG_LINE: line, HUBDIGITAL_QA_CONFIG_EXIT: String(code), HUBDIGITAL_QA_EXPECTED: expected, HUBDIGITAL_QA_LEGACY: legacyCheck },
            });
            assert.ifError(result.error);
            assert.equal(result.status, status, result.stderr);
        });
    }
}
