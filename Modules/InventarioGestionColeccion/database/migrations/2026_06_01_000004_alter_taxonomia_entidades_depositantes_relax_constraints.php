<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxonomia.entidades_depositantes', function (Blueprint $table): void {
            $table->string('tipo', 50)->nullable()->change();
            $table->string('contacto', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('taxonomia.entidades_depositantes', function (Blueprint $table): void {
            $table->string('tipo', 50)->nullable(false)->change();
            $table->string('contacto', 255)->nullable(false)->change();
        });
    }
};
