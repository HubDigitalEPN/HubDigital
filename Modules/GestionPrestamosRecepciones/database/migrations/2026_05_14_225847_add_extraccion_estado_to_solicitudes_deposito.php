<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('recepciones.solicitudes_deposito', 'extraccion_estado')) {
            return;
        }

        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->string('extraccion_estado')->nullable()->default(null)->after('datos_faltantes');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('recepciones.solicitudes_deposito', 'extraccion_estado')) {
            return;
        }

        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->dropColumn('extraccion_estado');
        });
    }
};
