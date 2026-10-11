/** Una consulta a la vez; ningún movimiento final se pierde esperando al octree. */
export function crearConsultaPuntero({consultar, mostrar, programar = requestAnimationFrame, cancelar = cancelAnimationFrame}) {
    let ultimo = null, version = 0, frame = null, pendiente = false, destruido = false;
    function agendar() {
        if (destruido || !ultimo || pendiente || frame !== null) return;
        frame = programar(async () => {
            frame = null;
            if (destruido || !ultimo) return;
            const solicitud = version, posicion = ultimo;
            ultimo = null; pendiente = true;
            try {
                const resultado = await consultar(posicion);
                if (!destruido && solicitud === version) mostrar(resultado);
            } finally {pendiente = false; agendar();}
        });
    }
    function limpiar() {
        version++; ultimo = null;
        if (frame !== null) cancelar(frame);
        frame = null;
    }
    return {
        mover(evento) {if (destruido) return; version++; ultimo = {clientX: evento.clientX, clientY: evento.clientY}; agendar();},
        cancelar: limpiar,
        destruir() {destruido = true; limpiar();},
    };
}
