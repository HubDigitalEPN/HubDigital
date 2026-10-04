<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ReconciliacionCientificaQa6;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taxonomia.correcciones_cientificas')) {
            Schema::create('taxonomia.correcciones_cientificas', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('hallazgo', 40);
                $table->string('entidad', 40);
                $table->uuid('entidad_id');
                $table->jsonb('original');
                $table->jsonb('tratamiento');
                $table->jsonb('fuentes');
                $table->text('decision');
                $table->string('estado', 40);
                $table->timestampTz('registrado_en');
                $table->unique(['hallazgo', 'entidad', 'entidad_id']);
            });
        }

        $this->corregirAnastrepha();
        $this->ponerRaguaEnRevision();
    }

    private function corregirAnastrepha(): void
    {
        // occurrence_id puede repetirse. La fuente identifica esta fila con tres claves.
        $especimen = DB::table('taxonomia.especimenes')->where('fila_origen_excel', 31584)
            ->where('occurrence_id', 'MEPN-INV-37369')->where('old_code', 'LOTE # 641')->first();
        if ($especimen === null || $especimen->taxon_id === null) return;
        $especie = DB::table('taxonomia.taxones')->where('id', $especimen->taxon_id)
            ->where('nombre_cientifico', 'Anastrepha freidbergi')->where('rango', 'especie')->first();
        if ($especie === null) return;

        $original = (array) $especimen;
        $original['linaje'] = $this->linaje($especie->id);
        $fuentes = [ReconciliacionCientificaQa6::FUENTE_TAXONOMIA, ReconciliacionCientificaQa6::FUENTE_ESPECIE];
        $decision = 'QA6-002: relación del género Anastrepha reconciliada con Diptera/Tephritidae. Se conserva el texto y linaje fuente; no cambia la identificación física ni se adoptan sinonimias o subfamilias inferidas.';

        $padre = null;
        foreach ([['Animalia', 'reino'], ['Arthropoda', 'phylum'], ['Insecta', 'clase'],
            ['Diptera', 'orden'], ['Tephritidae', 'familia'], ['Anastrepha', 'genero']] as [$nombre, $rango]) {
            $taxon = DB::table('taxonomia.taxones')->where('nombre_cientifico', $nombre)->where('rango', $rango)->first();
            if ($taxon === null) {
                $id = (string) Str::uuid();
                DB::table('taxonomia.taxones')->insert(['id' => $id, 'nombre_cientifico' => $nombre,
                    'rango' => $rango, 'padre_id' => $padre, 'estado' => 'activo', 'created_at' => now(), 'updated_at' => now()]);
            } else {
                $id = $taxon->id;
                // Conserva niveles intermedios válidos: basta con contener el ancestro esperado.
                if ($padre !== null && ! in_array($padre, array_column($this->linaje($id), 'id'), true)) {
                    // Los ancestros generales no se reescriben al corregir una relación de género.
                    if (in_array($rango, ['phylum', 'clase'], true)) {
                        throw new RuntimeException('QA6-002: ancestros generales incompatibles; revisar antes de publicar.');
                    }
                    $this->auditar('QA6-002', 'taxon', $id,
                        ['taxon' => (array) $taxon, 'linaje' => $this->linaje($id)], ['padre_id' => $padre], $fuentes, $decision, 'reconciliado');
                    DB::table('taxonomia.taxones')->where('id', $id)->update(['padre_id' => $padre, 'updated_at' => now()]);
                }
            }
            $padre = $id;
        }

        if ($especie->padre_id !== $padre) {
            $this->auditar('QA6-002', 'taxon', $especie->id,
                ['taxon' => (array) $especie, 'linaje' => $original['linaje']], ['padre_id' => $padre], $fuentes, $decision, 'reconciliado');
            DB::table('taxonomia.taxones')->where('id', $especie->id)->update(['padre_id' => $padre, 'updated_at' => now()]);
        }
        $this->auditar('QA6-002', 'especimen', $especimen->id, $original,
            ['linaje' => $this->linaje($especie->id), 'taxon_id' => $especie->id], $fuentes, $decision, 'reconciliado');
        DB::table('taxonomia.especimenes')->where('id', $especimen->id)->update([
            'taxonomic_notes' => ReconciliacionCientificaQa6::agregarNota($especimen->taxonomic_notes, $decision.' Fuentes: '.implode(' ', $fuentes)),
        ]);
    }

    private function ponerRaguaEnRevision(): void
    {
        $filas = DB::table('taxonomia.especimenes')->where('fila_origen_excel', 25342)
            ->where('occurrence_id', 'MEPN-INV-30203')->where('old_code', '787')->get();
        foreach ($filas as $especimen) {
            if (! ReconciliacionCientificaQa6::conflictoRagua((array) $especimen)) continue;
            $original = (array) $especimen;
            $original['referencia_localidad'] = $especimen->localidad_id !== null
                ? (array) DB::table('taxonomia.localidades')->where('id', $especimen->localidad_id)->first() : null;
            $nota = ReconciliacionCientificaQa6::NOTA_RAGUA.' Fuente: '.ReconciliacionCientificaQa6::FUENTE_RAGUA;
            $tratamiento = ['decimal_latitude' => null, 'decimal_longitude' => null, 'localidad_id' => null,
                'estado_revision' => 'pendiente', 'publicacion' => 'excluido por falta de coordenadas públicas válidas'];
            $this->auditar('QA6-003', 'especimen', $especimen->id, $original, $tratamiento,
                [ReconciliacionCientificaQa6::FUENTE_RAGUA], $nota, 'pendiente');
            DB::table('taxonomia.especimenes')->where('id', $especimen->id)->update([
                'decimal_latitude' => null, 'decimal_longitude' => null, 'localidad_id' => null,
                'coord_verbatim' => trim($especimen->coord_verbatim ?? '') !== '' ? $especimen->coord_verbatim : '-4.0226841 / -79.194422',
                'verbatim_latitude' => $especimen->verbatim_latitude ?? '-4.0226841',
                'verbatim_longitude' => $especimen->verbatim_longitude ?? '-79.194422',
                'estado_revision' => 'pendiente',
                'motivo_revision' => ReconciliacionCientificaQa6::agregarNota($especimen->motivo_revision, $nota),
                'locality_notes' => ReconciliacionCientificaQa6::agregarNota($especimen->locality_notes, $nota),
                'specimen_notes' => ReconciliacionCientificaQa6::agregarNota($especimen->specimen_notes, $nota),
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function linaje(string $id): array
    {
        $linaje = []; $visitados = [];
        while ($id !== '' && ! isset($visitados[$id]) && count($linaje) < 30) {
            $visitados[$id] = true;
            $taxon = DB::table('taxonomia.taxones')->where('id', $id)->first();
            if ($taxon === null) break;
            $linaje[] = (array) $taxon;
            $id = $taxon->padre_id ?? '';
        }
        return $linaje;
    }

    private function auditar(string $hallazgo, string $entidad, string $id, array $original, array $tratamiento,
        array $fuentes, string $decision, string $estado): void
    {
        DB::table('taxonomia.correcciones_cientificas')->insertOrIgnore([
            'id' => (string) Str::uuid(), 'hallazgo' => $hallazgo, 'entidad' => $entidad, 'entidad_id' => $id,
            'original' => json_encode($original, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'tratamiento' => json_encode($tratamiento, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'fuentes' => json_encode($fuentes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'decision' => $decision, 'estado' => $estado, 'registrado_en' => now(),
        ]);
    }

    public function down(): void
    {
        // Las evidencias originales y decisiones científicas no se eliminan al revertir.
        // La reversión requiere el respaldo anterior o una decisión curatorial explícita.
    }
};
