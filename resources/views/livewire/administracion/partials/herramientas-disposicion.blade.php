<div class="hub-editor-herramientas" data-herramientas-disposicion>
    <button type="button" class="hub-ig-button" data-publicar-posiciones x-on:click="disposicion.guardar()" x-bind:disabled="!disposicion.pendiente || disposicion.guardando">Guardar y publicar</button>
    <button type="button" class="hub-ig-button" data-restaurar-posiciones x-on:click="if(confirm('¿Restaurar la distribución original de esta página o paso?')) disposicion.guardar(true)" x-bind:disabled="disposicion.guardando">Restaurar posiciones</button>
    <span role="status" x-text="disposicion.guardando ? 'Guardando posiciones…' : disposicion.mensaje"></span>
</div>
