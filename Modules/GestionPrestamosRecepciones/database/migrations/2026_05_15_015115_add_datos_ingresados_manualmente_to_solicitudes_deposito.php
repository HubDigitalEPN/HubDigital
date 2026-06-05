<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('recepciones.solicitudes_deposito', 'datos_ingresados_manualmente')) {
            return;
        }

        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table) {
            $table->jsonb('datos_ingresados_manualmente')->default('[]')->after('datos_faltantes');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('recepciones.solicitudes_deposito', 'datos_ingresados_manualmente')) {
            return;
        }

        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table) {
            $table->dropColumn('datos_ingresados_manualmente');
        });
    }
};
