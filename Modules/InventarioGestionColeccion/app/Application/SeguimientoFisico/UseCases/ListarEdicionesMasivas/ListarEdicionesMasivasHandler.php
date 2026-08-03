<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ListarEdicionesMasivas;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EdicionMasiva;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\BitacoraEdicionMasivaRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Services\RegistroColumnasEspecimen;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoEdicionMasiva;

/**
 * Historial de ediciones masivas, para el panel desde el que se deshacen.
 *
 * Devuelve cada entrada ya redactada en una frase: el curador tiene que poder
 * reconocer la operación que quiere revertir sin descifrar nombres de columna.
 */
final class ListarEdicionesMasivasHandler
{
    public function __construct(
        private readonly BitacoraEdicionMasivaRepositoryInterface $bitacoraRepo,
    ) {}

    public function handle(ListarEdicionesMasivasInput $input): ListarEdicionesMasivasOutput
    {
        $etiquetas = [];
        foreach (RegistroColumnasEspecimen::todas() as $col) {
            $etiquetas[$col['clave']] = $col['etiqueta'];
        }

        $items = array_map(
            function (EdicionMasiva $e) use ($etiquetas): array {
                $campo = $etiquetas[$e->campo()] ?? $e->campo();

                return [
                    'id' => $e->id(),
                    'resumen' => $this->resumir($e, $campo),
                    'campo' => $campo,
                    'totalAfectados' => $e->totalAfectados(),
                    'actor' => $e->actorNombre(),
                    'fecha' => $e->creadoEn()->format('Y-m-d H:i'),
                    'deshecha' => $e->fueDeshecha(),
                ];
            },
            $this->bitacoraRepo->listarRecientes(max(1, $input->limite)),
        );

        return new ListarEdicionesMasivasOutput($items);
    }

    private function resumir(EdicionMasiva $e, string $campo): string
    {
        return match ($e->tipo()) {
            TipoEdicionMasiva::ReemplazarTexto => sprintf(
                'En «%s» se reemplazó «%s» por «%s»',
                $campo,
                (string) $e->textoBuscado(),
                $e->textoReemplazo() === '' || $e->textoReemplazo() === null ? '(nada)' : $e->textoReemplazo(),
            ),
            TipoEdicionMasiva::EdicionCelda => $e->valorAplicado() === null
                ? sprintf('Se vació «%s» en una celda', $campo)
                : sprintf('Se puso «%s» en «%s» de una celda', $e->valorAplicado(), $campo),
            TipoEdicionMasiva::FijarValor => $e->valorAplicado() === null
                ? sprintf('Se vació «%s»', $campo)
                : sprintf('Se puso «%s» en «%s»', $e->valorAplicado(), $campo),
        };
    }
}
