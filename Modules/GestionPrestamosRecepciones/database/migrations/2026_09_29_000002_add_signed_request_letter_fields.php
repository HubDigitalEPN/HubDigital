<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->string('solicitud_nombre_permiso', 200)->nullable();
            $table->string('solicitud_cedula', 20)->nullable();
            $table->string('solicitud_cargo', 200)->nullable();
            $table->string('solicitud_grupo', 250)->nullable();
            $table->string('solicitud_proyecto', 300)->nullable();
            $table->string('solicitud_institucion', 250)->nullable();
            $table->string('solicitud_correo', 250)->nullable();
            $table->string('solicitud_oficio', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->dropColumn([
                'solicitud_nombre_permiso', 'solicitud_cedula', 'solicitud_cargo',
                'solicitud_grupo', 'solicitud_proyecto', 'solicitud_institucion',
                'solicitud_correo', 'solicitud_oficio',
            ]);
        });
    }
};
