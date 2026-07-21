<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Tests\Support;

use Modules\GestionPrestamosRecepciones\Application\Ports\CatalogoEspecimenesPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\EspecimenCatalogoDto;
use Modules\GestionPrestamosRecepciones\Application\Ports\FichaEspecimenActaDto;

/**
 * Doble de prueba del {@see CatalogoEspecimenesPort} para Behat.
 *
 * Permite que los pasos `Dado` siembren especímenes sin tocar la base de datos
 * del inventario: los tests de comportamiento del use case no deben depender de
 * la traducción ACL real (esa se cubre en el test de integración del adaptador).
 */
final class FakeCatalogoEspecimenesPort implements CatalogoEspecimenesPort
{
    /** @var array<string, EspecimenCatalogoDto> */
    private array $especimenes = [];

    public function agregar(EspecimenCatalogoDto $especimen): void
    {
        $this->especimenes[$especimen->especimenId] = $especimen;
    }

    public function buscarDisponibles(string $texto, int $limite = 15): array
    {
        $texto = trim($texto);

        if ($texto === '') {
            return [];
        }

        $coincidencias = array_filter(
            $this->especimenes,
            static function (EspecimenCatalogoDto $e) use ($texto): bool {
                return stripos($e->codigoCatalogo, $texto) !== false
                    || stripos($e->nombreCientifico ?? '', $texto) !== false;
            }
        );

        return array_slice(array_values($coincidencias), 0, $limite);
    }

    public function obtenerPorId(string $especimenId): ?EspecimenCatalogoDto
    {
        return $this->especimenes[$especimenId] ?? null;
    }

    public function obtenerPorIds(array $especimenIds): array
    {
        $resultado = [];

        foreach ($especimenIds as $id) {
            if (isset($this->especimenes[$id])) {
                $resultado[$id] = $this->especimenes[$id];
            }
        }

        return $resultado;
    }

    public function obtenerFichasParaActa(array $especimenIds): array
    {
        $resultado = [];

        foreach ($especimenIds as $id) {
            if (! isset($this->especimenes[$id])) {
                continue;
            }

            $especimen = $this->especimenes[$id];

            // ponytail: la familia, el sexo y la procedencia salen del inventario real,
            // que este doble no modela. Sembrarlos aquí si algún escenario llega a
            // afirmar sobre esas columnas del acta.
            $resultado[$id] = new FichaEspecimenActaDto(
                especimenId: $especimen->especimenId,
                codigoCatalogo: $especimen->codigoCatalogo,
                familia: null,
                sexo: null,
                especie: $especimen->nombreCientifico,
                provincia: null,
                localidad: null,
            );
        }

        return $resultado;
    }
}
