const fs = require('node:fs');

const archivo = process.argv[2];
if (!archivo) {
    console.error('Falta la ruta del archivo JSON.');
    process.exitCode = 1;
} else {
    try {
        JSON.parse(fs.readFileSync(archivo, 'utf8').replace(/^\uFEFF/, ''));
    } catch (error) {
        console.error(`JSON invalido en ${archivo}: ${error.message}`);
        process.exitCode = 1;
    }
}
