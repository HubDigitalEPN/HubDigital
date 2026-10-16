(() => {
    if (window.hubInteractiveGridInstalled) return;
    window.hubInteractiveGridInstalled = true;
    window.hubInteractiveGrid = (columns = [], server = false, label = 'Resultados') => ({
        columns, server, label, hidden: [], compact: false, menuOpen: false, menuView: 'main',
        columnSearch: '', draftHidden: [], sortColumns: [], sortField: '', sortDirection: 'asc', filterText: '', filterValues: {},
        init() {
            if (!this.columns.length) this.columns = [...this.$el.querySelectorAll('thead tr:first-child th')].map(cell => cell.textContent.trim() || 'Acciones');
            this.sortColumns = [...this.$el.querySelectorAll('th[data-grid-field]')].map(cell => ({field: cell.dataset.gridField, label: cell.textContent.trim()}));
            this.$watch('hidden', () => this.$nextTick(() => this.applyColumns()));
            // Livewire puede sustituir las celdas al paginar o buscar.
            this.observer = new MutationObserver(() => this.applyColumns());
            this.observer.observe(this.$el.querySelector('table'), {childList: true, subtree: true});
        },
        toggleDraft(index) {
            this.draftHidden = this.draftHidden.includes(index) ? this.draftHidden.filter(value => value !== index) : [...this.draftHidden, index];
        },
        applyColumns() {
            this.$el.querySelectorAll('table tr').forEach(row => [...row.children].forEach((cell, index) => {
                if (cell.colSpan > 1) return;
                cell.hidden = this.hidden.includes(index);
            }));
        },
        openMenu() {
            this.menuView = 'main'; this.menuOpen = !this.menuOpen;
            if (this.menuOpen) this.$nextTick(() => this.$refs.actionMenu.querySelector('button:not([disabled])')?.focus());
        },
        closeMenu(focus = true) { this.menuOpen = false; if (focus) this.$refs.actionsButton.focus(); },
        changeMenu(view) {
            this.menuView = view;
            this.$nextTick(() => this.$refs.actionMenu.querySelector('[data-menu-view="' + view + '"] button')?.focus());
        },
        menuKey(event) {
            if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); this.closeMenu(); return; }
            if (event.key === 'Tab') { this.menuOpen = false; return; }
            const buttons = [...this.$refs.actionMenu.querySelectorAll('button:not([disabled])')].filter(button => button.getClientRects().length);
            const position = buttons.indexOf(document.activeElement);
            const index = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1
                : event.key === 'ArrowDown' ? (position + 1) % buttons.length
                : event.key === 'ArrowUp' ? (position - 1 + buttons.length) % buttons.length : null;
            if (index !== null) { event.preventDefault(); buttons[index]?.focus(); }
        },
        openColumns() {
            this.draftHidden = [...this.hidden]; this.columnSearch = ''; this.closeMenu(false); this.$refs.columnsDialog.showModal();
        },
        applyColumnSelection() { this.hidden = [...this.draftHidden]; this.$refs.columnsDialog.close(); },
        openSort() {
            this.sortField = this.$wire.gridSort || this.sortColumns[0]?.field || '';
            this.sortDirection = this.$wire.gridDirection; this.closeMenu(false); this.$refs.sortDialog.showModal();
        },
        async applySort() { await this.$wire.aplicarOrdenGrilla(this.sortField, this.sortDirection); this.$refs.sortDialog.close(); },
        openFilters() {
            this.filterText = this.$wire.busqueda;
            this.$refs.filterDialog.querySelectorAll('[data-grid-filter]').forEach(input => {
                this.filterValues[input.dataset.gridFilter] = this.$wire[input.dataset.gridFilter];
            });
            this.closeMenu(false); this.$refs.filterDialog.showModal();
        },
        async applyFilters() {
            this.$wire.$set('busqueda', this.filterText, false);
            Object.entries(this.filterValues).forEach(([field, value]) => this.$wire.$set(field, value, false));
            await this.$wire.buscarGrilla(); this.$refs.filterDialog.close();
        },
        reset() { this.hidden = []; this.compact = false; this.closeMenu(false); },
        downloadPage() {
            const escape = value => '"' + (/^[\s]*[=+@-]/.test(value) ? "'" + value : value).replaceAll('"', '""') + '"';
            const rows = [...this.$el.querySelectorAll('table tr')].map(row => [...row.cells].filter(cell => !cell.hidden)
                .map(cell => escape(cell.innerText.trim().replace(/\s*\n\s*/g, ' · '))).join(','));
            const url = URL.createObjectURL(new Blob(['\uFEFF' + rows.join('\r\n')], {type: 'text/csv;charset=utf-8;'}));
            const link = document.createElement('a'); link.href = url;
            link.download = this.label.toLowerCase().replace(/[^\p{L}\p{N}]+/gu, '-') + '-pagina.csv';
            link.click(); setTimeout(() => URL.revokeObjectURL(url), 1000); this.closeMenu();
        },
        destroy() { this.observer?.disconnect(); },
    });
})();
