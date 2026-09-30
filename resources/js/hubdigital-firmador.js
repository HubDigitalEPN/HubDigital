/** La interfaz entrega las credenciales temporales al firmador Java del servidor. */
window.hubDigitalFirmador = (config) => ({
    dialogo: false,
    estado: 'listo',
    progreso: '',
    error: '',

    abrirDialogo() {
        this.estado = 'listo';
        this.error = '';
        this.dialogo = true;
        this.$nextTick(() => this.$refs.certificado?.focus());
    },

    cerrarDialogo() {
        if (this.estado === 'procesando') return;
        this.dialogo = false;
        this.limpiarCamposCredenciales();
        this.$nextTick(() => this.$refs.abrirFirma?.focus());
    },

    async firmar() {
        if (this.estado === 'procesando') return;
        this.error = '';
        const archivo = this.$refs.certificado?.files?.[0];
        let clave = this.$refs.clave?.value ?? '';
        if (!archivo || !/\.(p12|pfx)$/i.test(archivo.name)) {
            this.error = 'Selecciona un certificado .p12 o .pfx.';
            this.limpiarCamposCredenciales();
            return;
        }
        if (clave.length === 0) {
            this.error = 'Ingresa la contraseña del certificado.';
            this.limpiarCamposCredenciales();
            return;
        }

        this.estado = 'procesando';
        this.progreso = 'Java está firmando y verificando el documento oficial…';
        const formulario = new FormData();
        const controlador = new AbortController();
        const temporizador = window.setTimeout(() => controlador.abort(), 90000);
        try {
            formulario.append('certificado', archivo);
            formulario.append('clave_certificado', clave);
            formulario.append('original_referencia', config.originalReference ?? '');
            formulario.append('original_sha256', config.originalSha256 ?? '');
            clave = '';
            this.limpiarCamposCredenciales();

            const respuesta = await fetch(config.uploadUrl, {
                method: 'POST',
                credentials: 'same-origin',
                signal: controlador.signal,
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: formulario,
            });
            const cuerpo = await respuesta.json().catch(() => ({}));
            if (!respuesta.ok) {
                throw new Error(cuerpo.message ?? 'No se pudo firmar y validar el documento.');
            }
            this.estado = 'completado';
            this.progreso = cuerpo.message ?? 'Documento firmado y validado por Java.';
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: this.progreso } }));
            window.setTimeout(() => window.location.reload(), 900);
        } catch (error) {
            this.estado = 'error';
            this.progreso = '';
            this.error = error?.name === 'AbortError'
                ? 'La firma demoró demasiado. Actualiza la pantalla para comprobar su estado.'
                : this.mensajeSeguro(error);
        } finally {
            window.clearTimeout(temporizador);
            formulario.delete('clave_certificado');
            formulario.delete('certificado');
            clave = '';
            this.limpiarCamposCredenciales();
        }
    },

    limpiarCamposCredenciales() {
        if (this.$refs.clave) this.$refs.clave.value = '';
        if (this.$refs.certificado) this.$refs.certificado.value = '';
    },

    mensajeSeguro(error) {
        const mensaje = String(error?.message ?? 'No se pudo firmar el documento.');
        if (/password|passphrase|PKCS#12|ASN\.1|BER|DER|MAC could not|too few bytes/i.test(mensaje)) {
            return 'No se pudo abrir el certificado. Verifica el archivo y su contraseña.';
        }
        return mensaje;
    },
});
