<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extiende taxonomia.especimenes con los campos del espécimen que introduce la
 * "Plantilla base datos laboratorio invertebrados v2" y que hoy se descartaban
 * en el import. Puramente ADITIVA: todas las columnas nullable, sin alterar
 * tipos existentes (no arriesga los ~48k registros ya cargados).
 *
 * NO incluye préstamos (loan*) — pertenecen al módulo Gestión de Préstamos.
 * NO incluye los "campos por defecto" institucionales (institutionCode, license,
 * basisOfRecord, datasetName…) — viven una sola vez en DatasetConfig.
 *
 * `endemic` (booleano) se conserva; se agrega `endemic_verbatim` (texto) para el
 * valor descriptivo que trae la plantilla ("yes, Colombia", "endemic, Galápagos").
 */
return new class extends Migration
{
    /** @var array<string, string> columna => tipo ('string'|'text') */
    private array $columnas = [
        // Obligatorias (plantilla)
        'record_number' => 'string',
        'origin' => 'string',
        'identified_by' => 'string',
        'date_determined' => 'string',
        'research_permit' => 'string',
        'transport_permit' => 'string',
        // Opcional-si-aplica
        'export_import_authorization' => 'string',
        'scientific_name_authorship' => 'string',
        'lat_lon_max_error' => 'string',
        // Opcionales
        'clade' => 'string',
        'identification_qualifier' => 'string',
        'identification_remarks' => 'text',
        'vernacular_name' => 'string',
        'type_notes' => 'text',
        'continent' => 'string',
        'country_code' => 'string',
        'locality_notes' => 'text',
        'locality_code' => 'string',
        'elevation_max_error' => 'string',
        'verbatim_elevation' => 'string',
        'verbatim_depth' => 'string',
        'verbatim_latitude' => 'string',
        'verbatim_longitude' => 'string',
        'verbatim_coordinate_system' => 'string',
        'verbatim_srs' => 'string',
        'information_withheld' => 'text',
        'prior_owner' => 'string',
        'located_at' => 'string',
        // Uso interno
        'ipt_upload' => 'string',
        'record_created_by' => 'string',
        'responsible_researcher_export' => 'string',
        'endemic_verbatim' => 'string',
    ];

    public function up(): void
    {
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            foreach ($this->columnas as $columna => $tipo) {
                if (Schema::hasColumn('taxonomia.especimenes', $columna)) {
                    continue;
                }
                $tipo === 'text'
                    ? $table->text($columna)->nullable()
                    : $table->string($columna, 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('taxonomia.especimenes', function (Blueprint $table): void {
            foreach (array_keys($this->columnas) as $columna) {
                if (Schema::hasColumn('taxonomia.especimenes', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
