/** Consulta del octree de puntos con un cono de selección de tamaño constante en pantalla. */
export function buscarNodoEspacial(arbol, posiciones, origen, direccion, escalaPixel, estadisticas, mundos) {
    if (!arbol || !posiciones.length) return -1;
    let mejor = -1, puntuacion = Infinity, distanciaPantalla = Infinity;
    const frente = mundos?.frente;
    const alineacion = frente ? direccion.reduce((suma, valor, eje) => suma + valor * frente[eje], 0) : 1;
    arbol.visit((octante, x0, y0, z0, x1, y1, z1) => {
        if (estadisticas) estadisticas.octantes = (estadisticas.octantes || 0) + 1;
        const profundidad = ((x0 + x1) * .5 - origen[0]) * direccion[0]
            + ((y0 + y1) * .5 - origen[1]) * direccion[1] + ((z0 + z1) * .5 - origen[2]) * direccion[2];
        const radio = Math.max(.001, (profundidad + Math.hypot(x1 - x0, y1 - y0, z1 - z0) * .5) * escalaPixel
            * (mundos ? Math.max(6, (octante.maxTamano || mundos.maxTamano) * 1.8 * (mundos.factor || 1) + 2) : 1));
        let cerca = 0, lejos = Infinity;
        for (let eje = 0; eje < 3; eje++) {
            const min = (eje === 0 ? x0 : eje === 1 ? y0 : z0) - radio;
            const max = (eje === 0 ? x1 : eje === 1 ? y1 : z1) + radio, paso = direccion[eje];
            if (Math.abs(paso) < 1e-12) {if (origen[eje] < min || origen[eje] > max) return true;}
            else {
                let entrada = (min - origen[eje]) / paso, salida = (max - origen[eje]) / paso;
                if (entrada > salida) [entrada, salida] = [salida, entrada];
                cerca = Math.max(cerca, entrada); lejos = Math.min(lejos, salida);
                if (lejos < cerca) return true;
            }
        }
        if (!octante.length) {
            let hoja = octante;
            do {
                if (estadisticas) estadisticas.puntos = (estadisticas.puntos || 0) + 1;
                const i = hoja.data, dx = posiciones[i * 3] - origen[0], dy = posiciones[i * 3 + 1] - origen[1], dz = posiciones[i * 3 + 2] - origen[2];
                const fondo = dx * direccion[0] + dy * direccion[1] + dz * direccion[2];
                if (fondo > 0) {
                    let profundidadVista = fondo;
                    let perpendicular = Math.max(0, dx * dx + dy * dy + dz * dz - fondo * fondo);
                    if (frente) {
                        // Los Points son discos en el plano de la cámara, no esferas
                        // geométricas. Usamos su profundidad y su círculo proyectado.
                        profundidadVista = dx * frente[0] + dy * frente[1] + dz * frente[2];
                        if (profundidadVista <= 0 || alineacion <= 0) {hoja = hoja.next; continue;}
                        const tramo = profundidadVista / alineacion;
                        perpendicular = (dx - direccion[0] * tramo) ** 2 + (dy - direccion[1] * tramo) ** 2 + (dz - direccion[2] * tramo) ** 2;
                    }
                    const pixeles = mundos ? Math.max(6, mundos.tamanos[i] * .5
                        * Math.max(.55, Math.min(3.6, mundos.distancia / profundidadVista)) * (mundos.factor || 1)
                        * (i === mundos.seleccionado ? 1 + (mundos.pulso || 0) * .10 : 1) + 3) : 1;
                    const tolerancia = Math.max(.001, profundidadVista * escalaPixel * pixeles);
                    const valor = frente ? profundidadVista : fondo - Math.sqrt(Math.max(0, tolerancia * tolerancia - perpendicular));
                    if (perpendicular <= tolerancia * tolerancia && (valor < puntuacion || (valor === puntuacion && (perpendicular < distanciaPantalla || perpendicular === distanciaPantalla && i < mejor)))) {
                        mejor = i; puntuacion = valor; distanciaPantalla = perpendicular;
                    }
                }
                hoja = hoja.next;
            } while (hoja);
        }
        return false;
    });
    return mejor;
}

/** Diámetro máximo por octante: los mundos pequeños no heredan la tolerancia de la raíz. */
export function prepararRadiosEspaciales(arbol, tamanos) {
    if (!arbol || !tamanos) return;
    arbol.visitAfter(octante => {
        let maximo = 0;
        if (octante.length) {
            for (let i = 0; i < 8; i++) maximo = Math.max(maximo, octante[i]?.maxTamano || 0);
        } else {
            let hoja = octante;
            do {maximo = Math.max(maximo, tamanos[hoja.data]); hoja = hoja.next;} while (hoja);
        }
        octante.maxTamano = maximo;
    });
}

/** Descarta octantes completos fuera del encuadre, sin recorrer sus hojas. */
export function nodosEnEncuadre(arbol, posiciones, planos, estadisticas) {
    const encontrados = [];
    if (!arbol || !posiciones.length) return new Uint32Array();
    arbol.visit((octante, x0, y0, z0, x1, y1, z1) => {
        if (estadisticas) estadisticas.octantes = (estadisticas.octantes || 0) + 1;
        for (let p = 0; p < planos.length; p += 4) {
            const a = planos[p], b = planos[p + 1], c = planos[p + 2], d = planos[p + 3];
            if (a * (a >= 0 ? x1 : x0) + b * (b >= 0 ? y1 : y0) + c * (c >= 0 ? z1 : z0) + d < 0) {
                if (estadisticas) estadisticas.descartados = (estadisticas.descartados || 0) + 1;
                return true;
            }
        }
        if (!octante.length) {
            let hoja = octante;
            do {
                const i = hoja.data, offset = i * 3; let dentro = true;
                if (estadisticas) estadisticas.puntos = (estadisticas.puntos || 0) + 1;
                for (let p = 0; p < planos.length; p += 4) {
                    if (planos[p] * posiciones[offset] + planos[p + 1] * posiciones[offset + 1]
                        + planos[p + 2] * posiciones[offset + 2] + planos[p + 3] < 0) {dentro = false; break;}
                }
                if (dentro) encontrados.push(i);
                hoja = hoja.next;
            } while (hoja);
        }
        return false;
    });
    return Uint32Array.from(encontrados);
}
