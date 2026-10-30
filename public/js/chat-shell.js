(() => {
    if (window.hubChatShell) return;
    window.hubChatShell = ({puedeOcultar = false} = {}) => ({
        abierto: false, minimizado: false, puedeOcultar,
        // Ocultar afecta esta visita al catálogo. Una apertura nueva siempre
        // ofrece el lanzador, aunque una versión anterior guardara lo contrario.
        init() { try {localStorage.removeItem('hubdigital.chat.oculto');} catch {} this.actualizarVisibilidad(); },
        actualizarVisibilidad() { document.documentElement.toggleAttribute('data-chat-hidden', this.minimizado); },
        ocultar() { if (!this.puedeOcultar) return; this.abierto = false; this.minimizado = true; this.actualizarVisibilidad(); this.$nextTick(() => (document.querySelector('[data-chat-restore-catalogo]') || document.querySelector('[data-chat-restore]'))?.focus({preventScroll:true})); },
        alternar() { this.minimizado = false; this.actualizarVisibilidad(); this.abierto = !this.abierto; this.$nextTick(() => this.abierto ? this.$refs.input.focus() : this.devolverFoco()); },
        devolverFoco() { if (this.$refs.trigger.offsetParent) {this.$refs.trigger.focus(); return;} document.getElementById('portal-chat-mobile-trigger')?.focus(); },
        seleccionCatalogo() { const catalogo = document.querySelector('[data-catalogo-seleccion]'); return catalogo ? JSON.parse(catalogo.dataset.catalogoSeleccion) : null; },
    });
})();
