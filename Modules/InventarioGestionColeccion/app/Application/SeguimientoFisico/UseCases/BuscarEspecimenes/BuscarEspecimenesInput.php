<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\BuscarEspecimenes;

/**
 * Input multi-filtro para búsqueda de especímenes.
 *
 * Todos los campos son opcionales y se combinan con AND. Si todos son null
 * el handler debe rechazar (o aplicar un filtro por defecto seguro como
 * para_revision).
 *
 * `familia`: el handler resuelve primero los descendientes del taxon de
 * rango familia que coincida con el nombre dado, y filtra especímenes por
 * los IDs resultantes.
 *
 * `paraRevision`: shortcut que filtra estado_revision='pendiente' AND
 * motivo_revision NOT NULL. Compatible con motivoRevision (ILIKE adicional).
 */
final readonly class BuscarEspecimenesInput
{
    public function __construct(
        /**
         * Búsqueda rápida de una sola caja: matchea en OR contra los campos por
         * los que un curador identifica un espécimen a ojo (códigos, taxón,
         * colector, localidad). Se combina en AND con el resto de filtros.
         */
        public ?string $busquedaGlobal = null,
        public ?string $taxonNombre = null,
        public ?string $codigoCatalogo = null,
        public ?string $occurrenceId = null,
        public ?string $catalogNumber = null,
        public ?string $localidad = null,
        public ?string $colector = null,
        public ?string $familia = null,
        public ?string $fechaDesde = null,
        public ?string $fechaHasta = null,
        public ?string $estado = null,
        public ?string $estadoRevision = null,
        public ?string $motivoRevision = null,
        public bool $paraRevision = false,
        public int $limit = 200,
        /**
         * Paginación server-side (opt-in). Si `page` es null se mantiene el
         * comportamiento anterior (una sola tanda de hasta `limit` filas). Con
         * `page` definido, el repositorio aplica LIMIT/OFFSET y se devuelve el
         * total real para escalar a catálogos de decenas de miles de filas.
         */
        public ?int $page = null,
        public int $perPage = 25,
        /**
         * Cuando es true, "sin filtros" significa *todo el catálogo paginado* en
         * vez de un resultado vacío. Lo activa la pantalla de inventario, que se
         * comporta como una hoja de cálculo: al abrirla debe mostrar los datos.
         * Las demás pantallas lo dejan en false para no traerse decenas de miles
         * de filas por accidente.
         */
        public bool $permitirSinFiltros = false,
        /** Clave de columna a ordenar (lista blanca de RegistroColumnasEspecimen). */
        public ?string $ordenarPor = null,
        /** 'asc' | 'desc'. Cualquier otro valor cae a 'asc' en el repositorio. */
        public string $ordenDireccion = 'asc',
    ) {}

    public function tieneFiltros(): bool
    {
        return $this->busquedaGlobal !== null
            || $this->taxonNombre !== null
            || $this->codigoCatalogo !== null
            || $this->occurrenceId !== null
            || $this->catalogNumber !== null
            || $this->localidad !== null
            || $this->colector !== null
            || $this->familia !== null
            || $this->fechaDesde !== null
            || $this->fechaHasta !== null
            || $this->estado !== null
            || $this->estadoRevision !== null
            || $this->motivoRevision !== null
            || $this->paraRevision;
    }
}
