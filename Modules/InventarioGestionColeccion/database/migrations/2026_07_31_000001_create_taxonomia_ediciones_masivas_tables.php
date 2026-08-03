<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de ediciones masivas del catálogo, para poder deshacerlas.
 *
 * Dos tablas porque nacen acopladas: la cabecera describe la operación (qué
 * campo, con qué valor, quién y cuándo) y el detalle guarda UNA FILA POR
 * ESPÉCIMEN con el valor que tenía antes.
 *
 * El detalle es tabla hija y no una columna JSON en la cabecera porque el
 * deshacer necesita marcar fila a fila si se revirtió o si quedó en conflicto
 * (alguien la cambió después). Con un JSON habría que reescribir el documento
 * entero en cada reversión. Además el esquema `taxonomia` no usa JSON en
 * ninguna de sus tablas: la convención de la casa es una fila por agregado.
 *
 * `valor_previo` y `valor_aplicado` son `text` sin tipar aposta: guardan la
 * representación `::text` que rinde Postgres para cualquier columna editable
 * (texto, booleano…), de modo que la comparación del deshacer se hace siempre
 * sobre el mismo formato y no depende de cómo PHP haya castado el valor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taxonomia.ediciones_masivas')) {
            Schema::create('taxonomia.ediciones_masivas', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                // fijar_valor | reemplazar_texto | edicion_celda
                $table->string('tipo', 40);
                $table->string('campo', 80);
                // Nulo cuando la operación vacía el campo, o cuando cada fila
                // recibe un valor distinto (buscar y reemplazar).
                $table->text('valor_aplicado')->nullable();
                $table->string('texto_buscado', 255)->nullable();
                $table->string('texto_reemplazo', 255)->nullable();
                $table->unsignedInteger('total_afectados')->default(0);
                $table->uuid('actor_id')->nullable();
                $table->string('actor_nombre', 255)->nullable();
                $table->timestamp('deshecha_en')->nullable();
                $table->timestamps();

                $table->index('created_at');
            });
        }

        if (! Schema::hasTable('taxonomia.ediciones_masivas_detalle')) {
            Schema::create('taxonomia.ediciones_masivas_detalle', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('edicion_id');
                $table->uuid('especimen_id');
                $table->text('valor_previo')->nullable();
                $table->text('valor_aplicado')->nullable();
                // pendiente | revertido | conflicto | desaparecido
                $table->string('estado_reversion', 20)->default('pendiente');
                $table->timestamp('revertido_en')->nullable();

                $table->foreign('edicion_id')
                    ->references('id')
                    ->on('taxonomia.ediciones_masivas')
                    ->cascadeOnDelete();

                $table->foreign('especimen_id')
                    ->references('id')
                    ->on('taxonomia.especimenes')
                    ->cascadeOnDelete();

                $table->index('edicion_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomia.ediciones_masivas_detalle');
        Schema::dropIfExists('taxonomia.ediciones_masivas');
    }
};
