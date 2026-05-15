<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestamos.actas_prestamo', function (Blueprint $table): void {
            $table->text('motivo_devolucion')->nullable()->after('pdf_firmado_ruta');
        });
    }

    public function down(): void
    {
        Schema::table('prestamos.actas_prestamo', function (Blueprint $table): void {
            $table->dropColumn('motivo_devolucion');
        });
    }
};
