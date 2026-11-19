<div class="hub-editor-herramientas" data-herramientas-disposicion>
    <button type="button" class="hub-ig-button" x-on:click="disposicion.activar()" x-bind:aria-pressed="disposicion.activa" x-text="disposicion.activa ? 'Volver a editar textos' : 'Reorganizar posiciones'"></button>
    <button type="button" class="hub-ig-button" x-show="disposicion.activa" x-cloak x-on:click="disposicion.guardar()" x-bind:disabled="!disposicion.pendiente || disposicion.guardando">Guardar posiciones</button>
    <button type="button" class="hub-ig-button" x-show="disposicion.activa" x-cloak x-on:click="if(confirm('¿Restaurar la distribución original de esta página o paso?')) disposicion.guardar(true)" x-bind:disabled="disposicion.guardando">Restaurar posiciones</button>
    <span role="status" x-text="disposicion.guardando ? 'Guardando posiciones…' : disposicion.mensaje"></span>
</div>
