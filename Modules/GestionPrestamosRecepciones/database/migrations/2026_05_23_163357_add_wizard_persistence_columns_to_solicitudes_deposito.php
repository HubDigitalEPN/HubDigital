<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            if (! Schema::hasColumn('recepciones.solicitudes_deposito', 'paso_actual')) {
                $table->smallInteger('paso_actual')->default(1)->after('estado');
            }
            if (! Schema::hasColumn('recepciones.solicitudes_deposito', 'documentos_cargados')) {
                $table->jsonb('documentos_cargados')->default('{}')->after('documentos_adjuntos');
            }
            if (! Schema::hasColumn('recepciones.solicitudes_deposito', 'nombres_archivos_originales')) {
                $table->jsonb('nombres_archivos_originales')->default('{}')->after('documentos_cargados');
            }
            if (! Schema::hasColumn('recepciones.solicitudes_deposito', 'documentos_requeridos')) {
                $table->jsonb('documentos_requeridos')->default('[]')->after('nombres_archivos_originales');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $existentes = array_values(array_filter(
                ['paso_actual', 'documentos_cargados', 'nombres_archivos_originales', 'documentos_requeridos'],
                fn (string $col) => Schema::hasColumn('recepciones.solicitudes_deposito', $col),
            ));
            if ($existentes !== []) {
                $table->dropColumn($existentes);
            }
        });
    }
};
