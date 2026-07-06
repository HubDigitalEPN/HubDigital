<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('prestamos.certificados_curador')) {
            return;
        }

        Schema::create('prestamos.certificados_curador', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('curador_id')->unique();
            // p12 y contraseña cifrados en reposo con Laravel Crypt (APP_KEY).
            $table->text('p12_cifrado');
            $table->text('contrasenia_cifrada');
            $table->string('common_name');
            $table->string('serial');
            $table->timestamp('valido_hasta');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos.certificados_curador');
    }
};
