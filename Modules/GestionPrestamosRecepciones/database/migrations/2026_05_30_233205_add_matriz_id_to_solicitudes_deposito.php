<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('recepciones.solicitudes_deposito', 'matriz_id')) {
            return;
        }

        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->uuid('matriz_id')->nullable()->after('documentos_requeridos');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('recepciones.solicitudes_deposito', 'matriz_id')) {
            return;
        }

        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->dropColumn('matriz_id');
        });
    }
};
