<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rellena la junta de depósito de los especímenes que ya estaban en la colección.
 *
 * Solo la parte DETERMINISTA, la que se puede deducir del propio `codigo_catalogo` sin
 * ambigüedad. El vínculo fila↔espécimen (`registro_deposito_id`) NO se hace aquí:
 * depende del orden que devuelve el repositorio de matrices, que no es reproducible con
 * confianza en SQL puro. Eso lo hace el comando `deposito:vincular-especimenes`, que
 * reconstruye el orden con el mismo repositorio que usó el ingreso.
 *
 * Idempotente por los `IS NULL`: reejecutarla no pisa nada.
 *
 * Los especímenes cuya solicitud ya no existe (hay 13, de `MEPN-INV-DEP-00001`) se
 * quedan con `numero_solicitud_deposito` relleno y `solicitud_deposito_id` en null.
 * Eso no es un fallo del backfill: es exactamente la verdad sobre ellos, y es la
 * condición con la que el comando de saneamiento los marca para revisión.
 */
return new class extends Migration
{
    /** Formato del código derivado del depósito: MEPN-INV-DEP-00001-0001. */
    private const PATRON_CODIGO = '^MEPN-INV-DEP-\d{5}-\d{4}$';

    public function up(): void
    {
        // 1. Número de solicitud e índice de fila: se leen del propio código.
        DB::statement(
            "UPDATE taxonomia.especimenes SET
                numero_solicitud_deposito = substring(codigo_catalogo from '^(MEPN-INV-DEP-\d{5})-\d{4}$'),
                indice_matriz             = substring(codigo_catalogo from '^MEPN-INV-DEP-\d{5}-(\d{4})$')::int
             WHERE codigo_catalogo ~ '".self::PATRON_CODIGO."'
               AND (numero_solicitud_deposito IS NULL OR indice_matriz IS NULL)"
        );

        // 2. Identidad estable de la solicitud y tipo de trámite, cuando la solicitud
        //    sigue existiendo.
        DB::statement(
            'UPDATE taxonomia.especimenes e SET
                solicitud_deposito_id = s.id,
                tipo_tramite_origen   = COALESCE(e.tipo_tramite_origen, s.tipo_tramite)
             FROM recepciones.solicitudes_deposito s
             WHERE s.numero = e.numero_solicitud_deposito
               AND e.solicitud_deposito_id IS NULL'
        );

        // 3. Fecha de ingreso: para el material de depósito, la fila se creó en el
        //    momento en que entró a la colección.
        DB::statement(
            'UPDATE taxonomia.especimenes SET ingresado_en = created_at
             WHERE numero_solicitud_deposito IS NOT NULL
               AND ingresado_en IS NULL
               AND created_at IS NOT NULL'
        );
    }

    public function down(): void
    {
        // Estas columnas no existían antes de la migración de esquema, así que
        // vaciarlas devuelve el estado anterior sin destruir nada propio.
        DB::statement(
            'UPDATE taxonomia.especimenes SET
                numero_solicitud_deposito = NULL,
                indice_matriz             = NULL,
                solicitud_deposito_id     = NULL,
                tipo_tramite_origen       = NULL,
                ingresado_en              = NULL
             WHERE numero_solicitud_deposito IS NOT NULL'
        );
    }
};
