<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('divulgacion.especimenes_divulgables')) {
            return;
        }

        Schema::create('divulgacion.especimenes_divulgables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('especimen_id')->unique();
            $table->boolean('occurrence_id_visible')->default(true);
            $table->boolean('scientific_name_visible')->default(true);
            $table->boolean('individual_count_visible')->default(true);
            $table->boolean('type_status_visible')->default(true);
            $table->boolean('type_notes_visible')->default(true);
            $table->boolean('specimen_notes_visible')->default(true);
            $table->boolean('sampling_protocol_visible')->default(true);
            $table->boolean('recorded_by_visible')->default(true);
            $table->boolean('occurrence_status_visible')->default(true);
            $table->boolean('family_visible')->default(true);
            $table->boolean('genus_visible')->default(true);
            $table->boolean('country_visible')->default(true);
            $table->boolean('locality_name_visible')->default(true);
            $table->boolean('decimal_latitude_visible')->default(true);
            $table->boolean('decimal_longitude_visible')->default(true);
            $table->timestamps();

            $table->foreign('especimen_id')
                ->references('id')
                ->on('taxonomia.especimenes')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('divulgacion.especimenes_divulgables');
    }
};
