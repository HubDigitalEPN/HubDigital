<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table) {
            $table->unsignedInteger('nro_individuos')->nullable()->after('firmas_electronicas');
            $table->unsignedInteger('nro_morfoespecies')->nullable()->after('nro_individuos');
            $table->unsignedInteger('nro_lotes')->nullable()->after('nro_morfoespecies');
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table) {
            $table->dropColumn(['nro_individuos', 'nro_morfoespecies', 'nro_lotes']);
        });
    }
};
