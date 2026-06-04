<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('recepciones.solicitudes_deposito', 'documentos_procesados')) {
            return;
        }

        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->jsonb('documentos_procesados')->default('[]')->after('extraccion_estado');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('recepciones.solicitudes_deposito', 'documentos_procesados')) {
            return;
        }

        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->dropColumn('documentos_procesados');
        });
    }
};
