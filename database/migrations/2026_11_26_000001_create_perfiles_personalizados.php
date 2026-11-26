<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios.perfiles_personalizados', function (Blueprint $table): void {
            $table->string('codigo', 33)->primary();
            $table->string('nombre', 80);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX perfiles_personalizados_nombre_unico ON usuarios.perfiles_personalizados (lower(nombre))');
    }

    public function down(): void
    {
        if (DB::table('usuarios.perfiles_personalizados')->exists()) {
            throw new LogicException('El registro contiene perfiles personalizados. Conserva sus definiciones al revertir la aplicación.');
        }

        Schema::dropIfExists('usuarios.perfiles_personalizados');
    }
};
