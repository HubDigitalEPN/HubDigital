<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios.users', function (Blueprint $table) {
            // Datos profesionales del depositante, usados en el Acta recepción-depósito.
            // Nullable: los usuarios existentes y los solicitantes (préstamos) no los declaran.
            $table->string('cargo')->nullable()->after('last_name');
            $table->string('institucion')->nullable()->after('cargo');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios.users', function (Blueprint $table) {
            $table->dropColumn(['cargo', 'institucion']);
        });
    }
};
