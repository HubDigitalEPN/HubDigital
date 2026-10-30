(() => {
    if (window.hubChatShell) return;
    window.hubChatShell = () => ({
        abierto: false, minimizado: false,
        init() { try {this.minimizado = localStorage.getItem('hubdigital.chat.oculto') === '1';} catch {} this.actualizarVisibilidad(); },
        actualizarVisibilidad() { document.documentElement.toggleAttribute('data-chat-hidden', this.minimizado); },
        guardarVisibilidad() { try {localStorage.setItem('hubdigital.chat.oculto', this.minimizado ? '1' : '0');} catch {} this.actualizarVisibilidad(); },
        ocultar() { this.abierto = false; this.minimizado = true; this.guardarVisibilidad(); this.$nextTick(() => (document.querySelector('[data-chat-restore-catalogo]') || document.querySelector('[data-chat-restore]'))?.focus({preventScroll:true})); },
        alternar() { this.minimizado = false; this.guardarVisibilidad(); this.abierto = !this.abierto; this.$nextTick(() => this.abierto ? this.$refs.input.focus() : this.devolverFoco()); },
        devolverFoco() { if (this.$refs.trigger.offsetParent) {this.$refs.trigger.focus(); return;} document.getElementById('portal-chat-mobile-trigger')?.focus(); },
        seleccionCatalogo() { const catalogo = document.querySelector('[data-catalogo-seleccion]'); return catalogo ? JSON.parse(catalogo.dataset.catalogoSeleccion) : null; },
    });
})();
