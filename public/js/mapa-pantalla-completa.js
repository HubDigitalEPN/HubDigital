window.hubMapaPantallaCompleta = mapa => {
    const contenedor = mapa.getContainer();
    if (contenedor.querySelector('[data-mapa-fullscreen]')) return;
    const control = document.createElement('div');
    control.className = 'leaflet-control leaflet-bar';
    control.style.cssText = 'position:absolute;right:10px;top:10px;z-index:800;background:white';
    const boton = document.createElement('button');
    boton.type = 'button'; boton.dataset.mapaFullscreen = '';
    boton.textContent = '⛶'; boton.style.cssText = 'width:44px;height:44px;font-size:28px;color:#17375e;background:white;cursor:pointer;border:0';
    const actualizar = () => {
        const completo = document.fullscreenElement === contenedor || contenedor.classList.contains('hub-map-fullscreen');
        const titulo = completo ? 'Salir de pantalla completa' : 'Pantalla completa';
        boton.title = boton.ariaLabel = window.hubTexto?.(titulo) || titulo;
        boton.setAttribute('aria-pressed', String(completo));
        requestAnimationFrame(() => mapa.invalidateSize({pan:false}));
    };
    boton.addEventListener('click', async evento => {
        evento.stopPropagation();
        try {
            if (document.fullscreenElement === contenedor) await document.exitFullscreen();
            else if (contenedor.requestFullscreen) await contenedor.requestFullscreen();
            else contenedor.classList.toggle('hub-map-fullscreen');
        } catch { contenedor.classList.toggle('hub-map-fullscreen'); }
        actualizar();
    });
    for (const nombre of ['pointerdown','dblclick','wheel','mousedown','touchstart']) control.addEventListener(nombre, evento => evento.stopPropagation());
    const escapar = evento => { if (evento.key === 'Escape' && contenedor.classList.contains('hub-map-fullscreen')) { contenedor.classList.remove('hub-map-fullscreen'); actualizar(); } };
    document.addEventListener('fullscreenchange', actualizar); document.addEventListener('keydown', escapar);
    mapa.on('unload', () => { document.removeEventListener('fullscreenchange', actualizar); document.removeEventListener('keydown', escapar); });
    control.append(boton); contenedor.append(control); actualizar();
};
window.dispatchEvent(new Event('hub-mapa-controles-listos'));
