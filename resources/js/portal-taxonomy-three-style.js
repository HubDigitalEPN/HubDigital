/** La abundancia sólo matiza el diámetro: nunca sustituye la jerarquía visual. */
export function factorAbundancia3D(registros, maximo) {
    const total = Math.max(100, Number(registros) || 0), limite = Math.max(100, Number(maximo) || 0);
    if (!Number.isFinite(total) || !Number.isFinite(limite) || limite <= 100) return 1;
    const proporcion = Math.min(1, Math.max(0, Math.log(total / 100) / Math.log(limite / 100)));
    // Relación máxima entre diámetros: 1,08. Hasta 99 registros comparten el mínimo.
    return 1 + proporcion * .08;
}

let fondoEstelar;
/** Una imagen SVG compartida y estática: sin partículas, texturas por nodo ni animación adicional. */
export function fondoEstelar3D() {
    if (fondoEstelar) return fondoEstelar;
    const estrellas = []; let semilla = 20261011;
    const aleatorio = () => {semilla ^= semilla << 13; semilla ^= semilla >>> 17; semilla ^= semilla << 5; return (semilla >>> 0) / 4294967296;};
    for (let i = 0; i < 180; i++) {
        const x = (aleatorio() * 1600).toFixed(1), y = (aleatorio() * 1000).toFixed(1);
        const radio = (.65 + aleatorio() * .55).toFixed(2), opacidad = (.28 + aleatorio() * .25).toFixed(2);
        estrellas.push(`<circle cx="${x}" cy="${y}" r="${radio}" opacity="${opacidad}"/>`);
    }
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 1000"><g fill="#c4d5e5">${estrellas.join('')}</g></svg>`;
    fondoEstelar = `url("data:image/svg+xml,${encodeURIComponent(svg)}")`;
    return fondoEstelar;
}
