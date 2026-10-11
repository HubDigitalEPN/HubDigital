export function consultaPredictiva(campo, texto) {
    const fragmento = campo === 'filtroCatalogo' ? texto.split(',').at(-1) : texto;
    return fragmento.trim().slice(0, 120);
}

export function completarBusqueda(campo, texto, sugerencia) {
    if (campo !== 'filtroCatalogo' || !texto.includes(',')) return sugerencia;
    return `${texto.slice(0, texto.lastIndexOf(',') + 1)} ${sugerencia}`;
}
