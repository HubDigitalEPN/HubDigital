<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('prestamos.actas_prestamo', 'documento_identidad_ruta')) {
            return;
        }

        Schema::table('prestamos.actas_prestamo', function (Blueprint $table): void {
            $table->string('documento_identidad_ruta')->nullable()->after('pdf_firmado_ruta');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('prestamos.actas_prestamo', 'documento_identidad_ruta')) {
            return;
        }

        Schema::table('prestamos.actas_prestamo', function (Blueprint $table): void {
            $table->dropColumn('documento_identidad_ruta');
        });
    }
};
