<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iot.cajas', function (Blueprint $table): void {
            if (! Schema::hasColumn('iot.cajas', 'es_especial')) {
                $table->boolean('es_especial')->default(false)->after('codigo');
            }

            if (! Schema::hasColumn('iot.cajas', 'observacion')) {
                $table->text('observacion')->nullable()->after('es_especial');
            }

            if (! Schema::hasColumn('iot.cajas', 'clasificacion_taxonomica')) {
                $table->jsonb('clasificacion_taxonomica')->nullable()->after('observacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('iot.cajas', function (Blueprint $table): void {
            $table->dropColumn(['es_especial', 'observacion', 'clasificacion_taxonomica']);
        });
    }
};
