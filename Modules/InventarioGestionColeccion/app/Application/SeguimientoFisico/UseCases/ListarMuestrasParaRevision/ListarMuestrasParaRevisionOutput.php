<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarMuestrasParaRevision;

final readonly class ListarMuestrasParaRevisionOutput
{
    /**
     * @param  list<array{
     *   id: string,
     *   codigoMuestra: ?string,
     *   fechaVerbatim: ?string,
     *   localidadVerbatim: ?string,
     *   colector: ?string,
     *   samplingProtocol: ?string,
     *   estadoRevision: string,
     *   motivoRevision: ?string,
     *   conteoEspecimenes: int
     * }>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $pagina,
        public int $porPagina,
    ) {}

    public function totalPaginas(): int
    {
        return $this->total === 0 ? 1 : (int) ceil($this->total / $this->porPagina);
    }
}
