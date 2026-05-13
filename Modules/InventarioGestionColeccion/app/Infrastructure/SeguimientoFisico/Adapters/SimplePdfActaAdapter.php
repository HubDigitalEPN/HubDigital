<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Adapters;

use Illuminate\Support\Facades\Storage;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\Ports\GeneradorActaPdfPort;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\EntidadDepositante;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Entities\Especimen;

class SimplePdfActaAdapter implements GeneradorActaPdfPort
{
    /** @param Especimen[] $especimenes */
    public function generar(EntidadDepositante $entidad, array $especimenes): string
    {
        $fecha = (new \DateTimeImmutable)->format('Y-m-d_H-i-s');
        $nombreArchivo = "actas/acta_entrega_{$entidad->id()}_{$fecha}.txt";

        $lineas = [
            'ACTA DE ENTREGA DE ESPECÍMENES',
            str_repeat('=', 50),
            "Fecha: {$fecha}",
            "Entidad depositante: {$entidad->nombre()}",
            "Tipo: {$entidad->tipo()->value}",
            "Contacto: {$entidad->contacto()}",
            '',
            'ESPECÍMENES ENTREGADOS:',
            str_repeat('-', 30),
        ];

        foreach ($especimenes as $i => $especimen) {
            $lineas[] = ($i + 1).". {$especimen->codigoCatalogo()} — {$especimen->localidad()} ({$especimen->fechaColecta()})";
        }

        $lineas[] = '';
        $lineas[] = 'Total de especímenes: '.count($especimenes);

        Storage::put($nombreArchivo, implode("\n", $lineas));

        return Storage::path($nombreArchivo);
    }
}
