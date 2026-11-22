(() => {
    if (window.hubInteractiveGridInstalled) return;
    window.hubInteractiveGridInstalled = true;
    window.hubInteractiveGrid = (columns = [], server = false, label = 'Resultados', client = false) => ({
        columns, server, label, client, hidden: [], compact: false, menuOpen: false, menuView: 'main',
        searchText: '', clientPage: 1, clientPerPage: 15, clientTotal: 0, originalOrder: null, nextOrder: 0,
        columnSearch: '', draftHidden: [], sortColumns: [], sortField: '', sortDirection: 'asc', filterText: '', filterValues: {},
        columnOrder: [], columnFilters: {}, activeColumn: 0, columnTerm: '', columnOperator: 'contains', valueSearch: '', draggingColumn: null,
        anchosColumnas: {}, arrastreAncho: null,
        pageSort: '', pageDirection: 'asc', pageMatches: 0, headerTrigger: null,
        clientFirstPage: 1, clientLastLoaded: 1, clientWindowToken: '', clientAnchor: 1,
        cargandoContinuidad: false, errorContinuidad: '', ultimaDireccion: 1,
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
            this.observer = new MutationObserver(() => {
                this.installHeaders();
                this.applyColumns();
                if (client) this.renderClientRows(); else this.renderPageRows();
                if (this.root.hasAttribute('data-grid-scroll-only')) this.actualizarConsultaContinua();
            });
            this.observer.observe(this.root.querySelector('table'), {childList: true, subtree: true});
            this.applyColumns();
            this.$nextTick?.(() => this.instalarScroll());
        },
        datosVentana() {
            let pager=this.root.querySelector('[data-grid-window]');
            if(!pager){
                const component=this.root.closest?.('[wire\\:id]');
                const opciones=[...(component?.querySelectorAll('[data-grid-window]')||[])].filter(el=>el.getClientRects().length && (!el.closest('[data-hub-grid]') || el.closest('[data-hub-grid]')===this.root));
                if(opciones.length===1)pager=opciones[0];
            }
            try{return pager?JSON.parse(pager.dataset.gridWindow):null;}catch{return null;}
        },
        instalarScroll() {
            this.superficie=this.root.querySelector('.atlas-record-table-scroll')||this.root.querySelector('.hub-ig-scroll');
            if(!this.superficie?.addEventListener)return;
            this.alTecla = event => {
                if (event.ctrlKey || event.altKey || event.metaKey
                    || event.target.closest?.('input,textarea,select,button,[contenteditable="true"]')) return;
                if (['PageDown', 'ArrowDown', 'End'].includes(event.key)) this.intencionScroll = 1;
                else if (['PageUp', 'ArrowUp', 'Home'].includes(event.key)) this.intencionScroll = -1;
                else return;
                if (this.enBorde(this.intencionScroll)) {
                    event.preventDefault();
                    this.alScroll?.();
                }
            };
            this.superficie.addEventListener('keydown', this.alTecla);
            // El catálogo público mide su propio contenedor y conserva su cursor de carga.
            if (this.root.closest?.('[data-hay-mas]')) return;
            const envoltura=this.root.querySelector('.hub-ig-scroll');
            if(envoltura!==this.superficie)envoltura.style.overflow='visible';
            const component=this.root.closest?.('[wire\\:id]');
            const paginador=component?.querySelector('.hub-ig-footer [wire\\:click], nav[role="navigation"] [wire\\:click]');
            if(!client && !server && !this.datosVentana() && !paginador){
                client=this.client=true;this.originalOrder=new WeakMap();
                this.sortColumns=this.columns.map((label,index)=>({field:String(index),label}));
                this.$watch('clientPerPage',()=>{this.clientGo(1);});this.renderClientRows();
            }
            this.alRueda=event=>{
                if(event.ctrlKey || event.target.closest?.('dialog,textarea,select,[contenteditable="true"]'))return;
                const direccion=Math.sign(event.deltaY);
                if(direccion && this.enBorde(direccion) && this.puedeContinuar(direccion)){
                    if(event.cancelable)event.preventDefault();this.cargarContinuidad(direccion);
                }
            };
            const soloScroll = this.root.hasAttribute('data-grid-scroll-only');
            if (soloScroll) this.actualizarConsultaContinua();
            this.alScroll = () => {
                const top = this.superficie.scrollTop;
                const direccion = this.intencionScroll || (soloScroll ? Math.sign(top - this.posicionScroll) : 0);
                this.posicionScroll = top;
                if (direccion && this.enBorde(direccion)) {
                    this.intencionScroll = 0;
                    if (this.puedeContinuar(direccion)) this.cargarContinuidad(direccion);
                }
            };
            this.alTocar=event=>{this.inicioTacto=event.touches[0]?.clientY;};
            this.alMoverTacto=event=>{
                this.intencionScroll=Math.sign((this.inicioTacto??0)-(event.touches[0]?.clientY??0));
                this.alScroll();
            };
            this.superficie.addEventListener('wheel',this.alRueda,{passive:false});
            this.superficie.addEventListener('scroll',this.alScroll,{passive:true});
            this.superficie.addEventListener('touchstart',this.alTocar,{passive:true});this.superficie.addEventListener('touchmove',this.alMoverTacto,{passive:true});
            this.alMedir=()=>this.ajustarScroll();
            window.addEventListener('resize', this.alMedir, {passive: true});
            if(typeof ResizeObserver!=='undefined'){this.medidas=new ResizeObserver(this.alMedir);this.medidas.observe(this.root);}
            this.ajustarScroll();
        },
        actualizarConsultaContinua() {
            const consulta = this.datosVentana()?.consulta;
            if (!this.superficie || consulta === this.consultaContinua) return;
            this.consultaContinua = consulta;
            this.intencionScroll = 0;
            this.superficie.scrollTop = this.posicionScroll = 0;
            this.errorContinuidad = '';
        },
        ajustarScroll() {
            if(!this.superficie?.isConnected)return;
            const viewport=window.visualViewport?.height||window.innerHeight;
            const modal=this.root.closest('dialog[open]');
            // Dentro de un diálogo el límite es su borde; en la página se mide
            // desde el documento para que el scroll exterior no agrande la cuadrícula.
            const top = this.superficie.getBoundingClientRect().top + (modal ? 0 : window.scrollY);
            const bottom=modal?Math.min(viewport,modal.getBoundingClientRect().bottom):viewport;
            const minimo = modal ? 100 : Math.max(100, Math.min(viewport / 3, viewport - 120));
            const altura = Math.max(minimo, Math.min(bottom - Math.max(8, top) - 64, viewport - 120));
            const valor=altura+'px';if(this.superficie.style.maxHeight!==valor)this.superficie.style.maxHeight=valor;
        },
        enBorde(direccion) {const s=this.superficie;return !!s && (direccion>0?s.scrollHeight-s.scrollTop-s.clientHeight<=16:s.scrollTop<=1);},
        puedeContinuar(direccion) {
            if(this.cargandoContinuidad || this.errorContinuidad)return false;
            const v=this.datosVentana();
            return v?(direccion>0?v.hasta<v.paginas:v.desde>1):this.client?(direccion>0?this.clientLastLoaded<this.clientLastPage:this.clientFirstPage>1):false;
        },
        async cargarContinuidad(direccion,reintento=false) {
            if(reintento)this.errorContinuidad='';
            if(!this.puedeContinuar(direccion))return;
            this.cargandoContinuidad=true;this.ultimaDireccion=direccion;
            const s=this.superficie,top=s.getBoundingClientRect().top;
            const filas = () => {
                const movil = this.root.querySelector('[data-grid-mobile]');
                return movil?.getClientRects().length ? [...movil.querySelectorAll('[data-grid-scroll-row]')] : this.dataRows();
            };
            const ancla=filas().find(row=>!row.hidden && row.getBoundingClientRect().bottom>top+40);
            const identidad=ancla?.getAttribute('wire:key')||ancla?.textContent;
            const y=ancla?.getBoundingClientRect().top;let timer;
            try{
                const v=this.datosVentana();
                if(v)await Promise.race([this.$wire.cargarGrillaContinua(direccion,v.consulta,direccion>0?v.hasta:v.desde),new Promise((_,reject)=>{timer=setTimeout(()=>reject(Error('Tiempo agotado')),45000);})]);
                else this.cargarClienteContinuo(direccion);
                await this.$nextTick();
                const conservada=filas().find(row=>(row.getAttribute('wire:key')||row.textContent)===identidad);
                if(conservada && y!==undefined)s.scrollTop+=conservada.getBoundingClientRect().top-y;
                this.posicionScroll = s.scrollTop;
                this.ajustarScroll();
            }catch{this.errorContinuidad='No se pudieron cargar más filas. Conservamos las visibles; puedes reintentar.';}
            finally{clearTimeout(timer);this.cargandoContinuidad=false;}
        },
        cargarClienteContinuo(direccion) {
            const maximo=Math.max(1,Math.min(3,Math.floor(600/this.clientPerPage)));
            if(direccion>0 && this.clientLastLoaded<this.clientLastPage){this.clientLastLoaded++;this.clientFirstPage=Math.max(this.clientFirstPage,this.clientLastLoaded-maximo+1);}
            else if(direccion<0 && this.clientFirstPage>1){this.clientFirstPage--;this.clientLastLoaded=Math.min(this.clientLastLoaded,this.clientFirstPage+maximo-1);}
            this.renderClientRows();
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
                if (cell.dataset.gridFixed) return;
                cell.draggable = true; cell.tabIndex = 0; cell.setAttribute('aria-haspopup', 'dialog');
                cell.title = 'Buscar y ordenar · arrastra para mover · Alt + flechas para mover con teclado';
                if (cell._hubGridHeader) return;
                cell._hubGridHeader = true;
                cell.addEventListener('click', event => {
                    if (event.target.closest('input, select, textarea, a, [data-grid-resize], [data-grid-server-order]')) return;
                    event.preventDefault(); event.stopImmediatePropagation(); this.openColumn(index, cell);
                }, true);
                cell.addEventListener('keydown', event => {
                    if (event.target.closest('input, select, textarea, [data-grid-resize], [data-grid-server-order]')) return;
                    if (event.altKey && ['ArrowLeft', 'ArrowRight'].includes(event.key)) {
                        event.preventDefault(); event.stopImmediatePropagation(); this.moveColumn(index, event.key === 'ArrowLeft' ? -1 : 1); cell.focus();
                    } else if (['Enter', ' '].includes(event.key)) { event.preventDefault(); event.stopImmediatePropagation(); this.openColumn(index, cell); }
                }, true);
                cell.addEventListener('dragstart', event => {
                    if (this.arrastreAncho) { event.preventDefault(); return; }
                    this.draggingColumn = index; event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', String(index));
                });
                cell.addEventListener('dragover', event => { if (this.draggingColumn !== null) {event.preventDefault(); event.dataTransfer.dropEffect = 'move';} });
                cell.addEventListener('drop', event => { event.preventDefault(); if (this.draggingColumn === null) return; this.placeColumn(this.draggingColumn, index); this.draggingColumn = null; });
                cell.addEventListener('dragend', () => { this.draggingColumn = null; });
            });
        },
        estilosColumnas() {
            const estilos = {'--ig-visible-columns': this.columns.length - this.hidden.length};
            if (!this.$el.hasAttribute('data-grid-resizable')) return estilos;
            const cabeceras = this.headers();
            const orden = this.columnOrder.length ? this.columnOrder : this.columns.map((_, indice) => indice);
            const medidas = orden.filter(indice => !this.hidden.includes(indice))
                .map(indice => Number(cabeceras[indice]?.dataset.gridFixed) || this.anchosColumnas[cabeceras[indice]?.dataset.gridField]);
            estilos['--ig-column-template'] = medidas.map(ancho => ancho ? ancho + 'px' : 'minmax(144px, 1fr)').join(' ');
            estilos['--ig-table-min-width'] = medidas.reduce((total, ancho) => total + (ancho || 144), 0) + 'px';
            return estilos;
        },
        anchoColumna(campo, control) {
            return this.anchosColumnas[campo] || control.closest('th').getBoundingClientRect().width;
        },
        ajustarAncho(campo, ancho) {
            this.anchosColumnas = {...this.anchosColumnas, [campo]: Math.max(104, Math.min(720, Math.round(ancho)))};
        },
        iniciarAncho(evento, campo) {
            if (evento.button !== 0) return;
            const control = evento.currentTarget;
            control.focus({preventScroll: true});
            this.arrastreAncho = {campo, x: evento.clientX, ancho: this.anchoColumna(campo, control)};
            control.setPointerCapture(evento.pointerId);
            control.closest('th').draggable = false;
        },
        moverAncho(evento) {
            const arrastre = this.arrastreAncho;
            if (arrastre) this.ajustarAncho(arrastre.campo, arrastre.ancho + evento.clientX - arrastre.x);
        },
        terminarAncho(control) {
            this.arrastreAncho = null;
            control.closest('th').draggable = true;
        },
        placeColumn(from, to) {
            const headers = this.headers();
            if (headers[from]?.dataset.gridFixed || headers[to]?.dataset.gridFixed) return;
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
        get columnScope() { return this.server && this.columnField ? 'Todas las filas de la consulta' : this.client ? 'Todas las filas de la tabla' : 'Filas cargadas; los filtros generales conservan toda la consulta'; },
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
                requestAnimationFrame(() => { if (this.menuOpen) { this.positionMenu(); this.menuItems()[0]?.focus(); } });
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
        menuItems() {
            return [...this.$refs.actionMenu.querySelectorAll('[role^="menuitem"]:not([disabled]):not([aria-disabled="true"])')].filter(item => item.getClientRects().length);
        },
        menuKey(event) {
            if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); this.closeMenu(); return; }
            if (event.key === 'Tab') { this.closeMenu(false); return; }
            const items = this.menuItems();
            const position = items.indexOf(document.activeElement);
            const index = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1
                : event.key === 'ArrowDown' ? (position + 1) % items.length
                : event.key === 'ArrowUp' ? (position - 1 + items.length) % items.length : null;
            if (index !== null) { event.preventDefault(); items[index]?.focus(); }
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
        reset() { this.hidden = []; this.compact = false; this.anchosColumnas = {}; this.columnOrder = this.columns.map((_, index) => index); this.columnFilters = {}; this.pageSort = ''; this.applyColumns(); if (client) { this.searchText = ''; this.sortField = ''; this.clientPerPage = 15; this.clientPage = 1; this.renderClientRows(); } else this.renderPageRows(); this.closeMenu(false); },
        searchPage() { this.clientPage = 1; this.renderClientRows(); },
        get clientLastPage() { return Math.max(1, Math.ceil(this.clientTotal / this.clientPerPage)); },
        get clientPageNumbers() { return [...new Set([1, ...Array.from({length: 5}, (_, i) => this.clientPage - 2 + i), this.clientLastPage])].filter(n => n > 0 && n <= this.clientLastPage).sort((a,b) => a-b); },
        clientGo(page) { this.clientPage = Math.max(1, Math.min(this.clientLastPage, page)); this.clientFirstPage=this.clientLastLoaded=this.clientPage;this.renderClientRows(); },
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
            const token=JSON.stringify([text,this.sortField,this.sortDirection,this.columnFilters,this.clientPerPage]);
            if(token!==this.clientWindowToken || this.clientAnchor!==this.clientPage){this.clientWindowToken=token;this.clientAnchor=this.clientPage;this.clientFirstPage=this.clientLastLoaded=this.clientPage;}
            const matches = sorted.filter(row => (!text || normalizar(row.textContent).includes(text)) && Object.entries(this.columnFilters).every(([column, filter]) => this.matchesColumn(this.cellText(row, Number(column)), filter)));
            this.clientTotal = matches.length; this.clientPage = Math.min(this.clientPage, this.clientLastPage);
            this.clientFirstPage=Math.min(this.clientFirstPage,this.clientLastPage);this.clientLastLoaded=Math.min(this.clientLastLoaded,this.clientLastPage);
            const visible = new Set(matches.slice((this.clientFirstPage-1)*this.clientPerPage, this.clientLastLoaded*this.clientPerPage));
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
        destroy() {
            this.menuOpen=false;this.observer?.disconnect();this.medidas?.disconnect();
            if(this.superficie){for(const [tipo,fn] of [['wheel',this.alRueda],['scroll',this.alScroll],['touchstart',this.alTocar],['touchmove',this.alMoverTacto],['keydown',this.alTecla]])if(fn)this.superficie.removeEventListener(tipo,fn);}
            if(this.alMedir) window.removeEventListener('resize',this.alMedir);
        },
    });
})();
