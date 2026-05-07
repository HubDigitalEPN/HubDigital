<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS iot');

        Schema::create('iot.horarios', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedTinyInteger('hora_inicio');
            $table->unsignedTinyInteger('hora_fin');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Seed default horario
        $this->seedDefaultHorario();
    }

    public function down(): void
    {
        Schema::dropIfExists('iot.horarios');
    }

    private function seedDefaultHorario(): void
    {
        $now = DB::raw('CURRENT_TIMESTAMP');

        DB::table('iot.horarios')->insert([
            'id' => (string) Str::uuid(),
            'hora_inicio' => 8,
            'hora_fin' => 18,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
