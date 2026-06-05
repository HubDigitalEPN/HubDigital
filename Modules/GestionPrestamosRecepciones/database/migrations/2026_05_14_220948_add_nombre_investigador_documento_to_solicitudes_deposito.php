<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('recepciones.solicitudes_deposito', 'nombre_investigador_documento')) {
            return;
        }

        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->string('nombre_investigador_documento')->nullable()->after('origen_donacion');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('recepciones.solicitudes_deposito', 'nombre_investigador_documento')) {
            return;
        }

        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->dropColumn('nombre_investigador_documento');
        });
    }
};
