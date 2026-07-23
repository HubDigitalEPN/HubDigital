<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría de la edición curatorial de celdas anómalas.
 *
 * El depositante firma una declaración al enviar la matriz. Si después el curador sana
 * una celda —una coordenada fuera de rango, una fecha imposible— tiene que quedar
 * constancia de quién, qué campo, con qué valores y cuándo, y el depositante debe poder
 * verlo. Sin esto, la corrección sería un cambio silencioso sobre un dato firmado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones.registros_especimen', function (Blueprint $table): void {
            $table->jsonb('correcciones_curatoriales')
                ->nullable()
                ->after('normalizaciones')
                ->comment('Historial de correcciones del curador sobre celdas anómalas: campo, valor anterior, valor nuevo, curador y fecha.');
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.registros_especimen', function (Blueprint $table): void {
            $table->dropColumn('correcciones_curatoriales');
        });
    }
};
