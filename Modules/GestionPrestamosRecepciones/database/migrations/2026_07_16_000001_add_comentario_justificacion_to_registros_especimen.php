<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la columna comentario_justificacion a recepciones.registros_especimen.
 *
 * Permite que el depositante añada un comentario de texto libre para justificar
 * ante el curador por qué ingresa un nombre que no figura en el catálogo de
 * referencia (complementa el motivo predefinido en motivo_justificacion).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones.registros_especimen', function (Blueprint $table): void {
            $table->text('comentario_justificacion')->nullable()->after('motivo_justificacion');
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.registros_especimen', function (Blueprint $table): void {
            $table->dropColumn('comentario_justificacion');
        });
    }
};
