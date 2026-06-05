<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxonomia.taxones', function (Blueprint $table): void {
            $table->string('autor', 255)->nullable()->change();
            $table->unsignedSmallInteger('anio_descripcion')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('taxonomia.taxones', function (Blueprint $table): void {
            $table->string('autor', 255)->nullable(false)->change();
            $table->unsignedSmallInteger('anio_descripcion')->nullable(false)->change();
        });
    }
};
