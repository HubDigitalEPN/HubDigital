<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iot.alertas_ubicacion', function (Blueprint $table): void {
            if (! Schema::hasColumn('iot.alertas_ubicacion', 'motivo_resolucion')) {
                $table->text('motivo_resolucion')->nullable()->after('datos_contexto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('iot.alertas_ubicacion', function (Blueprint $table): void {
            $table->dropColumn('motivo_resolucion');
        });
    }
};
