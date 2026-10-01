(() => {
    const iniciar = () => {
        const datos = window.portalEstadisticasDatos;
        if (!datos) return;
        const filtros = document.getElementById('filtros-estadisticas');
        const colores = ['#17699b', '#d17d28', '#568c59', '#8c62a5', '#b94e6b', '#71828d', '#a18a29', '#3f8d90'];
        const filos = Object.keys(datos.filos);
        const colorFilo = filo => filo === 'Sin filo' ? '#74818c' : colores[Math.max(0, filos.indexOf(filo)) % colores.length];
        const aviso = document.getElementById('aviso-estadisticas');
        let timeout;
        const informar = mensaje => {
            if (!aviso) return;
            aviso.textContent = mensaje;
            aviso.hidden = false;
            clearTimeout(timeout);
            timeout = setTimeout(() => { aviso.hidden = true; }, 4200);
        };
        const escaparCsv = valor => {
            let texto = String(valor ?? '');
            if (/^\s*[=+@]/.test(texto) || (/^\s*-/.test(texto) && !/^\s*-\d+(?:[.,]\d+)?\s*$/.test(texto))) texto = "'" + texto;
            return '"' + texto.replaceAll('"', '""') + '"';
        };
        const descargarCsv = (nombre, cabecera, filas) => {
            const contenido = '\uFEFF' + [cabecera, ...filas].map(fila => fila.map(escaparCsv).join(';')).join('\r\n');
            const url = URL.createObjectURL(new Blob([contenido], {type: 'text/csv;charset=utf-8'}));
            const enlace = document.createElement('a');
            enlace.href = url;
            enlace.download = nombre;
            document.body.appendChild(enlace);
            enlace.click();
            enlace.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        };
        const exportaciones = {
            mapa: ['cuadriculas-por-filo.csv', ['Latitud de cuadrícula', 'Longitud de cuadrícula', 'Filo', 'Registros'], datos.mapa.flatMap(p => Object.entries(p.filos).map(([filo, n]) => [p.lat, p.lon, filo, n]))],
            filos: ['composicion-taxonomica.csv', ['Filo', 'Registros'], Object.entries(datos.filos).map(([filo, n]) => [filo, n])],
            riqueza: ['riqueza-documentada.csv', ['Provincia', 'Especies', 'Registros'], datos.riqueza.map(p => [p.provincia, p.especies, p.registros])],
            decadas: ['cobertura-temporal.csv', ['Década', 'Especies', 'Registros'], datos.decadas.map(p => [p.decada, p.especies, p.registros])],
            aptitud: ['completitud-analisis.csv', ['Registros', 'Con especie fecha y coordenadas visibles'], [[datos.resumen.registros, datos.resumen.aptos]]],
            raras: ['especies-pocos-registros.csv', ['Especie', 'Registros'], datos.raras.map(p => [p.nombre, p.total])],
        };
        const cerrarMenus = () => document.querySelectorAll('.atlas-menu').forEach(menu => {
            menu.hidden = true;
            menu.closest('.atlas-panel')?.querySelector('[data-menu-trigger]')?.setAttribute('aria-expanded', 'false');
        });
        document.querySelectorAll('[data-menu-trigger]').forEach(boton => boton.addEventListener('click', evento => {
            evento.stopPropagation();
            const menu = boton.closest('.atlas-panel')?.querySelector('.atlas-menu');
            if (!menu) return;
            const abrir = menu.hidden;
            cerrarMenus();
            menu.hidden = !abrir;
            boton.setAttribute('aria-expanded', String(abrir));
            if (abrir) menu.querySelector('button')?.focus();
        }));
        document.addEventListener('click', evento => { if (!evento.target.closest('.atlas-menu, [data-menu-trigger]')) cerrarMenus(); });
        document.addEventListener('keydown', evento => {
            if (evento.key === 'Escape') {
                document.querySelector('.atlas-menu:not([hidden])')?.closest('.atlas-panel')?.querySelector('[data-menu-trigger]')?.focus();
                cerrarMenus();
            }
        });
        document.querySelectorAll('.atlas-menu').forEach(menu => menu.addEventListener('keydown', evento => {
            const opciones = [...menu.querySelectorAll('button')];
            const indice = opciones.indexOf(document.activeElement);
            if (evento.key === 'ArrowDown' || evento.key === 'ArrowUp') {
                evento.preventDefault();
                opciones[(indice + (evento.key === 'ArrowDown' ? 1 : -1) + opciones.length) % opciones.length]?.focus();
            }
            if (evento.key === 'Home') { evento.preventDefault(); opciones[0]?.focus(); }
            if (evento.key === 'End') { evento.preventDefault(); opciones.at(-1)?.focus(); }
        }));
        const modal = document.getElementById('ayuda-estadisticas');
        let retornoFoco;
        document.querySelectorAll('[data-ayuda]').forEach(boton => boton.addEventListener('click', () => {
            const plantilla = document.getElementById('guia-' + boton.dataset.ayuda);
            if (!modal || !plantilla) return;
            retornoFoco = boton;
            modal.querySelector('#titulo-ayuda-estadisticas').textContent = plantilla.dataset.titulo;
            modal.querySelector('#contenido-ayuda-estadisticas').replaceChildren(plantilla.content.cloneNode(true));
            modal.showModal();
            modal.querySelector('.atlas-help-close').focus();
        }));
        modal?.querySelector('.atlas-help-close')?.addEventListener('click', () => modal.close());
        modal?.addEventListener('click', evento => { if (evento.target === modal) modal.close(); });
        modal?.addEventListener('close', () => retornoFoco?.focus());

        const urlRegistros = new URL(datos.catalogoUrl, window.location.origin);
        urlRegistros.searchParams.set('vista', 'registros');
        if (datos.seleccion.taxon) urlRegistros.searchParams.set('ft', datos.seleccion.taxon);
        if (datos.seleccion.provincia) urlRegistros.searchParams.set('fprov', datos.seleccion.provincia);
        if (datos.seleccion.filo) urlRegistros.searchParams.set('fph', datos.seleccion.filo);
        if (datos.seleccion.desde) urlRegistros.searchParams.set('ffd', datos.seleccion.desde + '-01-01');
        if (datos.seleccion.hasta) urlRegistros.searchParams.set('ffh', datos.seleccion.hasta + '-12-31');
        if (datos.seleccion.mes) urlRegistros.searchParams.set('fmes', datos.seleccion.mes);
        if (datos.seleccion.identificacion) urlRegistros.searchParams.set('fid', datos.seleccion.identificacion);
        if (datos.seleccion.ubicacion) urlRegistros.searchParams.set('fgeo', datos.seleccion.ubicacion);
        if (datos.seleccion.aptitud === 'completos') urlRegistros.searchParams.set('fap', '1');
        if (datos.seleccion.colector) urlRegistros.searchParams.set('fco', datos.seleccion.colector);
        if (datos.seleccion.metodo) urlRegistros.searchParams.set('fm[0]', datos.seleccion.metodo);
        const filtrarCompletos = () => {
            if (!filtros) return;
            filtros.elements.aptitud.value = 'completos';
            filtros.requestSubmit();
        };
        document.querySelector('[data-filter-ready]')?.addEventListener('click', filtrarCompletos);
        let mapa;
        let puntos = [];
        let filoActivo = '';
        const ecuador = () => mapa?.fitBounds([[-5.1, -81.3], [1.9, -75]], {padding: [14, 14], maxZoom: 7});
        const pintarPuntos = () => {
            puntos.forEach(p => p.remove());
            puntos = [];
            if (!mapa) return;
            datos.mapa.forEach(celda => {
                const lat = Number(celda.lat), lon = Number(celda.lon);
                if (!Number.isFinite(lat) || !Number.isFinite(lon) || lat < -90 || lat > 90 || lon < -180 || lon > 180) return;
                const composicion = Object.entries(celda.filos).sort((a, b) => Number(b[1]) - Number(a[1]));
                const total = filoActivo ? Number(celda.filos[filoActivo] || 0) : Number(celda.total);
                if (!total) return;
                const filo = filoActivo || composicion[0]?.[0] || 'Sin filo';
                const punto = L.circleMarker([lat, lon], {
                    radius: Math.min(17, 4 + Math.sqrt(total) * .7),
                    color: '#17394f', weight: .9, fillColor: colorFilo(filo), fillOpacity: .76,
                }).addTo(mapa);
                const detalle = document.createElement('div');
                const titulo = document.createElement('strong');
                titulo.textContent = total.toLocaleString('es-EC') + ' registros en la cuadrícula';
                detalle.append(titulo);
                (filoActivo ? [[filoActivo, total]] : composicion).forEach(([nombre, n]) => {
                    const linea = document.createElement('div');
                    linea.textContent = nombre + ': ' + Number(n).toLocaleString('es-EC');
                    detalle.append(linea);
                });
                punto.bindPopup(detalle);
                puntos.push(punto);
            });
        };
        const marcarLeyenda = () => document.querySelectorAll('[data-filo-mapa]').forEach(boton => {
            boton.setAttribute('aria-pressed', String(boton.dataset.filoMapa === filoActivo));
        });
        const cambiarFilo = filo => {
            filoActivo = filoActivo === filo ? '' : filo;
            marcarLeyenda();
            pintarPuntos();
            informar(filoActivo ? 'Mapa filtrado visualmente por ' + filoActivo + '.' : 'Mapa con todos los filos.');
        };
        if (window.L) {
            mapa = L.map('mapa-coleccion', {scrollWheelZoom: false, preferCanvas: true, boxZoom: true});
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors', maxZoom: 18,
            }).addTo(mapa);
            L.control.scale({imperial: false}).addTo(mapa);
            ecuador();
            pintarPuntos();
            mapa.on('boxzoomend', evento => {
                const b = evento.boxZoomBounds;
                if (!b) return;
                const url = new URL(urlRegistros);
                url.searchParams.set('flat', b.getSouth().toFixed(6));
                url.searchParams.set('flax', b.getNorth().toFixed(6));
                url.searchParams.set('flon', b.getWest().toFixed(6));
                url.searchParams.set('flox', b.getEast().toFixed(6));
                window.location.assign(url.toString());
            });
        } else {
            const contenedor = document.getElementById('mapa-coleccion');
            if (contenedor) contenedor.textContent = 'No se pudo cargar el mapa. Recarga la página.';
        }
        const leyenda = document.getElementById('leyenda-mapa');
        if (leyenda) {
            [...filos, ...(datos.mapa.some(p => p.filos['Sin filo']) ? ['Sin filo'] : [])].forEach(filo => {
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.dataset.filoMapa = filo;
                boton.setAttribute('aria-pressed', 'false');
                const punto = document.createElement('span');
                punto.className = 'atlas-legend-dot';
                punto.style.backgroundColor = colorFilo(filo);
                boton.append(punto, document.createTextNode(filo));
                boton.addEventListener('click', () => cambiarFilo(filo));
                leyenda.append(boton);
            });
        }
        document.querySelectorAll('.atlas-taxon-row[data-filo-mapa]').forEach(boton => boton.addEventListener('click', () => cambiarFilo(boton.dataset.filoMapa)));

        const graficos = [];
        if (window.HubDigitalChart) {
            const Chart = window.HubDigitalChart;
            Chart.defaults.font.family = 'system-ui, sans-serif';
            Chart.defaults.color = '#52677a';
            const comun = {responsive: true, maintainAspectRatio: false, animation: false,
                plugins: {legend: {display: false}, tooltip: {callbacks: {label: c => Number(c.raw).toLocaleString('es-EC') + ' especies'}}}};
            const riqueza = document.getElementById('grafico-riqueza');
            if (riqueza) graficos.push(new Chart(riqueza, {type: 'bar',
                data: {labels: datos.riqueza.map(p => p.provincia), datasets: [{data: datos.riqueza.map(p => Number(p.especies)), backgroundColor: '#508bb1'}]},
                options: {...comun, indexAxis: 'y', onClick: (_, elementos) => {
                    if (!elementos.length || !filtros) return;
                    filtros.elements.provincia.value = datos.riqueza[elementos[0].index].provincia;
                    filtros.requestSubmit();
                }, scales: {x: {beginAtZero: true, ticks: {precision: 0}}, y: {ticks: {font: {size: 10}}}}}
            }));
            const decadas = document.getElementById('grafico-decadas');
            if (decadas) graficos.push(new Chart(decadas, {type: 'bar',
                data: {labels: datos.decadas.map(p => p.decada), datasets: [{data: datos.decadas.map(p => Number(p.especies)), backgroundColor: '#27789e'}]},
                options: {...comun, onClick: (_, elementos) => {
                    if (!elementos.length || !filtros) return;
                    const anio = Number(datos.decadas[elementos[0].index].decada);
                    filtros.elements.desde.value = anio;
                    filtros.elements.hasta.value = anio + 9;
                    filtros.requestSubmit();
                }, scales: {x: {grid: {display: false}}, y: {beginAtZero: true, ticks: {precision: 0}}}}
            }));
        }
        [['grafico-riqueza', datos.riqueza.length], ['grafico-decadas', datos.decadas.length]].forEach(([id, n]) => {
            if (n && window.HubDigitalChart) return;
            const lienzo = document.getElementById(id);
            if (!lienzo) return;
            lienzo.hidden = true;
            const vacio = document.createElement('p');
            vacio.className = 'atlas-chart-empty';
            vacio.textContent = n ? 'No se pudo cargar el gráfico.' : 'No hay datos visibles en esta selección.';
            lienzo.parentElement.append(vacio);
        });
        document.querySelectorAll('.atlas-menu [data-action]').forEach(boton => boton.addEventListener('click', () => {
            const panel = boton.closest('.atlas-panel');
            const clave = panel?.dataset.panel;
            cerrarMenus();
            panel?.querySelector('[data-menu-trigger]')?.focus();
            if (boton.dataset.action === 'download') {
                if (clave === 'lista') window.location.assign(datos.listaUrl);
                else if (exportaciones[clave]) descargarCsv(...exportaciones[clave]);
            }
            if (boton.dataset.action === 'open-records') window.location.assign(urlRegistros.toString());
            if (boton.dataset.action === 'open-mapped-records') {
                const url = new URL(urlRegistros);
                url.searchParams.set('fgeo', '1');
                window.location.assign(url.toString());
            }
            if (boton.dataset.action === 'filter-ready') filtrarCompletos();
        }));
        document.addEventListener('toggle', evento => {
            if (evento.target.matches('.atlas-filter-drawer') && mapa) setTimeout(() => mapa.invalidateSize(), 100);
        }, true);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar, {once: true});
    else iniciar();
})();
