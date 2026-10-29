const fs = require('node:fs');
const path = require('node:path');
const {createHash} = require('node:crypto');
const {spawn} = require('node:child_process');

const remoteRoot = '/home/ubuntu/TEMP-BORRAR';
function target(root, destination) {
    const code = String(destination).toUpperCase();
    if (code === 'W') return {code, local: true};
    if (code === 'LD') return {code, host: 'ubuntu@129.153.23.57', key: path.join(root, '..', 'ssh-key-2026-09-18.key')};
    if (code === 'LP') return {code, host: 'ubuntu@150.136.219.112', key: path.join(process.env.USERPROFILE || '', '.ssh', 'equiframe-dw-a1-2026-09-30.key')};
    throw new Error('Destino invalido. Use W, LD o LP.');
}

function selectedFiles(directory, destination) {
    if (!['LD', 'LP'].includes(destination)) throw new Error('La transferencia exige LD o LP.');
    const bundles = fs.readdirSync(directory).filter(name => /^[a-z0-9-]+-AMBOS\.tar\.gz$/.test(name));
    if (bundles.length !== 1) throw new Error('Se exige exactamente un paquete AMBOS en la carpeta.');
    const id = bundles[0].slice(0, -'-AMBOS.tar.gz'.length);
    const names = destination === 'LD'
        ? [bundles[0], `${id}-DEV.sha256`, `${id}-DEV-cloudshell-vm.sh`, `${id}-DEV-hubdigital.env`]
        : [bundles[0], `${id}-PROD-produccion-vm.sh`];
    for (const name of names) {
        const item = fs.lstatSync(path.join(directory, name));
        if (!item.isFile() || item.isSymbolicLink() || item.size === 0) throw new Error(`Archivo de transferencia invalido: ${name}`);
    }
    return {id, names, remote: `${remoteRoot}/${id}`};
}

function sshOptions(key) {
    return ['-i', path.resolve(key), '-o', 'IdentitiesOnly=yes', '-o', 'IdentityAgent=none',
        '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=10', '-o', 'StrictHostKeyChecking=yes',
        '-o', 'ServerAliveInterval=10', '-o', 'ServerAliveCountMax=2', '-o', 'IPQoS=none'];
}

function run(program, args, {input = '', timeout = 30000, label = program, log = console.log} = {}) {
    return new Promise((resolve, reject) => {
        const child = spawn(program, args, {windowsHide: true, stdio: ['pipe', 'pipe', 'pipe']});
        let output = '', timedOut = false, interrupted = false, killFallback;
        const stop = () => {
            if (!child.pid) return;
            if (process.platform === 'win32') {
                const killer = spawn('taskkill.exe', ['/PID', String(child.pid), '/T', '/F'], {windowsHide: true, stdio: 'ignore'});
                killer.on('error', () => child.kill('SIGKILL'));
                killer.on('exit', code => { if (code !== 0) child.kill('SIGKILL'); });
                // taskkill tambien puede ser rechazado o bloquearse: nunca
                // esperar indefinidamente por el programa que impone el plazo.
                killFallback = setTimeout(() => {
                    killer.kill('SIGKILL');
                    child.kill('SIGKILL');
                    child.stdout.destroy(); child.stderr.destroy(); child.stdin.destroy();
                }, 1000);
            } else child.kill('SIGKILL');
        };
        const cancel = () => { interrupted = true; stop(); };
        const timer = setTimeout(() => { timedOut = true; stop(); }, timeout);
        const started = Date.now();
        const progress = setInterval(() => log(`En curso: ${label} (${Math.floor((Date.now() - started) / 1000)} s)`), 10000);
        process.once('SIGINT', cancel);
        const cleanup = () => { clearTimeout(timer); clearTimeout(killFallback); clearInterval(progress); process.removeListener('SIGINT', cancel); };
        for (const stream of [child.stdout, child.stderr]) stream.on('data', chunk => {
            output = (output + chunk.toString()).slice(-256 * 1024);
        });
        child.stdin.on('error', () => {}); // Un rechazo SSH puede cerrar stdin antes de leer el script.
        child.on('error', error => { cleanup(); reject(error); });
        child.on('close', code => {
            cleanup();
            if (timedOut || interrupted || code !== 0) return reject(new Error(`${label}: ${timedOut ? 'plazo agotado' : interrupted ? 'cancelado' : `codigo ${code}`}. ${output.trim()}`));
            if (output.trim()) log(output.trim());
            resolve(output);
        });
        child.stdin.end(input);
    });
}

async function sha256(filename) {
    const hash = createHash('sha256');
    for await (const chunk of fs.createReadStream(filename)) hash.update(chunk);
    return hash.digest('hex');
}

async function preflight(root, destination, execute = run) {
    const remote = target(root, destination);
    if (remote.local) return remote;
    if (!fs.existsSync(remote.key) || !fs.statSync(remote.key).isFile()) throw new Error(`No se encontro la clave SSH de ${destination}: ${remote.key}`);
    await execute('ssh.exe', [...sshOptions(remote.key), remote.host, "test -d /home/ubuntu && printf 'SSH_DESTINO_OK\\n'"], {timeout: 20000, label: `Conexion ${destination}`});
    return remote;
}

function removeLocalTemporary(root, directory, id) {
    const cache = path.resolve(root, '.local', 'deployment', 'oci-transfer');
    const absolute = path.resolve(directory);
    // Solo este paquete temporal, nunca artifacts/oci, una carpeta ajena o un enlace.
    if (path.dirname(absolute) !== cache || path.basename(absolute) !== id ||
        fs.lstatSync(absolute).isSymbolicLink() || fs.realpathSync(absolute) !== absolute || fs.realpathSync(cache) !== cache) return;
    const expected = [`${id}-AMBOS.tar.gz`, `${id}-DEV.sha256`, `${id}-DEV-cloudshell-vm.sh`,
        `${id}-DEV-hubdigital.env`, `${id}-PROD-produccion-vm.sh`, `${id}-INSTRUCCIONES-CLOUD-SHELL.txt`];
    const entries = fs.readdirSync(absolute);
    if (entries.some(name => !expected.includes(name) || !fs.lstatSync(path.join(absolute, name)).isFile())) return;
    for (const name of entries) fs.unlinkSync(path.join(absolute, name));
    fs.rmdirSync(absolute);
}

async function transfer(root, directory, destination, {execute = run, log = console.log} = {}) {
    const remote = target(root, destination);
    if (remote.local) throw new Error('W conserva los archivos en Windows; no realiza transferencias.');
    const plan = selectedFiles(directory, remote.code);
    if (!fs.existsSync(remote.key)) throw new Error(`Falta la clave SSH: ${remote.key}`);
    const options = sshOptions(remote.key);
    const expected = [];
    for (const name of plan.names) expected.push(`${await sha256(path.join(directory, name))}  ${name}`);
    const prepare = `set -euo pipefail\numask 077\n[[ ! -L '${remoteRoot}' ]]\nmkdir -p -- '${remoteRoot}'\n[[ ! -L '${plan.remote}' ]]\nmkdir -p -- '${plan.remote}'\n[[ "$(readlink -f -- '${plan.remote}')" == '${plan.remote}' ]]\nchmod 700 -- '${remoteRoot}' '${plan.remote}'\n`;
    await execute('ssh.exe', [...options, remote.host, 'bash -s'], {input: prepare, timeout: 20000, label: 'Preparando TEMP-BORRAR'});
    await execute('scp.exe', [...options, ...plan.names.map(name => path.resolve(directory, name)), `${remote.host}:${plan.remote}/`],
        {timeout: 600000, label: `Copiando ${plan.names.length} archivos a ${remote.code}`, log});
    const verify = `set -euo pipefail\ncd '${plan.remote}'\nchmod 600 -- ${plan.names.map(name => `'${name}'`).join(' ')}\nsha256sum -c - <<'HUBDIGITAL_SHA256'\n${expected.join('\n')}\nHUBDIGITAL_SHA256\n`;
    await execute('ssh.exe', [...options, remote.host, 'bash -s'], {input: verify, timeout: 30000, label: 'Verificando archivos recibidos', log});
    // Eliminar la copia local solo despues de comprobar TODOS los hashes remotos.
    // En cualquier fallo anterior se conserva para reintentar sin repetir pruebas.
    removeLocalTemporary(root, directory, plan.id);
    const script = plan.names.find(name => name.endsWith('.sh'));
    log(`TRANSFERENCIA_OK=${remote.code}; ${remote.host}:${plan.remote}`);
    log(`Siguiente paso manual en la VM: cd '${plan.remote}' && bash './${script}'`);
    log('La copia no aplica migraciones ni activa la release. Los temporales y TEMP-BORRAR se eliminan al finalizar la activacion correctamente.');
    return {...plan, host: remote.host};
}

module.exports = {target, selectedFiles, sshOptions, preflight, transfer, removeLocalTemporary, run};
if (require.main === module) {
    const [action, root, destination = 'LD', directory] = process.argv.slice(2);
    const work = action === 'comprobar' ? preflight(root, destination)
        : action === 'copiar' && directory ? transfer(root, directory, destination)
        : Promise.reject(new Error('Uso: destino-paquete.cjs comprobar PROYECTO LD|LP, o copiar PROYECTO LD|LP CARPETA'));
    work.catch(error => { console.error(error.message); process.exitCode = 1; });
}
