<div>
    {{-- El visitante solo aloja el mapa en su modo: la búsqueda es por nombre
         científico y la navegación es de solo lectura. El acceso lo controla la
         ruta (middleware `visitante`); aquí no hay enlaces a otras áreas. --}}
    <livewire:inventario-mapa-interactivo modo="visitante" />
</div>
