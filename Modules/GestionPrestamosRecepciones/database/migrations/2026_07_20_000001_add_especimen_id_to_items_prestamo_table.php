<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referencia estable al espécimen del módulo de inventario.
 *
 * Se apunta al PK uuid de `taxonomia.especimenes` y no a `codigo_catalogo`: esa
 * columna dejó de ser única, por lo que no identifica una fila.
 *
 * Nullable por los ítems creados antes de la conexión con el catálogo, cuando el
 * código del espécimen se escribía como texto libre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('prestamos.items_prestamo', 'especimen_id')) {
            return;
        }

        Schema::table('prestamos.items_prestamo', function (Blueprint $table): void {
            $table->uuid('especimen_id')->nullable()->after('especimen_codigo_externo');

            $table->index('especimen_id');

            $table->foreign('especimen_id')
                ->references('id')
                ->on('taxonomia.especimenes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prestamos.items_prestamo', function (Blueprint $table): void {
            $table->dropForeign(['especimen_id']);
            $table->dropIndex(['especimen_id']);
            $table->dropColumn('especimen_id');
        });
    }
};
