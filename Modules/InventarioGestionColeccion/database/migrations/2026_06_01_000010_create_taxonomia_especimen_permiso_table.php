<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taxonomia.especimen_permiso')) {
            Schema::create('taxonomia.especimen_permiso', function (Blueprint $table): void {
                $table->uuid('especimen_id');
                $table->uuid('permiso_id');
                $table->timestamps();

                $table->primary(['especimen_id', 'permiso_id']);

                $table->foreign('especimen_id')
                    ->references('id')
                    ->on('taxonomia.especimenes')
                    ->cascadeOnDelete();

                $table->foreign('permiso_id')
                    ->references('id')
                    ->on('taxonomia.permisos')
                    ->cascadeOnDelete();

                $table->index('permiso_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomia.especimen_permiso');
    }
};
