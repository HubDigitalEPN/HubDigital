(() => {
    if (window.hubInteractiveGridInstalled) return;
    window.hubInteractiveGridInstalled = true;
    window.hubInteractiveGrid = (columns = [], server = false, label = 'Resultados', client = false) => ({
        columns, server, label, client, hidden: [], compact: false, menuOpen: false, menuView: 'main',
        searchText: '', clientPage: 1, clientPerPage: 15, clientTotal: 0, originalOrder: null, nextOrder: 0,
        columnSearch: '', draftHidden: [], sortColumns: [], sortField: '', sortDirection: 'asc', filterText: '', filterValues: {},
        columnOrder: [], columnFilters: {}, activeColumn: 0, columnTerm: '', columnOperator: 'contains', valueSearch: '', draggingColumn: null,
        pageSort: '', pageDirection: 'asc', pageMatches: 0, headerTrigger: null,
        init() {
            this.root = this.$el;
            if (!this.columns.length) this.columns = [...this.root.querySelectorAll('thead tr:first-child th')].map(cell => cell.dataset.gridLabel || cell.textContent.trim() || 'Acciones');
            this.sortColumns = [...this.root.querySelectorAll('th[data-grid-field]')].map(cell => ({field: cell.dataset.gridField, label: cell.textContent.trim()}));
            this.columnOrder = this.columns.map((_, index) => index);
            this.installHeaders();
            if (client) {
                this.sortColumns = this.columns.map((label, index) => ({field: String(index), label}));
                this.originalOrder = new WeakMap();
                this.$watch('clientPerPage', () => { this.clientPage = 1; this.renderClientRows(); });
                this.renderClientRows();
            }
            this.$watch('hidden', () => this.$nextTick(() => this.applyColumns()));
            // Livewire puede sustituir las celdas al paginar o buscar.
            this.observer = new MutationObserver(() => { this.installHeaders(); this.applyColumns(); if (client) this.renderClientRows(); else this.renderPageRows(); });
            this.observer.observe(this.root.querySelector('table'), {childList: true, subtree: true});
            this.applyColumns();
        },
        headers() { return [...this.root.querySelectorAll('thead tr:first-child th')]; },
        installHeaders() {
            const headers = this.headers();
            // El orden es visual (CSS Grid): Livewire conserva su DOM canónico y sus acciones.
            if (!headers.length || !this.root.querySelector('table')?.classList) return;
            const labels = headers.map(cell => cell.dataset.gridLabel || cell.textContent.trim() || 'Selección');
            if (JSON.stringify(labels) !== JSON.stringify(this.columns)) {
                const previous = [...this.columns];
                this.columnOrder = [...this.columnOrder.map(index => labels.indexOf(previous[index])).filter(index => index >= 0),
                    ...labels.map((_, index) => index).filter(index => !previous.includes(labels[index]))];
                this.hidden = this.hidden.map(index => labels.indexOf(previous[index])).filter(index => index >= 0);
                this.columnFilters = {}; this.columns = labels;
            }
            this.root.querySelector('table').classList.add('hub-ig-movable');
            headers.forEach((cell, index) => {
                cell.draggable = true; cell.tabIndex = 0; cell.setAttribute('aria-haspopup', 'dialog');
                cell.title = 'Buscar y ordenar · arrastra para mover · Alt + flechas para mover con teclado';
                if (cell._hubGridHeader) return;
                cell._hubGridHeader = true;
                cell.addEventListener('click', event => {
                    if (event.target.closest('input, select, textarea, a')) return;
                    event.preventDefault(); event.stopImmediatePropagation(); this.openColumn(index, cell);
                }, true);
                cell.addEventListener('keydown', event => {
                    if (event.target.closest('input, select, textarea')) return;
                    if (event.altKey && ['ArrowLeft', 'ArrowRight'].includes(event.key)) {
                        event.preventDefault(); event.stopImmediatePropagation(); this.moveColumn(index, event.key === 'ArrowLeft' ? -1 : 1); cell.focus();
                    } else if (['Enter', ' '].includes(event.key)) { event.preventDefault(); event.stopImmediatePropagation(); this.openColumn(index, cell); }
                }, true);
                cell.addEventListener('dragstart', event => { this.draggingColumn = index; event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', String(index)); });
                cell.addEventListener('dragover', event => { if (this.draggingColumn !== null) {event.preventDefault(); event.dataTransfer.dropEffect = 'move';} });
                cell.addEventListener('drop', event => { event.preventDefault(); if (this.draggingColumn === null) return; this.placeColumn(this.draggingColumn, index); this.draggingColumn = null; });
                cell.addEventListener('dragend', () => { this.draggingColumn = null; });
            });
        },
        placeColumn(from, to) {
            const next = [...this.columnOrder], start = next.indexOf(from), end = next.indexOf(to);
            if (start < 0 || end < 0 || start === end) return;
            next.splice(start, 1); next.splice(end, 0, from); this.columnOrder = next; this.applyColumns();
        },
        moveColumn(index, delta) { const position = this.columnOrder.indexOf(index), target = this.columnOrder[position + delta]; if (target !== undefined) this.placeColumn(index, target); },
        openColumn(index, trigger) {
            this.activeColumn = index; this.headerTrigger = trigger;
            const current = this.columnFilters[index]; this.columnTerm = current?.value || ''; this.columnOperator = current?.operator || 'contains'; this.valueSearch = '';
            this.closeMenu(false); const dialog = this.$refs.columnDialog; if (!dialog) return;
            dialog.showModal();
            this.$nextTick(() => { const box = trigger.getBoundingClientRect(); dialog.style.left = Math.max(8, Math.min(box.left, innerWidth - dialog.offsetWidth - 8))+'px'; dialog.style.top = Math.max(8, Math.min(box.bottom+4, innerHeight-dialog.offsetHeight-8))+'px'; dialog.querySelector('input')?.focus(); });
        },
        get columnField() { return this.headers()[this.activeColumn]?.dataset.gridField || ''; },
        get columnScope() { return this.server && this.columnField ? 'Todas las filas de la consulta' : this.client ? 'Todas las filas de la tabla' : 'Filas de esta página; los filtros generales conservan toda la consulta'; },
        get columnValues() {
            const needle = this.normalize(this.valueSearch);
            return [...new Set(this.dataRows().map(row => this.cellText(row, this.activeColumn)))].filter(value => value && (!needle || this.normalize(value).includes(needle))).sort(new Intl.Collator('es', {numeric:true}).compare).slice(0, 100);
        },
        normalize(value) { return String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase(); },
        cellText(row, index) {
            const cell = row.cells[index]; if (!cell) return '';
            if (cell.dataset?.gridValue !== undefined) return cell.dataset.gridValue;
            const controls = [...(cell.querySelectorAll?.('input:not([type=checkbox]):not([type=radio]), select, textarea') || [])];
            return controls.length ? controls.map(input => input.tagName === 'SELECT' ? input.selectedOptions[0]?.textContent || input.value : input.value).join(' ') : cell.innerText ?? cell.textContent ?? '';
        },
        dataRows() { return [...(this.root.querySelector('table tbody')?.rows || [])].filter(row => !row.querySelector('td[colspan]')); },
        matchesColumn(value, filter) {
            const actual = this.normalize(value.trim()), expected = this.normalize(filter.value.trim());
            if (filter.operator === 'empty') return actual === '' || actual === '—';
            if (filter.operator === 'equals') return actual === expected;
            if (filter.operator === 'starts') return actual.startsWith(expected);
            if (filter.operator === 'notContains') return !actual.includes(expected);
            return actual.includes(expected);
        },
        async applyColumn(value = this.columnTerm, operator = this.columnOperator) {
            const index = this.activeColumn;
            if (this.server && this.columnField) {
                await this.$wire.filtrarColumnaGrilla(this.columnField, String(value), operator);
            }
            if (value !== '' || operator === 'empty') this.columnFilters = {...this.columnFilters, [index]: {value, operator}};
            else { const filters = {...this.columnFilters}; delete filters[index]; this.columnFilters = filters; }
            if (this.client) {this.clientPage = 1; this.renderClientRows();} else this.renderPageRows();
            this.closeColumn();
        },
        async sortColumn(direction) {
            if (this.server && this.columnField) await this.$wire.aplicarOrdenGrilla(this.columnField, direction);
            else if (this.client) {this.sortField = String(this.activeColumn); this.sortDirection = direction; this.clientPage = 1; this.renderClientRows();}
            else {this.pageSort = String(this.activeColumn); this.pageDirection = direction; this.renderPageRows();}
            this.closeColumn();
        },
        closeColumn() { this.$refs.columnDialog?.close(); this.headerTrigger?.focus(); },
        hideColumn() { this.hidden = [...new Set([...this.hidden, this.activeColumn])]; this.applyColumns(); this.closeColumn(); },
        renderPageRows() {
            const rows = this.dataRows(), collator = new Intl.Collator('es', {numeric:true, sensitivity:'base'});
            const sorted = this.pageSort === '' ? rows : [...rows].sort((a,b) => collator.compare(this.cellText(a, Number(this.pageSort)), this.cellText(b, Number(this.pageSort))) * (this.pageDirection === 'desc' ? -1 : 1));
            sorted.forEach((row, index) => {
                if (row.style) row.style.order = index;
                row.hidden = Object.entries(this.columnFilters).some(([column, filter]) => !(this.server && this.headers()[column]?.dataset.gridField) && !this.matchesColumn(this.cellText(row, Number(column)), filter));
            }); this.pageMatches = rows.filter(row => !row.hidden).length;
        },
        toggleDraft(index) {
            this.draftHidden = this.draftHidden.includes(index) ? this.draftHidden.filter(value => value !== index) : [...this.draftHidden, index];
        },
        applyColumns() {
            const visible = this.columnOrder.filter(index => !this.hidden.includes(index));
            this.root.style?.setProperty('--ig-visible-columns', Math.max(1, visible.length));
            this.root.querySelectorAll('table tr').forEach(row => [...row.children].forEach((cell, index) => {
                if (cell.colSpan > 1) return;
                cell.hidden = this.hidden.includes(index);
                if (cell.style) cell.style.gridColumn = String(visible.indexOf(index) + 1);
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
        reset() { this.hidden = []; this.compact = false; this.columnOrder = this.columns.map((_, index) => index); this.columnFilters = {}; this.pageSort = ''; this.applyColumns(); if (client) { this.searchText = ''; this.sortField = ''; this.clientPerPage = 15; this.clientPage = 1; this.renderClientRows(); } else this.renderPageRows(); this.closeMenu(false); },
        searchPage() { this.clientPage = 1; this.renderClientRows(); },
        get clientLastPage() { return Math.max(1, Math.ceil(this.clientTotal / this.clientPerPage)); },
        get clientPageNumbers() { return [...new Set([1, ...Array.from({length: 5}, (_, i) => this.clientPage - 2 + i), this.clientLastPage])].filter(n => n > 0 && n <= this.clientLastPage).sort((a,b) => a-b); },
        clientGo(page) { this.clientPage = Math.max(1, Math.min(this.clientLastPage, page)); this.renderClientRows(); },
        renderClientRows() {
            const body = this.root.querySelector('table tbody'); if (!body) return;
            const rows = [...body.rows].filter(row => !row.querySelector('td[colspan]'));
            for (const row of rows) if (!this.originalOrder.has(row)) this.originalOrder.set(row, this.nextOrder++);
            const collator = new Intl.Collator('es', {numeric: true, sensitivity: 'base'});
            const sorted = [...rows].sort((a,b) => this.sortField === '' ? this.originalOrder.get(a)-this.originalOrder.get(b)
                : collator.compare(a.cells[Number(this.sortField)]?.innerText || '', b.cells[Number(this.sortField)]?.innerText || '') * (this.sortDirection === 'desc' ? -1 : 1));
            if (rows[0]?.style) sorted.forEach((row, index) => { row.style.order = index; });
            else if (sorted.some((row, i) => row !== rows[i])) for (const row of sorted) body.append(row);
            const normalizar = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
            const text = normalizar(this.searchText.trim());
            const matches = sorted.filter(row => (!text || normalizar(row.textContent).includes(text)) && Object.entries(this.columnFilters).every(([column, filter]) => this.matchesColumn(this.cellText(row, Number(column)), filter)));
            this.clientTotal = matches.length; this.clientPage = Math.min(this.clientPage, this.clientLastPage);
            const visible = new Set(matches.slice((this.clientPage-1)*this.clientPerPage, this.clientPage*this.clientPerPage));
            for (const row of rows) row.hidden = !visible.has(row);
        },
        downloadPage() {
            if (location.pathname.startsWith('/portal/') && document.querySelector('meta[name=catalogo-descargas]')?.content !== 'true') return;
            const escape = value => '"' + (/^[\s]*[=+@-]/.test(value) ? "'" + value : value).replaceAll('"', '""') + '"';
            const tableRows = [...this.root.querySelectorAll('table tr')].filter(row => !row.hidden);
            const header = tableRows.filter(row => row.parentElement?.tagName === 'THEAD');
            const body = tableRows.filter(row => row.parentElement?.tagName !== 'THEAD').sort((a,b) => Number(a.style?.order || 0) - Number(b.style?.order || 0));
            const rows = [...header, ...body].map(row => (this.columnOrder.length && row.cells.length === this.columns.length ? this.columnOrder.map(index => row.cells[index]) : [...row.cells]).filter(cell => !cell.hidden)
                .map(cell => escape((cell.dataset.gridLabel || cell.innerText).trim().replace(/\s*\n\s*/g, ' · '))).join(','));
            const url = URL.createObjectURL(new Blob(['\uFEFF' + rows.join('\r\n')], {type: 'text/csv;charset=utf-8;'}));
            const link = document.createElement('a'); link.href = url;
            link.download = this.label.toLowerCase().replace(/[^\p{L}\p{N}]+/gu, '-') + '-pagina.csv';
            link.click(); setTimeout(() => URL.revokeObjectURL(url), 1000); this.closeMenu();
        },
        destroy() { this.menuOpen = false; this.observer?.disconnect(); },
    });
})();
