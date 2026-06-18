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
            $table->string('curador_responsable')->nullable();
            $table->timestamp('aprobada_en')->nullable();
            $table->timestamp('rechazada_en')->nullable();
            $table->string('codigo_qr')->nullable()->unique();
            $table->text('comentario_curador')->nullable();
            $table->string('prioridad')->default('Normal');
            $table->jsonb('acta_transferencia_dominio')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->dropColumn([
                'curador_responsable',
                'aprobada_en',
                'rechazada_en',
                'codigo_qr',
                'comentario_curador',
                'prioridad',
                'acta_transferencia_dominio',
            ]);
        });
    }
};
