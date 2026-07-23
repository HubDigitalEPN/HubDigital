<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina la firma criptográfica automática del curador: la tabla de certificados
 * y la columna de metadata de firma. Se conserva `pdf_firmado_curador_ruta`, que
 * ahora guarda el PDF que el curador firma manualmente (canvas o carga de archivo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('prestamos.certificados_curador');

        if (Schema::hasColumn('prestamos.actas_prestamo', 'firma_curador_metadata')) {
            Schema::table('prestamos.actas_prestamo', function (Blueprint $table): void {
                $table->dropColumn('firma_curador_metadata');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('prestamos.actas_prestamo', 'firma_curador_metadata')) {
            Schema::table('prestamos.actas_prestamo', function (Blueprint $table): void {
                $table->json('firma_curador_metadata')->nullable()->after('pdf_firmado_curador_ruta');
            });
        }

        if (! Schema::hasTable('prestamos.certificados_curador')) {
            Schema::create('prestamos.certificados_curador', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('curador_id')->unique();
                $table->text('p12_cifrado');
                $table->text('contrasenia_cifrada');
                $table->string('common_name');
                $table->string('serial');
                $table->timestamp('valido_hasta');
                $table->timestamps();
            });
        }
    }
};
