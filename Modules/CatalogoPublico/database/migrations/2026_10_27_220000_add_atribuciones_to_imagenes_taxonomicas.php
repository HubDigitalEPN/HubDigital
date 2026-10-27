<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divulgacion.imagenes_taxonomicas', function (Blueprint $table): void {
            $table->string('photographer')->nullable();
            $table->string('license')->nullable();
            $table->string('license_holder')->nullable();
            $table->string('license_institution')->nullable();
            $table->string('contact')->nullable();
            $table->string('view', 80)->nullable();
        });
        // La autoría y la licencia histórica estampadas por HubDigital son conocidas.
        // Titular, institución y contacto se dejan vacíos hasta su edición explícita.
        DB::table('divulgacion.imagenes_taxonomicas')->update(['photographer' => DB::raw('autor_nombre_completo')]);
        DB::table('divulgacion.imagenes_taxonomicas')->where('marca_agua_aplicada', true)->update(['license' => 'CC BY 4.0']);
    }

    public function down(): void
    {
        Schema::table('divulgacion.imagenes_taxonomicas', function (Blueprint $table): void {
            $table->dropColumn(['photographer', 'license', 'license_holder', 'license_institution', 'contact', 'view']);
        });
    }
};
