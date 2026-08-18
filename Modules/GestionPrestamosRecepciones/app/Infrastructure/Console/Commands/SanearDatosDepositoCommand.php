<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\GestionPrestamosRecepciones\Application\Ports\IngresoColeccionPort;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;

/**
 * Sanea el material de depósito que ya está en la colección.
 *
 * Hace dos cosas, las dos sobre datos que quedaron mal por cómo funcionaba antes el
 * traspaso entre módulos:
 *
 *  1. **Rellena los campos Darwin Core perdidos.** El ingreso mapeaba treinta y dos
 *     campos de la plantilla y no se los pasaba a la entidad, y la jerarquía taxonómica
 *     declarada no tenía dónde guardarse. Se relee la matriz y se escribe únicamente
 *     donde hay hueco: lo que ya tiene valor no se toca.
 *  2. **Marca los huérfanos.** El material cuyo trámite se borró después de ingresar
 *     queda señalado para que el curador lo resuelva. No se borra nada.
 *
 * Corre en seco por defecto:
 *
 *   php artisan deposito:sanear-datos            # simulación
 *   php artisan deposito:sanear-datos --aplicar  # escribe
 */
final class SanearDatosDepositoCommand extends Command
{
    /** Texto con el que se anota al material sin trámite de origen. */
    private const MOTIVO_HUERFANO = 'procedencia de depósito huérfana: el trámite de origen ya no existe';

    protected $signature = 'deposito:sanear-datos
        {--aplicar : Escribe los cambios. Sin esta opción solo informa qué haría.}
        {--solicitud= : Limita el saneamiento a una solicitud concreta (su número).}';

    protected $description = 'Recupera los campos Darwin Core perdidos del material depositado y marca los huérfanos';

    public function handle(IngresoColeccionPort $ingresoColeccion): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $numero = $this->option('solicitud');

        $solicitudes = SolicitudDepositoEloquentModel::query()
            ->when(is_string($numero) && $numero !== '', fn ($q) => $q->where('numero', $numero))
            ->orderBy('numero')
            ->get(['id', 'numero']);

        $this->line($aplicar ? 'Aplicando saneamiento…' : 'Simulación (usa --aplicar para escribir).');
        $this->newLine();

        $filas = [];

        foreach ($solicitudes as $solicitud) {
            $resultado = $ingresoColeccion->sanearDatosDwC((string) $solicitud->id, ! $aplicar);

            $filas[] = [
                (string) $solicitud->numero,
                $resultado['especimenesTocados'],
                $resultado['columnasEscritas'],
            ];
        }

        if ($filas !== []) {
            $this->table(['Solicitud', 'Espécimenes', 'Columnas rellenadas'], $filas);
        } else {
            $this->warn('No hay solicitudes de depósito que sanear.');
        }

        // Los huérfanos no cuelgan de ninguna solicitud, así que se tratan aparte y solo
        // cuando el saneamiento cubre todo el conjunto.
        if (! is_string($numero) || $numero === '') {
            $huerfanos = $aplicar ? $ingresoColeccion->marcarHuerfanos(self::MOTIVO_HUERFANO) : 0;

            $this->newLine();
            $this->line($aplicar
                ? sprintf('Huérfanos marcados para revisión: %d', $huerfanos)
                : 'Los huérfanos se marcarán al aplicar (no se borra ninguno).');
        }

        if (! $aplicar) {
            $this->newLine();
            $this->info('Vuelve a correrlo con --aplicar para escribir.');
        }

        return self::SUCCESS;
    }
}
