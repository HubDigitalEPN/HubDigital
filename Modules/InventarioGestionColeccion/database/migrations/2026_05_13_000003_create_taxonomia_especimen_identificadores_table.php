<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taxonomia.especimen_identificadores')) {
            Schema::create('taxonomia.especimen_identificadores', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('especimen_id');
                $table->string('tipo', 80);
                $table->string('valor', 255);
                $table->timestamps();

                $table->foreign('especimen_id')
                    ->references('id')
                    ->on('taxonomia.especimenes')
                    ->cascadeOnDelete();

                $table->unique(['especimen_id', 'tipo', 'valor']);
                $table->index(['tipo', 'valor']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomia.especimen_identificadores');
    }
};
