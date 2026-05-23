<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->smallInteger('paso_actual')->default(1)->after('estado');
            $table->jsonb('documentos_cargados')->default('{}')->after('documentos_adjuntos');
            $table->jsonb('nombres_archivos_originales')->default('{}')->after('documentos_cargados');
            $table->jsonb('documentos_requeridos')->default('[]')->after('nombres_archivos_originales');
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->dropColumn(['paso_actual', 'documentos_cargados', 'nombres_archivos_originales', 'documentos_requeridos']);
        });
    }
};
