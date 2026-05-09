<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS divulgacion');
        if (! Schema::hasTable('divulgacion.especimenes')) {
            Schema::create('divulgacion.especimenes', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('occurrence_id')->unique();
                $table->string('scientific_name');
                $table->integer('individual_count')->default(0);
                $table->string('type_status')->nullable();
                $table->text('type_notes')->nullable();
                $table->text('specimen_notes')->nullable();
                $table->string('sampling_protocol')->nullable();
                $table->string('recorded_by')->nullable();
                $table->string('occurrence_status', 100);
                $table->string('family')->nullable();
                $table->string('genus')->nullable();
                $table->string('country')->nullable();
                $table->string('locality_name')->nullable();
                $table->decimal('decimal_latitude', 10, 7)->nullable();
                $table->decimal('decimal_longitude', 10, 7)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('divulgacion.especimenes');
    }
};
