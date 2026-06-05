<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taxonomia.permisos')) {
            Schema::create('taxonomia.permisos', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('tipo', 40);
                $table->string('numero', 255)->nullable();
                $table->string('responsable', 255)->nullable();
                $table->text('detalles')->nullable();
                $table->timestamps();

                $table->index(['tipo', 'numero']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomia.permisos');
    }
};
