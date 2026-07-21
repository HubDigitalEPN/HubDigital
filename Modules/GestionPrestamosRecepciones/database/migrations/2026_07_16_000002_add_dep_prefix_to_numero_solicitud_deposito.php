<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migra los números de solicitud de depósito al nuevo formato con prefijo DEP:
 * `MEPN-INV-00001` → `MEPN-INV-DEP-00001`.
 *
 * El prefijo DEP distingue las solicitudes de depósito/donación de los códigos de
 * préstamo (que viven en otra tabla con prefijo MEPN-INV-LOAN-). Solo se tocan las
 * filas con el formato anterior exacto; conserva la secuencia numérica intacta.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE recepciones.solicitudes_deposito
            SET numero = 'MEPN-INV-DEP-' || SUBSTRING(numero FROM 10)
            WHERE numero ~ '^MEPN-INV-[0-9]{5}$'
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            UPDATE recepciones.solicitudes_deposito
            SET numero = 'MEPN-INV-' || SUBSTRING(numero FROM 14)
            WHERE numero ~ '^MEPN-INV-DEP-[0-9]{5}$'
        SQL);
    }
};
