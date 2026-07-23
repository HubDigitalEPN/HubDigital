<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha en que un espécimen depositado volvió a manos de su depositante.
 *
 * Acompaña a `estado_custodia = 'Devuelto'`. La fila no se borra nunca: el rastro de
 * qué material estuvo bajo custodia y cuándo salió es parte del patrimonio documental
 * de la colección, y el Acta de Recepción sigue apuntando a estos especímenes.
 *
 * Nulo en todo lo demás: el material heredado, las donaciones y los depósitos que
 * siguen en custodia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            $table->timestampTz('devuelto_en')
                ->nullable()
                ->after('estado_custodia')
                ->comment('Fecha de devolución al depositante. Solo se llena cuando estado_custodia = Devuelto.');
        });
    }

    public function down(): void
    {
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            $table->dropColumn('devuelto_en');
        });
    }
};
