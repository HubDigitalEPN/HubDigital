/** Conserva el nombre original registrado; la URL pública no contiene el formato. */
export function nombreDescargaImagen(nombreOriginal) {
    if (typeof nombreOriginal !== 'string') return 'imagen';
    const nombre = nombreOriginal.replaceAll('\\', '/').split('/').at(-1)
        .replace(/[\u0000-\u001f\u007f<>:"|?*]/g, '_').trim();
    return nombre && nombre !== '.' && nombre !== '..' ? nombre : 'imagen';
}

export function crearVisorImagen() {
    return {
        url: '', alt: '', filename: 'imagen', atribuciones: {}, vista: '', invocador: null, dialogo: null,
        init() { this.dialogo = this.$el; },
        abrir(datos) {
            this.invocador = datos.invocador || document.activeElement;
            this.url = datos.url; this.alt = datos.alt || ''; this.filename = nombreDescargaImagen(datos.nombreArchivo);
            this.atribuciones = datos.atribuciones || {}; this.vista = datos.vista || this.atribuciones.view || '';
            if (!this.dialogo.open) this.dialogo.showModal();
            this.$nextTick(() => this.$refs.cerrar.focus({preventScroll:true}));
        },
        cerrar() { this.dialogo.close(); },
        restaurarFoco() { if (this.invocador?.isConnected !== false) this.invocador?.focus({preventScroll:true}); },
    };
}
