<dialog wire:ignore.self x-ref="columnDialog" class="hub-ig-dialog hub-ig-column-popup" aria-label="Acciones de columna" x-on:cancel.prevent="closeColumn()">
    <div class="hub-ig-dialog-heading"><h2 x-text="columns[activeColumn]"></h2><button type="button" class="hub-ig-icon-button" x-on:click="closeColumn()" aria-label="Cerrar columna">×</button></div>
    <div class="hub-ig-column-buttons" role="group" aria-label="Orden y posición de columna">
        <button type="button" class="hub-ig-button" x-on:click="sortColumn('asc')" title="Orden ascendente">↑ A–Z</button>
        <button type="button" class="hub-ig-button" x-on:click="sortColumn('desc')" title="Orden descendente">↓ Z–A</button>
        <button type="button" class="hub-ig-button" x-on:click="moveColumn(activeColumn, -1)" :disabled="columnOrder.indexOf(activeColumn) <= 0" aria-label="Mover columna a la izquierda">←</button>
        <button type="button" class="hub-ig-button" x-on:click="moveColumn(activeColumn, 1)" :disabled="columnOrder.indexOf(activeColumn) >= columnOrder.length-1" aria-label="Mover columna a la derecha">→</button>
        <button type="button" class="hub-ig-button" x-on:click="hideColumn()">Ocultar</button>
    </div>
    <div x-on:keydown.enter.prevent.stop="await applyColumn()" class="hub-ig-dialog-body hub-ig-column-search">
        <p class="hub-ig-scope" x-text="columnScope"></p>
        <label class="hub-ig-dialog-search">Condición<select x-model="columnOperator"><option value="contains">Contiene</option><option value="equals">Es igual a</option><option value="starts">Empieza por</option><option value="notContains">No contiene</option><option value="empty">Sin valor</option></select></label>
        <label class="hub-ig-dialog-search">Buscar en esta columna<input type="search" x-model="columnTerm" maxlength="240" :disabled="columnOperator === 'empty'" autofocus></label>
        <div class="hub-ig-column-buttons"><button type="button" x-on:click="applyColumn()" class="hub-ig-button hub-ig-button-primary">Aplicar</button><button type="button" class="hub-ig-button" x-on:click="applyColumn('', 'contains')">Quitar filtro</button></div>
        <label class="hub-ig-dialog-search">Valores de esta página<input type="search" x-model="valueSearch" placeholder="Buscar un valor"></label>
        <div class="hub-ig-value-list"><template x-for="value in columnValues" :key="value"><button type="button" x-text="value" x-on:click="applyColumn(value, 'equals')"></button></template><p x-show="columnValues.length === 0">Sin valores disponibles.</p></div>
    </div>
</dialog>
