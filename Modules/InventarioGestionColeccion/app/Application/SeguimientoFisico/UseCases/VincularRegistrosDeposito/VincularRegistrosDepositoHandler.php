<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\VincularRegistrosDeposito;

use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\EspecimenRepositoryInterface;

/**
 * Ata cada espécimen ya ingresado a la fila de matriz que lo declaró.
 *
 * Existe porque el material que entró antes de que la junta existiera solo tiene el
 * `codigo_catalogo` derivado, y de ahí se puede deducir el índice de fila pero no el
 * uuid del registro.
 *
 * **No asume el orden.** El ingreso original numeró las filas por su posición en
 * `array_values($matriz->registros())`, y esa relación de Eloquent no declara `orderBy`,
 * así que el orden que devuelve PostgreSQL no está garantizado. Antes de escribir nada
 * se comprueba que el nombre científico de la fila N coincida con el `taxon_verbatim`
 * del espécimen que quedó en el índice N. Si algo no cuadra, se aborta la solicitud
 * entera y se reporta: es preferible dejar el vínculo sin hacer que atarlo mal.
 *
 * La comparación es laxa a propósito (sin acentos, sin mayúsculas, sin espacios de
 * más): el ingreso pudo normalizar el nombre por el camino, y una diferencia de caja no
 * es una discrepancia de orden.
 */
final class VincularRegistrosDepositoHandler
{
    public function __construct(
        private readonly EspecimenRepositoryInterface $especimenRepo,
    ) {}

    public function handle(VincularRegistrosDepositoInput $input): VincularRegistrosDepositoOutput
    {
        $especimenes = $this->especimenRepo->especimenesPorIndiceDeSolicitud($input->solicitudDepositoId);

        $discrepancias = [];
        $porVincular = [];
        $especimenPorRegistro = [];
        $yaVinculados = 0;
        $sinEspecimen = 0;

        foreach ($input->registroIdPorIndice as $indice => $registroId) {
            $especimen = $especimenes[$indice] ?? null;

            if ($especimen === null) {
                $sinEspecimen++;

                continue;
            }

            $discrepancia = $this->discrepanciaDeNombre($indice, $input, $especimen['taxonVerbatim']);

            if ($discrepancia !== null) {
                $discrepancias[] = $discrepancia;

                continue;
            }

            $especimenPorRegistro[$registroId] = $especimen['id'];

            if ($especimen['registroDepositoId'] !== null) {
                if ($especimen['registroDepositoId'] !== $registroId) {
                    $discrepancias[] = sprintf(
                        'fila %d: el espécimen ya está atado al registro %s, no a %s',
                        $indice,
                        $especimen['registroDepositoId'],
                        $registroId,
                    );

                    continue;
                }

                $yaVinculados++;

                continue;
            }

            $porVincular[$especimen['id']] = $registroId;
        }

        // Todo o nada por solicitud: un vínculo mal puesto es peor que ninguno, y el
        // índice único haría fallar la mitad del lote dejando el resto a medias.
        if ($discrepancias !== [] || $input->simular) {
            return new VincularRegistrosDepositoOutput(
                vinculados: 0,
                yaVinculados: $yaVinculados,
                sinEspecimen: $sinEspecimen,
                especimenPorRegistro: $discrepancias === [] ? $especimenPorRegistro : [],
                discrepancias: $discrepancias,
                simulado: true,
            );
        }

        return new VincularRegistrosDepositoOutput(
            vinculados: $this->especimenRepo->vincularRegistrosDeposito($porVincular),
            yaVinculados: $yaVinculados,
            sinEspecimen: $sinEspecimen,
            especimenPorRegistro: $especimenPorRegistro,
            discrepancias: [],
            simulado: false,
        );
    }

    /**
     * Devuelve el texto de la discrepancia, o null si la fila y el espécimen concuerdan.
     *
     * Si no se pasó nombre de referencia para ese índice no se puede verificar; se deja
     * pasar en vez de inventar una discrepancia.
     */
    private function discrepanciaDeNombre(
        int $indice,
        VincularRegistrosDepositoInput $input,
        ?string $taxonVerbatim,
    ): ?string {
        $esperado = $input->nombreCientificoPorIndice[$indice] ?? null;

        if ($esperado === null || $taxonVerbatim === null) {
            return null;
        }

        if ($this->normalizar($esperado) === $this->normalizar($taxonVerbatim)) {
            return null;
        }

        return sprintf(
            'fila %d: la matriz declara "%s" pero el espécimen ingresado dice "%s"',
            $indice,
            $esperado,
            $taxonVerbatim,
        );
    }

    private function normalizar(string $valor): string
    {
        $sinAcentos = @iconv('UTF-8', 'ASCII//TRANSLIT', $valor);

        return preg_replace('/\s+/', ' ', mb_strtolower(trim($sinAcentos !== false ? $sinAcentos : $valor))) ?? '';
    }
}
