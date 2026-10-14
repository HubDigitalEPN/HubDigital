<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones.firmas_r2_pendientes', function (Blueprint $table): void {
            $table->string('nombre_documento', 500)->nullable();
            $table->uuid('verificacion_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.firmas_r2_pendientes', function (Blueprint $table): void {
            $table->dropColumn(['nombre_documento', 'verificacion_id']);
        });
    }
};
