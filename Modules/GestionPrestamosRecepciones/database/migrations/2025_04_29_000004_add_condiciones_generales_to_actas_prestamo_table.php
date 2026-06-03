<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('prestamos.actas_prestamo', 'condiciones_generales')) {
            return;
        }

        Schema::table('prestamos.actas_prestamo', function (Blueprint $table): void {
            $table->text('condiciones_generales')->nullable()->after('tipo_prestamo');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('prestamos.actas_prestamo', 'condiciones_generales')) {
            return;
        }

        Schema::table('prestamos.actas_prestamo', function (Blueprint $table): void {
            $table->dropColumn('condiciones_generales');
        });
    }
};
