const {spawnSync} = require('node:child_process');
const {createReadStream} = require('node:fs');
const {stat} = require('node:fs/promises');
const {createHash} = require('node:crypto');
const path = require('node:path');

async function main() {
    const root = path.resolve(process.argv[2]);
    const listing = spawnSync('git.exe', ['-C', root, 'ls-files', '-z', '--cached', '--others', '--exclude-standard'], {encoding: 'utf8', windowsHide: true, maxBuffer: 16 * 1024 * 1024});
    if (listing.status !== 0) throw new Error('No se pudo enumerar el código con Git.');
    const files = [...new Set(listing.stdout.split('\0').filter(Boolean))].sort();
    const results = new Array(files.length);
    let next = 0;
    async function consume() {
        while (next < files.length) {
            const index = next++;
            const file = files[index];
            const absolute = path.resolve(root, file);
            const relative = path.relative(root, absolute);
            if (relative === '..' || relative.startsWith('..' + path.sep) || path.isAbsolute(relative)) throw new Error('Ruta de código fuera del repositorio.');
            try {
                if (!(await stat(absolute)).isFile()) {
                    results[index] = `eliminado  ${file}`;
                    continue;
                }
                const hash = createHash('sha256');
                for await (const chunk of createReadStream(absolute, {highWaterMark: 256 * 1024})) hash.update(chunk);
                results[index] = `${hash.digest('hex')}  ${file}`;
            } catch (error) {
                if (error.code !== 'ENOENT') throw error;
                results[index] = `eliminado  ${file}`;
            }
        }
    }
    // Cuatro lectores acotados; conserva la huella de todos los archivos y el orden.
    await Promise.all(Array.from({length: 4}, consume));
    process.stdout.write(results.join('\n') + '\n');
}

main().catch(error => { process.stderr.write(error.message + '\n'); process.exitCode = 1; });
