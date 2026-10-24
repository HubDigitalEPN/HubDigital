(() => {
    if (window.hubInteractiveGridInstalled) return;
    window.hubInteractiveGridInstalled = true;
    window.hubInteractiveGrid = (columns = [], server = false, label = 'Resultados', client = false) => ({
        columns, server, label, client, hidden: [], compact: false, menuOpen: false, menuView: 'main',
        searchText: '', clientPage: 1, clientPerPage: 15, clientTotal: 0, originalOrder: null, nextOrder: 0,
        columnSearch: '', draftHidden: [], sortColumns: [], sortField: '', sortDirection: 'asc', filterText: '', filterValues: {},
        init() {
            if (!this.columns.length) this.columns = [...this.$el.querySelectorAll('thead tr:first-child th')].map(cell => cell.dataset.gridLabel || cell.textContent.trim() || 'Acciones');
            this.sortColumns = [...this.$el.querySelectorAll('th[data-grid-field]')].map(cell => ({field: cell.dataset.gridField, label: cell.textContent.trim()}));
            if (client) {
                this.sortColumns = this.columns.map((label, index) => ({field: String(index), label}));
                this.originalOrder = new WeakMap();
                this.$watch('clientPerPage', () => { this.clientPage = 1; this.renderClientRows(); });
                this.renderClientRows();
            }
            this.$watch('hidden', () => this.$nextTick(() => this.applyColumns()));
            // Livewire puede sustituir las celdas al paginar o buscar.
            this.observer = new MutationObserver(() => { this.applyColumns(); if (client) this.renderClientRows(); });
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
            if (this.menuOpen) return this.closeMenu();
            this.menuView = 'main'; this.menuOpen = true;
            this.$nextTick(() => {
                const menu = this.$refs.actionMenu;
                if (menu.showPopover) {
                    menu.popover = 'manual'; menu.showPopover();
                    this.positionMenu();
                }
                // x-show termina de revelar el panel en el siguiente frame.
                // Enfocar antes deja el foco en body, especialmente en móvil.
                requestAnimationFrame(() => { if (this.menuOpen) { this.positionMenu(); menu.querySelector('button:not([disabled])')?.focus(); } });
            });
        },
        closeMenu(focus = true) { const menu = this.$refs.actionMenu; if (menu?.popover && menu.matches(':popover-open')) menu.hidePopover(); this.menuOpen = false; if (focus) this.$refs.actionsButton.focus(); },
        positionMenu() {
            const menu = this.$refs.actionMenu;if (!menu?.popover) return;
            const r = this.$refs.actionsButton.getBoundingClientRect();
            menu.style.left = Math.max(8, Math.min(r.left, innerWidth-menu.offsetWidth-8))+'px';
            menu.style.top = Math.max(8, Math.min(r.bottom+4, innerHeight-menu.offsetHeight-8))+'px';
        },
        changeMenu(view) {
            this.menuView = view;
            this.$nextTick(() => requestAnimationFrame(() => {if (this.menuOpen) { this.positionMenu(); this.$refs.actionMenu.querySelector('[data-menu-view="' + view + '"] button')?.focus(); }}));
        },
        menuKey(event) {
            if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); this.closeMenu(); return; }
            if (event.key === 'Tab') { this.closeMenu(false); return; }
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
            this.sortField = (server ? this.$wire.gridSort : this.sortField) || this.sortColumns[0]?.field || '';
            if (server) this.sortDirection = this.$wire.gridDirection;
            this.closeMenu(false); this.$refs.sortDialog.showModal();
        },
        async applySort() { if (server) await this.$wire.aplicarOrdenGrilla(this.sortField, this.sortDirection); else { this.clientPage = 1; this.renderClientRows(); } this.$refs.sortDialog.close(); },
        openFilters() {
            this.filterText = server ? this.$wire.busqueda : this.searchText;
            if (server) this.$refs.filterDialog.querySelectorAll('[data-grid-filter]').forEach(input => {
                this.filterValues[input.dataset.gridFilter] = this.$wire[input.dataset.gridFilter];
            });
            this.closeMenu(false); this.$refs.filterDialog.showModal();
        },
        async applyFilters() {
            if (client) { this.searchText = this.filterText; this.searchPage(); this.$refs.filterDialog.close(); return; }
            this.$wire.$set('busqueda', this.filterText, false);
            Object.entries(this.filterValues).forEach(([field, value]) => this.$wire.$set(field, value, false));
            await this.$wire.buscarGrilla(); this.$refs.filterDialog.close();
        },
        reset() { this.hidden = []; this.compact = false; if (client) { this.searchText = ''; this.sortField = ''; this.clientPerPage = 15; this.clientPage = 1; this.renderClientRows(); } this.closeMenu(false); },
        searchPage() { this.clientPage = 1; this.renderClientRows(); },
        get clientLastPage() { return Math.max(1, Math.ceil(this.clientTotal / this.clientPerPage)); },
        get clientPageNumbers() { return [...new Set([1, ...Array.from({length: 5}, (_, i) => this.clientPage - 2 + i), this.clientLastPage])].filter(n => n > 0 && n <= this.clientLastPage).sort((a,b) => a-b); },
        clientGo(page) { this.clientPage = Math.max(1, Math.min(this.clientLastPage, page)); this.renderClientRows(); },
        renderClientRows() {
            const body = this.$el.querySelector('table tbody'); if (!body) return;
            const rows = [...body.rows].filter(row => !row.querySelector('td[colspan]'));
            for (const row of rows) if (!this.originalOrder.has(row)) this.originalOrder.set(row, this.nextOrder++);
            const collator = new Intl.Collator('es', {numeric: true, sensitivity: 'base'});
            const sorted = [...rows].sort((a,b) => this.sortField === '' ? this.originalOrder.get(a)-this.originalOrder.get(b)
                : collator.compare(a.cells[Number(this.sortField)]?.innerText || '', b.cells[Number(this.sortField)]?.innerText || '') * (this.sortDirection === 'desc' ? -1 : 1));
            if (sorted.some((row, i) => row !== rows[i])) for (const row of sorted) body.append(row);
            const normalizar = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
            const text = normalizar(this.searchText.trim());
            const matches = sorted.filter(row => !text || normalizar(row.textContent).includes(text));
            this.clientTotal = matches.length; this.clientPage = Math.min(this.clientPage, this.clientLastPage);
            const visible = new Set(matches.slice((this.clientPage-1)*this.clientPerPage, this.clientPage*this.clientPerPage));
            for (const row of rows) row.hidden = !visible.has(row);
        },
        downloadPage() {
            if (location.pathname.startsWith('/portal/') && document.querySelector('meta[name=catalogo-descargas]')?.content !== 'true') return;
            const escape = value => '"' + (/^[\s]*[=+@-]/.test(value) ? "'" + value : value).replaceAll('"', '""') + '"';
            const rows = [...this.$el.querySelectorAll('table tr')].filter(row => !row.hidden).map(row => [...row.cells].filter(cell => !cell.hidden)
                .map(cell => escape((cell.dataset.gridLabel || cell.innerText).trim().replace(/\s*\n\s*/g, ' · '))).join(','));
            const url = URL.createObjectURL(new Blob(['\uFEFF' + rows.join('\r\n')], {type: 'text/csv;charset=utf-8;'}));
            const link = document.createElement('a'); link.href = url;
            link.download = this.label.toLowerCase().replace(/[^\p{L}\p{N}]+/gu, '-') + '-pagina.csv';
            link.click(); setTimeout(() => URL.revokeObjectURL(url), 1000); this.closeMenu();
        },
        destroy() { this.menuOpen = false; this.observer?.disconnect(); },
    });
})();
