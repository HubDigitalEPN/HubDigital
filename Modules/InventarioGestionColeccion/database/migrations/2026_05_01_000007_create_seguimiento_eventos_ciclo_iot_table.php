<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('iot.eventos_ciclo_iot')) {
            Schema::create('iot.eventos_ciclo_iot', function (Blueprint $table): void {
                $table->id();
                $table->string('tipo_agregado', 100);
                $table->string('agregado_id', 36);
                $table->string('tipo_evento', 100);
                $table->unsignedSmallInteger('version_evento');
                $table->json('datos')->default('{}');
                $table->string('actor_id')->nullable();
                $table->string('actor_rol', 30);
                $table->dateTime('ocurrido_en');

                $table->index(['agregado_id', 'tipo_evento']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('iot.eventos_ciclo_iot');
    }
};
