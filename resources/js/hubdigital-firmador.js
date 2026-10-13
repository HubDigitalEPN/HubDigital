/** La interfaz entrega las credenciales temporales al firmador Java del servidor. */
window.hubDigitalFirmador = (config) => ({
    dialogo: false,
    estado: 'listo',
    progreso: '',
    error: '',
    metodo: 'archivo',
    tokenAlias: '',
    tokenCertificados: [],
    peticionId: null,
    pdfPreparando: false,

    abrirDialogo() {
        this.estado = 'listo';
        this.error = '';
        this.dialogo = true;
        this.$nextTick(() => this.$refs.certificado?.focus());
        if (!this.pdfPreparando) void this.prepararPdf();
    },

    async prepararPdf() {
        if (!config.originalUrl || config.documentVersion == null) return;
        this.pdfPreparando = true;
        const controller = new AbortController();
        const timer = window.setTimeout(() => controller.abort(), 12000);
        try {
            const response = await fetch(config.originalUrl, {credentials: 'same-origin', signal: controller.signal,
                headers: {Accept: 'application/pdf'}});
            if (response.ok) await response.arrayBuffer();
        } catch { /* la firma conserva la generación y comprobación completas */ }
        finally { window.clearTimeout(timer); this.pdfPreparando = false; }
    },

    cerrarDialogo() {
        if (this.estado === 'procesando') return;
        this.dialogo = false;
        this.limpiarCamposCredenciales();
        this.$nextTick(() => this.$refs.abrirFirma?.focus());
    },

    async firmar() {
        if (this.estado === 'procesando' || this.pdfPreparando) return;
        this.error = '';
        const archivo = this.$refs.certificado?.files?.[0];
        let clave = this.$refs.clave?.value ?? '';
        if (this.metodo === 'archivo' && (!archivo || !/\.(p12|pfx)$/i.test(archivo.name))) {
            this.error = 'Selecciona un certificado .p12 o .pfx.';
            this.limpiarCamposCredenciales();
            return;
        }
        if (clave.length === 0) {
            this.error = this.metodo === 'token' ? 'Ingresa el PIN del token.' : 'Ingresa la contraseña del certificado.';
            this.limpiarCamposCredenciales();
            return;
        }

        this.estado = 'procesando';
        this.progreso = 'Firmando…';
        this.peticionId ??= crypto.randomUUID();
        const formulario = new FormData();
        const controlador = new AbortController();
        const temporizador = window.setTimeout(() => controlador.abort(), 90000);
        try {
            if (this.metodo === 'token') {
                const firmado = await this.firmarConToken(clave, controlador.signal);
                formulario.append('pdf_firmado', firmado, 'solicitud-token-firmada.pdf');
            } else {
                formulario.append('certificado', archivo);
                formulario.append('clave_certificado', clave);
            }
            if (config.documentVersion != null) formulario.append('documento_version', String(config.documentVersion));
            formulario.append('firma_peticion_id', this.peticionId);
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
                const detalle = Object.values(cuerpo.errors ?? {}).flat().find(Boolean);
                throw new Error(detalle ?? cuerpo.message ?? 'No se pudo firmar y validar el documento.');
            }
            await this.completarFirma(cuerpo.message);
        } catch (error) {
            if ((error?.name === 'AbortError' || error instanceof TypeError) && await this.recuperarFirma()) return;
            if (error?.name !== 'AbortError' && !(error instanceof TypeError)) this.peticionId = null;
            this.estado = 'error';
            this.progreso = '';
            this.error = error?.name === 'AbortError'
                ? 'No se pudo confirmar la firma por una interrupción de conexión. Vuelve a intentarlo; comprobaremos si ya se guardó.'
                : this.mensajeSeguro(error);
        } finally {
            window.clearTimeout(temporizador);
            formulario.delete('clave_certificado');
            formulario.delete('certificado');
            clave = '';
            this.limpiarCamposCredenciales();
        }
    },

    async completarFirma(mensaje) {
        this.estado = 'completado';
        this.progreso = mensaje ?? 'Solicitud firmada y validada criptográficamente.';
        this.dialogo = false;
        window.dispatchEvent(new CustomEvent('toast', {detail: {message: this.progreso}}));
        if (config.onSigned) {
            try {
                await config.onSigned();
                this.$nextTick(() => document.querySelector('[data-oficio-signed-link]')?.focus());
            } catch { window.location.reload(); }
        } else window.location.reload();
    },

    async recuperarFirma() {
        if (!config.statusUrl) return false;
        try {
            const url = new URL(config.statusUrl, window.location.href);
            url.searchParams.set('version', config.documentVersion);
            const respuesta = await fetch(url, {credentials: 'same-origin', headers: {Accept: 'application/json'}, signal: AbortSignal.timeout(10000)});
            if (!respuesta.ok || !(await respuesta.json()).firmada) return false;
            await this.completarFirma();
            return true;
        } catch { return false; }
    },

    async firmarConToken(pin, signal) {
        const puente = config.tokenUrl ?? 'http://127.0.0.1:17654';
        const cabeceras = {'X-HubDigital-Bridge': '1'};
        let sesion;
        try {
            const estado = await fetch(puente + '/session', {headers: cabeceras, signal});
            if (!estado.ok) throw new Error('Conector local no disponible.');
            sesion = await estado.json();
        } catch (error) {
            if (error.name === 'AbortError') throw error;
            throw new Error('Abre el conector HubDigital para token USB y permite el acceso a la red local cuando el navegador lo solicite.');
        }
        const headers = {...cabeceras, 'X-Bridge-Session': sesion.token, 'X-Token-Pin': pin};
        const listado = await fetch(puente + '/certificates', {method: 'POST', headers, signal});
        const identidades = await listado.json();
        if (!listado.ok) throw new Error(identidades.message);
        this.tokenCertificados = identidades.certificados;
        if (!this.tokenAlias && this.tokenCertificados.length === 1) this.tokenAlias = this.tokenCertificados[0].alias;
        if (!this.tokenCertificados.some(cert => cert.alias === this.tokenAlias)) {
            throw new Error('Selecciona el certificado del token y vuelve a introducir el PIN para firmar.');
        }
        const original = await fetch(config.originalUrl, {credentials: 'same-origin', signal, headers: {Accept: 'application/pdf'}});
        if (!original.ok || !original.headers.get('content-type')?.includes('application/pdf')) throw new Error('No se pudo preparar el PDF para el token.');
        headers['X-Token-Alias'] = encodeURIComponent(this.tokenAlias);
        headers['Content-Type'] = 'application/pdf';
        const respuesta = await fetch(puente + '/sign', {method: 'POST', headers, body: await original.blob(), signal});
        if (!respuesta.ok) throw new Error((await respuesta.json()).message);
        return respuesta.blob();
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
