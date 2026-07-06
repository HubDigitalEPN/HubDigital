<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('prestamos.actas_prestamo', 'pdf_firmado_curador_ruta')) {
            return;
        }

        Schema::table('prestamos.actas_prestamo', function (Blueprint $table): void {
            $table->string('pdf_firmado_curador_ruta')->nullable()->after('validada_por');
            $table->json('firma_curador_metadata')->nullable()->after('pdf_firmado_curador_ruta');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('prestamos.actas_prestamo', 'pdf_firmado_curador_ruta')) {
            return;
        }

        Schema::table('prestamos.actas_prestamo', function (Blueprint $table): void {
            $table->dropColumn(['pdf_firmado_curador_ruta', 'firma_curador_metadata']);
        });
    }
};
