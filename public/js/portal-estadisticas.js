(() => {
    const iniciar = () => {
        const datos = window.portalEstadisticasDatos;
        if (!datos) return;

        const azul = '#1d6e9f';
        const azulClaro = '#83abc8';
        const aviso = document.getElementById('aviso-estadisticas');
        let temporizadorAviso;
        const informar = mensaje => {
            if (!aviso) return;
            aviso.textContent = mensaje;
            aviso.hidden = false;
            clearTimeout(temporizadorAviso);
            temporizadorAviso = setTimeout(() => { aviso.hidden = true; }, 4500);
        };

        const csvCampo = valor => {
            let texto = String(valor ?? '');
            if (/^[\s]*[=+\-@]/.test(texto)) texto = "'" + texto;
            return '"' + texto.replaceAll('"', '""') + '"';
        };
        const descargarCsv = (nombre, encabezados, filas) => {
            const contenido = '\uFEFF' + [encabezados, ...filas].map(fila => fila.map(csvCampo).join(';')).join('\r\n');
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
            mapa: ['cuadriculas-publicas.csv', ['Latitud de cuadrícula', 'Longitud de cuadrícula', 'Registros'], datos.mapa.map(p => [p.lat, p.lon, p.total])],
            filos: ['registros-por-filo.csv', ['Filo', 'Registros'], Object.entries(datos.filos).map(([filo, total]) => [filo, total])],
            anios: ['registros-por-anio.csv', ['Año de colecta', 'Registros'], datos.anios.map(p => [p.anio, p.total])],
            provincias: ['registros-por-provincia.csv', ['Provincia', 'Registros'], datos.provincias.map(p => [p.provincia, p.total])],
            calidad: ['documentacion-visible.csv', ['Indicador', 'Registros', 'Porcentaje'], [
                ['Con identificación de especie', datos.resumen.identificados],
                ['Con fecha visible', datos.resumen.fechados],
                ['Con ubicación visible', datos.resumen.georreferenciados],
            ].map(([etiqueta, valor]) => [etiqueta, valor, Number(datos.resumen.registros) ? (Number(valor) / Number(datos.resumen.registros) * 100).toFixed(1) : '0.0'])],
            metodos: ['metodos-de-colecta.csv', ['Método de colecta', 'Registros'], datos.metodos.map(p => [p.metodo, p.total])],
        };

        const cerrarMenus = () => document.querySelectorAll('.atlas-menu').forEach(menu => {
            menu.hidden = true;
            menu.closest('.atlas-panel')?.querySelector('[data-menu-trigger]')?.setAttribute('aria-expanded', 'false');
        });
        document.querySelectorAll('[data-menu-trigger]').forEach(disparador => disparador.addEventListener('click', evento => {
            evento.stopPropagation();
            const menu = disparador.closest('.atlas-panel')?.querySelector('.atlas-menu');
            if (!menu) return;
            const abrir = menu.hidden;
            cerrarMenus();
            menu.hidden = !abrir;
            disparador.setAttribute('aria-expanded', String(abrir));
            if (abrir) menu.querySelector('button')?.focus();
        }));
        document.addEventListener('click', evento => {
            if (!evento.target.closest('.atlas-menu')) cerrarMenus();
        });
        document.addEventListener('keydown', evento => {
            if (evento.key === 'Escape') {
                const abierto = document.querySelector('.atlas-menu:not([hidden])');
                abierto?.closest('.atlas-panel')?.querySelector('[data-menu-trigger]')?.focus();
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
            const clave = boton.dataset.ayuda;
            const plantilla = document.getElementById('guia-' + clave);
            if (!modal || !plantilla) return;
            retornoFoco = boton;
            modal.querySelector('#titulo-ayuda-estadisticas').textContent = plantilla.dataset.titulo || 'Ayuda';
            modal.querySelector('#contenido-ayuda-estadisticas').replaceChildren(plantilla.content.cloneNode(true));
            modal.showModal();
            modal.querySelector('.atlas-help-close').focus();
        }));
        modal?.querySelector('.atlas-help-close')?.addEventListener('click', () => modal.close());
        modal?.addEventListener('click', evento => { if (evento.target === modal) modal.close(); });
        modal?.addEventListener('close', () => retornoFoco?.focus());

        let mapa;
        let circulos = [];
        let volumen = true;
        const vistaEcuador = () => mapa?.fitBounds([[-5.1, -81.3], [1.9, -75.0]], {padding: [16, 16], maxZoom: 7});
        const alternarMapa = () => {
            volumen = !volumen;
            circulos.forEach(({circulo, total}) => circulo.setRadius(volumen ? Math.min(19, 4 + Math.sqrt(total) * .8) : 5));
            document.querySelector('[data-action="toggle-map"]')?.replaceChildren(document.createTextNode(volumen ? 'Mostrar presencia' : 'Mostrar volumen'));
            informar(volumen ? 'El tamaño indica la cantidad de registros.' : 'Los círculos muestran presencia por cuadrícula.');
        };
        if (window.L) {
            mapa = L.map('mapa-coleccion', {scrollWheelZoom: false, preferCanvas: true, boxZoom: true});
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors', maxZoom: 18,
            }).addTo(mapa);
            L.control.scale({imperial: false}).addTo(mapa);
            vistaEcuador();
            circulos = datos.mapa.filter(p => Number.isFinite(Number(p.lat)) && Number.isFinite(Number(p.lon)) && Number(p.lat) >= -90 && Number(p.lat) <= 90 && Number(p.lon) >= -180 && Number(p.lon) <= 180).map(p => {
                const total = Number(p.total) || 0;
                const circulo = L.circleMarker([Number(p.lat), Number(p.lon)], {
                    radius: Math.min(19, 4 + Math.sqrt(total) * .8), color: '#164c72', weight: 1,
                    fillColor: azul, fillOpacity: .62,
                }).addTo(mapa);
                circulo.bindPopup(total.toLocaleString('es-EC') + ' registros en esta cuadrícula');
                return {circulo, total};
            });
            mapa.on('boxzoomend', evento => {
                const limites = evento.boxZoomBounds;
                if (!limites) return;
                const url = new URL(datos.catalogoUrl, window.location.origin);
                url.searchParams.set('vista', 'registros');
                url.searchParams.set('flat', limites.getSouth().toFixed(6));
                url.searchParams.set('flax', limites.getNorth().toFixed(6));
                url.searchParams.set('flon', limites.getWest().toFixed(6));
                url.searchParams.set('flox', limites.getEast().toFixed(6));
                window.location.assign(url.toString());
            });
        } else {
            const contenedor = document.getElementById('mapa-coleccion');
            if (contenedor) {
                contenedor.style.display = 'grid';
                contenedor.style.placeItems = 'center';
                contenedor.textContent = 'No se pudo cargar el mapa. Recarga la página para intentarlo nuevamente.';
            }
        }

        const graficos = [];
        document.querySelectorAll('[data-metodo]').forEach(boton => boton.addEventListener('click', () => {
            const filtros = document.getElementById('filtros-estadisticas');
            const selector = filtros?.elements.namedItem('metodo');
            if (!selector) return;
            selector.value = boton.dataset.metodo;
            filtros.requestSubmit();
        }));
        if (window.HubDigitalChart) {
            const Chart = window.HubDigitalChart;
            const filtros = document.getElementById('filtros-estadisticas');
            Chart.defaults.font.family = 'system-ui, sans-serif';
            Chart.defaults.color = '#53687a';
            const comun = {responsive: true, maintainAspectRatio: false, animation: false,
                plugins: {legend: {display: false}, tooltip: {callbacks: {label: contexto => Number(contexto.raw).toLocaleString('es-EC') + ' registros'}}}};
            const anios = document.getElementById('grafico-anios');
            if (anios) graficos.push(new Chart(anios, {type: 'bar', data: {labels: datos.anios.map(p => p.anio), datasets: [{data: datos.anios.map(p => Number(p.total)), backgroundColor: azul, hoverBackgroundColor: '#0d456d', maxBarThickness: 24}]}, options: {...comun, onClick: (_, elementos) => {
                if (!elementos.length || !filtros) return;
                const anio = datos.anios[elementos[0].index]?.anio;
                if (!anio) return;
                filtros.elements.desde.value = anio;
                filtros.elements.hasta.value = anio;
                filtros.requestSubmit();
            }, scales: {x: {grid: {display: false}, ticks: {maxTicksLimit: 6, font: {size: 10}}}, y: {beginAtZero: true, grid: {color: '#e5edf3'}, ticks: {precision: 0, font: {size: 10}}}}}}));
            const provincias = document.getElementById('grafico-provincias');
            if (provincias) graficos.push(new Chart(provincias, {type: 'bar', data: {labels: datos.provincias.map(p => p.provincia), datasets: [{data: datos.provincias.map(p => Number(p.total)), backgroundColor: azulClaro, hoverBackgroundColor: azul, maxBarThickness: 15}]}, options: {...comun, indexAxis: 'y', onClick: (_, elementos) => {
                if (!elementos.length || !filtros) return;
                const provincia = datos.provincias[elementos[0].index]?.provincia;
                if (!provincia) return;
                filtros.elements.provincia.value = provincia;
                filtros.requestSubmit();
            }, scales: {x: {beginAtZero: true, grid: {color: '#e5edf3'}, ticks: {precision: 0, font: {size: 10}}}, y: {grid: {display: false}, ticks: {font: {size: 9}}}}}}));
        }
        [['grafico-anios', datos.anios.length, 'No hay fechas visibles para estos filtros.'],
            ['grafico-provincias', datos.provincias.length, 'No hay provincias visibles para estos filtros.']]
            .forEach(([id, cantidad, mensaje]) => {
                if (cantidad > 0 && window.HubDigitalChart) return;
                const lienzo = document.getElementById(id);
                if (!lienzo) return;
                lienzo.hidden = true;
                const vacio = document.createElement('p');
                vacio.className = 'atlas-chart-empty';
                vacio.textContent = window.HubDigitalChart ? mensaje : 'No se pudo cargar el gráfico. Recarga la página para intentarlo nuevamente.';
                lienzo.parentElement.appendChild(vacio);
            });

        document.querySelectorAll('.atlas-menu [data-action]').forEach(boton => boton.addEventListener('click', async () => {
            const panel = boton.closest('.atlas-panel');
            const clave = panel?.dataset.panel;
            const accion = boton.dataset.action;
            cerrarMenus();
            panel?.querySelector('[data-menu-trigger]')?.focus();
            if (!clave) return;
            if (accion === 'reset-map') { vistaEcuador(); informar('Mapa centrado en Ecuador.'); }
            if (accion === 'toggle-map') alternarMapa();
            if (accion === 'download') {
                if (clave === 'lista') window.location.assign(datos.listaUrl);
                else if (exportaciones[clave]) descargarCsv(...exportaciones[clave]);
            }
            if (accion === 'copy') {
                const url = new URL(window.location.href);
                url.hash = 'panel-' + clave;
                try {
                    await navigator.clipboard.writeText(url.toString());
                    informar('Enlace al panel copiado.');
                } catch {
                    informar('No fue posible copiar el enlace en este navegador.');
                }
            }
            if (accion === 'fullscreen') {
                try {
                    if (document.fullscreenElement === panel) await document.exitFullscreen();
                    else if (panel.requestFullscreen) await panel.requestFullscreen();
                    else informar('Este navegador no permite ampliar el panel.');
                } catch { informar('No fue posible ampliar el panel.'); }
            }
        }));
        document.addEventListener('fullscreenchange', () => {
            setTimeout(() => { mapa?.invalidateSize(); graficos.forEach(grafico => grafico.resize()); }, 100);
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar, {once: true});
    else iniciar();
})();
