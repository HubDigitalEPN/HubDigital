import {colorFilo} from './portal-map-model.js';

export function valoresPanel(tipo, datos) {
    const meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    if (tipo === 'filos') return Object.entries(datos).map(([label, value]) => ({label, value: Number(value)}));
    if (tipo === 'estacionalidad') return meses.map((label, i) => ({label, value: Number(datos.find(fila => Number(fila.mes) === i + 1)?.registros || 0)}));
    return datos.map(fila => ({label: tipo === 'riqueza' ? fila.provincia : tipo === 'decadas' ? `${fila.decada}–${Number(fila.decada)+9}` : tipo === 'altitud' ? `${fila.desde}–${fila.hasta} m` : (fila.etiqueta || fila.metodo), value: Number(fila.registros), x: Number(fila.decada)}));
}

export function configuracionGraficoPanel(tipo, datos, titulo, seleccionar) {
    const valores = valoresPanel(tipo, datos);
    const puntos = tipo === 'altitud' || tipo === 'riqueza';
    const circular = tipo === 'filos' || tipo === 'metodos';
    const configuracion = {
        type: puntos ? 'scatter' : circular ? 'doughnut' : tipo === 'decadas' ? 'line' : 'bar',
        data: {labels: valores.map(fila => fila.label), datasets: [{label: 'Registros públicos',
            data: puntos ? valores.map((fila, i) => ({x: fila.value, y: i})) : tipo === 'decadas' ? valores.map(fila => ({x: fila.x, y: fila.value})) : valores.map(fila => fila.value),
            backgroundColor: tipo === 'filos' ? valores.map(fila => colorFilo(fila.label)) : circular ? ['#17699b', '#d17d28', '#568c59', '#b94e6b', '#8c62a5', '#3f8d90'] : '#17699b', borderColor: '#17699b', pointRadius: puntos ? 6 : 4, borderWidth: 2, tension: 0,
        }]},
        options: {responsive: true, maintainAspectRatio: false, animation: false,
            plugins: {legend: {display: circular, position: 'bottom'}, tooltip: {callbacks: {title: elementos => valores[elementos[0]?.dataIndex]?.label || titulo, label: elemento => `${valores[elemento.dataIndex]?.value.toLocaleString('es-EC') || 0} registros`}}},
            onClick: (_evento, elementos) => {
                const indice = elementos[0]?.index;
                if (indice === undefined) return;
                const fila = tipo === 'estacionalidad' ? {mes: indice + 1} : datos[indice];
                if (tipo === 'filos') seleccionar.seleccionarFilo(valores[indice].label);
                else if (tipo === 'riqueza') seleccionar.seleccionarProvincia(fila.provincia);
                else if (tipo === 'decadas') seleccionar.seleccionarDecada(Number(fila.decada));
                else if (tipo === 'estacionalidad') seleccionar.seleccionarMes(fila.mes);
                else if (tipo === 'altitud') seleccionar.seleccionarAltitud(Number(fila.desde), Number(fila.hasta));
                else if (tipo === 'metodos') seleccionar.seleccionarMetodo(fila.metodo);
            },
        },
    };
    if (!circular) configuracion.options.scales = puntos ? {
        x: {beginAtZero: true, title: {display: true, text: 'Registros'}},
        y: {reverse: tipo === 'riqueza', min: -.5, max: Math.max(.5, valores.length - .5), ticks: {stepSize: 1, callback: valor => valores[valor]?.label || ''}},
    } : {x: tipo === 'decadas' ? {type: 'linear', ticks: {stepSize: 10}, title: {display: true, text: 'Década inicial'}} : {}, y: {beginAtZero: true, ticks: {precision: 0}, title: {display: true, text: 'Registros'}}};
    return configuracion;
}
