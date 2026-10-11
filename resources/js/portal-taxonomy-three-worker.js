import {crearIndiceRama3D, distribuirRama3D, filtrarRama3D} from './portal-taxonomy-three-model';
import {bytesDibujo3D} from './portal-taxonomy-three-cache';
import {octree} from 'd3-octree';
import {buscarNodoEspacial, nodosEnEncuadre, prepararRadiosEspaciales} from './portal-taxonomy-three-spatial';

let nodos = [], jerarquia, indice, tareaIndice = null, estructura = null, raizEstructura = '';
const maxBytesEstructura = 2 * 1024 * 1024;
function indexar(posiciones, revision, visibles, tamanos, idIndice) {
    const tarea = {posiciones, revision, visibles, tamanos, idIndice, cursor: 0, cantidad: 0, completo: false, inicio: performance.now()}; tareaIndice = tarea;
    indice = octree().x(i => posiciones[i * 3]).y(i => posiciones[i * 3 + 1]).z(i => posiciones[i * 3 + 2]);
    const agregar = () => {
        if (tareaIndice !== tarea) return;
        const inicio = performance.now(), cantidad = posiciones.length / 3;
        do {
            const fin = Math.min(cantidad, tarea.cursor + 2048), lote = [];
            for (let i = tarea.cursor; i < fin; i++) if (!visibles || visibles[i]) lote.push(i);
            indice.addAll(lote); tarea.cursor = fin; tarea.cantidad += lote.length;
        } while (tarea.cursor < cantidad && performance.now() - inicio < 8);
        tarea.completo = tarea.cursor >= cantidad;
        if (tarea.completo) prepararRadiosEspaciales(indice, tamanos);
        self.postMessage({tipo: 'indice', revision: tarea.revision, cantidad: tarea.cantidad, completo: tarea.completo, tiempo: performance.now() - tarea.inicio});
        if (tarea.cursor < cantidad) setTimeout(agregar, 0);
    }; agregar();
}
self.onmessage = ({data}) => {
    if (data.tipo === 'fuente') {nodos = data.nodos; jerarquia = crearIndiceRama3D(nodos); tareaIndice = null; indice = null; estructura = null; raizEstructura = ''; return;}
    if (data.tipo === 'indexar') {indexar(data.posiciones, data.revision, data.visibles, data.tamanos, data.idIndice); return;}
    if (data.tipo === 'revision') {
        if (!tareaIndice || tareaIndice.idIndice !== data.idIndice) {self.postMessage({tipo: 'indice', revision: data.revision, falta: true}); return;}
        tareaIndice.revision = data.revision;
        self.postMessage({tipo: 'indice', revision: data.revision, cantidad: tareaIndice.cantidad, completo: tareaIndice.completo,
            tiempo: performance.now() - tareaIndice.inicio, reutilizado: true}); return;
    }
    if (data.tipo === 'seleccionar') {
        const estadisticas = {}, inicio = performance.now();
        const nodo = tareaIndice?.revision === data.revision
            ? buscarNodoEspacial(indice, tareaIndice.posiciones, data.origen, data.direccion, data.escalaPixel, estadisticas,
                data.mundos ? {...data.mundos, tamanos: tareaIndice.tamanos} : undefined) : -1;
        self.postMessage({tipo: 'seleccion', id: data.id, nodo, estadisticas, tiempo: performance.now() - inicio}); return;
    }
    if (data.tipo === 'encuadre') {
        const estadisticas = {}, inicio = performance.now();
        const indices = tareaIndice?.revision === data.revision && tareaIndice.completo
            ? nodosEnEncuadre(indice, tareaIndice.posiciones, data.planos, estadisticas) : null;
        self.postMessage({tipo: 'encuadre', id: data.id, indices, estadisticas, tiempo: performance.now() - inicio}, indices ? [indices.buffer] : []); return;
    }
    try {
        const completo = data.rango && raizEstructura === data.raiz && estructura
            ? estructura : distribuirRama3D(nodos, data.raiz, jerarquia, Boolean(data.rango));
        const compacta = {claves: completo.claves, padres: completo.padres, grupos: completo.grupos};
        // Sólo una topología pequeña: los filtros no clonan posiciones ni el catálogo completo.
        if (bytesDibujo3D(compacta) <= maxBytesEstructura) {
            estructura = data.rango ? compacta : {...compacta, padres: compacta.padres.slice(), grupos: compacta.grupos.slice()};
            raizEstructura = data.raiz;
        } else {estructura = null; raizEstructura = '';}
        const dibujo = filtrarRama3D(completo, jerarquia.porClave, data.rango);
        self.postMessage({id: data.id, dibujo}, [dibujo.posiciones.buffer, dibujo.padres.buffer,
            dibujo.grupos.buffer, dibujo.profundidades.buffer, ...(dibujo.visibles ? [dibujo.visibles.buffer, dibujo.origenes.buffer] : [])]);
    } catch (error) {self.postMessage({id: data.id, error: error.message});}
};
