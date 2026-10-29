(() => {
    if (window.hubCardScroll) return;
    window.hubCardScroll = (selection, pages) => ({
        busy: false, error: false, observer: null, timer: null, active: true,
        init() {
            if (!window.IntersectionObserver) { this.error = true; return; }
            this.observer = new IntersectionObserver(entries => {
                if (entries.some(entry => entry.isIntersecting)) this.load();
            }, {rootMargin: '0px 0px 300px 0px'});
            this.observer.observe(this.$el);
        },
        async load() {
            if (this.busy || !this.active) return;
            this.busy = true; this.error = false; this.observer?.disconnect();
            // Una sola petición por centinela. Un fallo exige reintento del usuario.
            try { await Promise.race([this.$wire.cargarMasTarjetas(selection, pages), new Promise((_, reject) => {this.timer = setTimeout(() => reject(new Error('Tiempo de carga agotado')), 45000);})]); }
            catch { if (this.active) {this.busy = false; this.error = true;} }
            finally { clearTimeout(this.timer); this.timer = null; }
        },
        destroy() { this.active = false; this.observer?.disconnect(); clearTimeout(this.timer); },
    });
})();
