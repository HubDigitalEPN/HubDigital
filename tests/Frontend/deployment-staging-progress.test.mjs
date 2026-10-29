import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import path from 'node:path';
import {spawn} from 'node:child_process';

const stage = readFileSync(new URL('../../deploy/oracle/scripts/stage-linux-candidate.sh', import.meta.url), 'utf8');
const phase = stage.split('# BEGIN RELEASE PHASE')[1].split('# END RELEASE PHASE')[0];
const bash = process.env.HUBDIGITAL_BASH || (process.platform === 'win32'
    ? path.join(process.env.ProgramFiles || 'C:/Program Files', 'Git/bin/bash.exe') : 'bash');

test('staging transmite progreso antes de finalizar y conserva el fallo real', async () => {
    const producer = "console.log('PROGRESO_VIVO');setTimeout(()=>{console.log('FIN_PRODUCTOR');process.exitCode=7;},300);";
    const child = spawn(bash, ['--noprofile', '--norc', '-c', `set -euo pipefail\n${phase}\nrun_phase prueba "$QA_NODE" -e "$QA_PRODUCER"`], {
        windowsHide: true, env: {...process.env, QA_NODE: process.execPath.replaceAll('\\', '/'), QA_PRODUCER: producer},
    });
    let output = '', beforeFinish = false;
    for (const stream of [child.stdout, child.stderr]) stream.on('data', chunk => {
        output += chunk;
        if (output.includes('PROGRESO_VIVO') && !output.includes('FIN_PRODUCTOR')) beforeFinish = true;
    });
    const status = await new Promise((resolve, reject) => { child.once('error', reject); child.once('close', resolve); });
    assert.equal(status, 7, output);
    assert.equal(beforeFinish, true, output);
    assert.match(output, /==> prueba/);
    assert.match(output, /FALLO: prueba/);
});

test('el build Linux conserva pipefail y ya no acumula su salida', () => {
    assert.match(stage, /set -euo pipefail/);
    assert.doesNotMatch(stage, /build_output="\$\(/);
    assert.match(stage, /build-release\.sh" 2>&1 \| tee "\$\{build_log\}" >&2/);
    assert.match(stage, /archive="\$\(tail -n 1 "\$\{build_log\}"\)"/);
});

test('Java del candidato se comprueba antes de mantenimiento y se conserva la aceptacion final', () => {
    const deploy = readFileSync(new URL('../../deploy/oracle/scripts/deploy-release.sh', import.meta.url), 'utf8');
    const activate = readFileSync(new URL('../../deploy/oracle/scripts/activate-release.sh', import.meta.url), 'utf8');
    const check = deploy.indexOf('scripts/verify-java-pdf.sh');
    assert.ok(check > 0 && check < deploy.indexOf('candidate_artisan down'));
    assert.ok(check < deploy.indexOf('/usr/bin/php8.4 artisan down'));
    assert.match(activate, /-jar "\$\{signature_jar\}" selftest/);
    const gate = readFileSync(new URL('../../deploy/oracle/scripts/verify-java-pdf.sh', import.meta.url), 'utf8');
    assert.match(gate, /MemoryMax=320M/);
    assert.match(gate, /RuntimeMaxSec=300/);
    assert.match(gate, /\.status == "firmado" and \.cryptographically_valid == true/);
    assert.match(gate, /exit 65/);
    assert.match(gate, /trap restore_signature EXIT/);
    assert.ok(gate.indexOf('signature_paused=1') < gate.indexOf('systemctl stop hubdigital-signature.service'));
    assert.ok(gate.indexOf('systemctl stop hubdigital-signature.service') < gate.indexOf('java_result='));
    assert.match(gate, /if \[\[ "\$\{signature_paused\}" == 1 \]\]; then[\s\S]*systemctl start hubdigital-signature.service/);
});

test('DEV usa el staging del bundle verificado y conserva progreso y errores', () => {
    const core = readFileSync(new URL('../../scripts/deployment/crear-paquete-oci-core.ps1', import.meta.url), 'utf8');
    assert.match(core, /tar -xOzf "\$\{base_dir\}\/\$\{package_name\}" deploy\/oracle\/scripts\/stage-linux-candidate\.sh/);
    assert.match(core, /sudo bash "\$\{stage_driver\}" "\$\{base_dir\}\/\$\{package_name\}" 2>&1 \| tee "\$\{stage_log\}"/);
    assert.match(core, /trap 'rm -f -- "\$\{stage_driver\}"' EXIT/);
});
