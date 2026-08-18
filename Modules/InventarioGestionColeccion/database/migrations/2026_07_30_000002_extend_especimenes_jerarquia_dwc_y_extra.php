<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cierra las fugas de datos del ingreso por depósito.
 *
 * La matriz Darwin Core del depositante trae columnas que el mapeo leía o ni siquiera
 * miraba, y que terminaban en ningún sitio. Tres grupos:
 *
 *  1. **Jerarquía taxonómica** (kingdom..taxonRank). Un depósito puede traer material
 *     cuyo taxón aún no está catalogado, así que `taxon_id` se queda en null y hasta
 *     ahora lo único que sobrevivía era `scientificName` como `taxon_verbatim`. Se
 *     persiste la jerarquía tal como llegó: cumple "cero pérdida" resuelva o no el
 *     taxón, y es la procedencia contra la que se concilia después.
 *     `class` y `order` son palabras reservadas en SQL → `dwc_class` / `dwc_order`.
 *
 *  2. **Columnas sin destino en el mapeo**: samplingProtocol, otherCatalogNumbers,
 *     eventTime, projectName, collectionNotes, medium, movilizationPermit, language.
 *
 *  3. **`dwc_extra`**: catch-all para toda clave normalizada que el mapeo no consumió.
 *     Es la garantía de "cero pérdida" que el docblock de FilaCatalogoMapper ya promete
 *     y que hoy no cumple. `day`/`month`/`year` viven aquí a propósito: son redundantes
 *     con `fecha_verbatim` y no merecen columna.
 *
 * ADITIVA: todo nullable salvo `dwc_extra`, que lleva DEFAULT. En PostgreSQL ≥ 11
 * añadir una columna con default no volátil no reescribe la tabla, así que las 48.883
 * filas existentes no se tocan.
 */
return new class extends Migration
{
    /** @var array<string, string> columna => tipo ('string'|'text') */
    private array $jerarquia = [
        'kingdom' => 'string',
        'phylum' => 'string',
        'dwc_class' => 'string',
        'dwc_order' => 'string',
        'suborder' => 'string',
        'family' => 'string',
        'subfamily' => 'string',
        'tribe' => 'string',
        'genus' => 'string',
        'specific_epithet' => 'string',
        'infraspecific_epithet' => 'string',
        'taxon_rank' => 'string',
    ];

    /** @var array<string, string> columna => tipo ('string'|'text') */
    private array $fugas = [
        'sampling_protocol' => 'text',
        'other_catalog_numbers' => 'text',
        'event_time' => 'string',
        'project_name' => 'string',
        'collection_notes' => 'text',
        'medium' => 'string',
        'movilization_permit' => 'string',
        'language' => 'string',
    ];

    public function up(): void
    {
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            foreach ([...$this->jerarquia, ...$this->fugas] as $columna => $tipo) {
                if (Schema::hasColumn('taxonomia.especimenes', $columna)) {
                    continue;
                }

                if ($tipo === 'text') {
                    $table->text($columna)->nullable();

                    continue;
                }

                $table->string($columna, 255)->nullable();
            }
        });

        if (! Schema::hasColumn('taxonomia.especimenes', 'dwc_extra')) {
            DB::statement(
                "ALTER TABLE taxonomia.especimenes
                 ADD COLUMN dwc_extra jsonb NOT NULL DEFAULT '{}'::jsonb"
            );
        }
    }

    public function down(): void
    {
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            foreach ([...array_keys($this->jerarquia), ...array_keys($this->fugas), 'dwc_extra'] as $columna) {
                if (Schema::hasColumn('taxonomia.especimenes', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
