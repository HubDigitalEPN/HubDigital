<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('recepciones.solicitudes_deposito', 'firmas_electronicas')) {
            return;
        }

        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table) {
            $table->jsonb('firmas_electronicas')->default('{}')->after('documentos_procesados');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('recepciones.solicitudes_deposito', 'firmas_electronicas')) {
            return;
        }

        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table) {
            $table->dropColumn('firmas_electronicas');
        });
    }
};
