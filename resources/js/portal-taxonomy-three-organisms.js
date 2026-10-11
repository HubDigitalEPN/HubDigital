import {CanvasTexture, LinearFilter} from 'three';

export const GRUPOS_BIOLOGICOS_3D = Object.freeze([
    'taxon', 'arachnida', 'insecta', 'crustacea', 'mollusca', 'annelida', 'myriapoda', 'nematomorpha',
]);
const grupos = new Map([
    ['arachnida', 'arachnida'], ['insecta', 'insecta'], ['tingidae', 'insecta'],
    ['crustacea', 'crustacea'], ['malacostraca', 'crustacea'],
    ['mollusca', 'mollusca'], ['bivalvia', 'mollusca'], ['gastropoda', 'mollusca'],
    ['annelida', 'annelida'], ['clitellata', 'annelida'], ['hirudinea', 'annelida'],
    ['chilopoda', 'myriapoda'], ['diplopoda', 'myriapoda'], ['nematomorpha', 'nematomorpha'],
]);

/** El símbolo identifica el grupo publicado, nunca una ilustración inventada de la especie. */
export function grupoBiologico3D(nodo, porClave, cache = new Map()) {
    const camino = [], visitados = new Set(); let actual = nodo, grupo = 'taxon';
    while (actual && !visitados.has(actual.clave)) {
        if (cache.has(actual.clave)) {grupo = cache.get(actual.clave); break;}
        visitados.add(actual.clave); camino.push(actual.clave);
        const conocido = grupos.get(String(actual.nombre || '').trim().toLowerCase());
        if (conocido) {grupo = conocido; break;}
        actual = porClave.get(actual.padre);
    }
    for (const clave of camino) cache.set(clave, grupo);
    return grupo;
}

/** Un único atlas pequeño, compartido por todos los mundos y filtros de la escena. */
export function crearAtlasBiologico3D() {
    const lado = 128, canvas = document.createElement('canvas');
    canvas.width = lado * GRUPOS_BIOLOGICOS_3D.length; canvas.height = lado;
    const ctx = canvas.getContext('2d');
    const linea = puntos => {
        ctx.beginPath(); ctx.moveTo(...puntos[0]);
        for (const punto of puntos.slice(1)) ctx.lineTo(...punto);
        ctx.stroke();
    };
    const ovalo = (x, y, rx, ry) => {ctx.beginPath(); ctx.ellipse(x, y, rx, ry, 0, 0, Math.PI * 2); ctx.stroke();};
    for (let i = 0; i < GRUPOS_BIOLOGICOS_3D.length; i++) {
        ctx.save(); ctx.translate(i * lado + lado / 2, lado / 2);
        ctx.strokeStyle = '#fff'; ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
        const grupo = GRUPOS_BIOLOGICOS_3D[i];
        if (grupo === 'arachnida') {
            ovalo(0, 11, 13, 18); ovalo(0, -10, 8, 10);
            for (const signo of [-1, 1]) for (let pata = 0; pata < 4; pata++)
                linea([[signo * 7, -12 + pata * 8], [signo * (20 + (pata % 2) * 5), -26 + pata * 15], [signo * 34, -34 + pata * 23]]);
        } else if (grupo === 'insecta') {
            ovalo(0, 13, 10, 19); ovalo(0, -8, 7, 9); ovalo(0, -22, 7, 7);
            for (const signo of [-1, 1]) {
                linea([[signo * 3, -25], [signo * 15, -38]]);
                for (let pata = 0; pata < 3; pata++)
                    linea([[signo * 6, -10 + pata * 10], [signo * 21, -19 + pata * 16], [signo * 31, -26 + pata * 23]]);
            }
        } else if (grupo === 'crustacea') {
            ovalo(0, -4, 12, 17);
            for (let segmento = 0; segmento < 4; segmento++) ovalo(0, 12 + segmento * 5, 9 - segmento * 1.4, 4);
            for (const signo of [-1, 1]) {
                linea([[signo * 4, -15], [signo * 18, -37], [signo * 27, -40]]);
                for (let pata = 0; pata < 5; pata++) linea([[signo * 8, -12 + pata * 7], [signo * 25, -18 + pata * 11], [signo * 33, -13 + pata * 11]]);
            }
        } else if (grupo === 'mollusca') {
            ctx.beginPath(); ctx.moveTo(0, 29); ctx.bezierCurveTo(-54, 18, -41, -34, -5, -32);
            ctx.bezierCurveTo(43, -38, 53, 10, 0, 29); ctx.stroke();
            for (let costilla = -2; costilla <= 2; costilla++) {
                ctx.beginPath(); ctx.moveTo(0, 29); ctx.quadraticCurveTo(costilla * 12, -2, costilla * 13, -25); ctx.stroke();
            }
        } else if (grupo === 'annelida' || grupo === 'nematomorpha') {
            ctx.lineWidth = grupo === 'annelida' ? 7 : 2.2;
            ctx.beginPath(); ctx.moveTo(-26, 31); ctx.bezierCurveTo(45, 45, -49, -39, 25, -29); ctx.stroke();
            if (grupo === 'annelida') {
                ctx.globalCompositeOperation = 'destination-out'; ctx.lineWidth = 1.5;
                for (let segmento = 0; segmento < 8; segmento++) {
                    const t = (segmento + .5) / 8, u = 1 - t;
                    const x = -26 * u ** 3 + 3 * 45 * u ** 2 * t - 3 * 49 * u * t ** 2 + 25 * t ** 3;
                    const y = 31 * u ** 3 + 3 * 45 * u ** 2 * t - 3 * 39 * u * t ** 2 - 29 * t ** 3;
                    const dx = 3 * 71 * u ** 2 - 6 * 94 * u * t + 3 * 74 * t ** 2;
                    const dy = 3 * 14 * u ** 2 - 6 * 84 * u * t + 3 * 10 * t ** 2;
                    const longitud = Math.hypot(dx, dy);
                    linea([[x - dy / longitud * 7, y + dx / longitud * 7], [x + dy / longitud * 7, y - dx / longitud * 7]]);
                }
            }
        } else if (grupo === 'myriapoda') {
            for (let segmento = 0; segmento < 8; segmento++) {
                const y = -28 + segmento * 8; ovalo(0, y, 7, 6);
                for (const signo of [-1, 1]) linea([[signo * 5, y], [signo * 20, y - 5], [signo * 26, y - 1]]);
            }
            for (const signo of [-1, 1]) linea([[signo * 3, -30], [signo * 15, -41]]);
        } else {
            // Los grupos no identificados reciben una red de linaje neutra.
            ovalo(0, -22, 6, 6); ovalo(-22, 18, 6, 6); ovalo(22, 18, 6, 6);
            linea([[0, -16], [0, 0], [-22, 12]]); linea([[0, 0], [22, 12]]);
        }
        ctx.restore();
    }
    const atlas = new CanvasTexture(canvas); atlas.generateMipmaps = false;
    atlas.minFilter = LinearFilter; atlas.magFilter = LinearFilter;
    return atlas;
}
