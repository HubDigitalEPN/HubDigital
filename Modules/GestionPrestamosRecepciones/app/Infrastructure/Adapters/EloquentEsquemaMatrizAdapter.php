<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Application\Ports\EsquemaMatrizPort;
use Modules\GestionPrestamosRecepciones\Domain\Services\EsquemaMatriz;

final class EloquentEsquemaMatrizAdapter implements EsquemaMatrizPort
{
    public function leer(): array
    {
        $json = DB::table('recepciones.configuracion_matriz')->where('clave', 'depositos')->value('definicion');
        if ($json === null) throw new \RuntimeException('La configuración de la matriz de depósito todavía no está disponible.');
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function guardarColumna(array $columna, ?string $campoAnterior, string $usuarioId): void
    {
        DB::transaction(function () use ($columna, $campoAnterior, $usuarioId): void {
            $fila = DB::table('recepciones.configuracion_matriz')->where('clave', 'depositos')->lockForUpdate()->first();
            if ($fila === null) throw new \RuntimeException('La configuración de la matriz no está disponible.');
            $definicion = json_decode($fila->definicion, true, 512, JSON_THROW_ON_ERROR);
            $columnas = $definicion['columnas'];
            $indice = $campoAnterior === null ? false : array_search($campoAnterior, array_column($columnas, 'campo'), true);
            if ($campoAnterior !== null && $indice === false) throw new \DomainException('La columna cambió. Recarga la configuración.');
            foreach ($columnas as $i => $existente) {
                if ($i !== $indice && EsquemaMatriz::clave($existente['campo']) === EsquemaMatriz::clave($columna['campo'])) {
                    throw new \DomainException('Ya existe una columna con ese campo.');
                }
            }
            if ($indice === false) $columnas[] = $columna + ['plantilla_depositante' => true];
            else $columnas[$indice] = $columna + ['plantilla_depositante' => $columnas[$indice]['plantilla_depositante'] ?? true];
            EsquemaMatriz::mapa($columnas);
            $excepcion = $columna['excepcion_campo'] ?? '';
            if ($excepcion !== '' && (! in_array($excepcion, array_column($columnas, 'campo'), true) || trim($columna['excepcion_valor'] ?? '') === '')) {
                throw new \DomainException('La excepción debe usar un campo configurado y un valor.');
            }
            $definicion['columnas'] = $columnas;
            DB::table('recepciones.configuracion_matriz')->where('clave', 'depositos')->update([
                'definicion' => json_encode($definicion, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'actualizado_por' => $usuarioId, 'updated_at' => now(),
            ]);
        });
    }
}
