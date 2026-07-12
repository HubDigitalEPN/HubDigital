<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones.recepcion_lotes', function (Blueprint $table): void {
            // PDF del acta firmado electrónicamente (FirmaEC) por el curador y su marca de tiempo.
            $table->string('acta_firmada_ruta')->nullable()->after('acta_recepcion');
            $table->timestamp('firmada_en')->nullable()->after('acta_firmada_ruta');
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.recepcion_lotes', function (Blueprint $table): void {
            $table->dropColumn(['acta_firmada_ruta', 'firmada_en']);
        });
    }
};
