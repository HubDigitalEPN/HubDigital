<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taxonomia.codigos_qr')) {
            Schema::create('taxonomia.codigos_qr', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('especimen_id')->unique();
                $table->string('payload', 64)->unique();
                $table->timestamps();

                $table->foreign('especimen_id')
                    ->references('id')
                    ->on('taxonomia.especimenes')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomia.codigos_qr');
    }
};
