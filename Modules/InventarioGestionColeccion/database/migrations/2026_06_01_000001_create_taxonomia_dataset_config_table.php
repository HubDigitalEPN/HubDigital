<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('taxonomia.dataset_config')) {
            Schema::create('taxonomia.dataset_config', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('institution_code', 120);
                $table->string('collection_code', 120);
                $table->string('institution_id', 255)->nullable();
                $table->string('collection_id', 255)->nullable();
                $table->string('dataset_name', 255)->nullable();
                $table->string('owner_institution_code', 120)->nullable();
                $table->string('rights_holder', 255)->nullable();
                $table->string('access_rights', 255)->nullable();
                $table->string('license', 120)->nullable();
                $table->string('basis_of_record', 60)->default('PreservedSpecimen');
                $table->text('information_withheld')->nullable();
                $table->string('eml_contacto', 255)->nullable();
                $table->string('eml_titulo', 255)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomia.dataset_config');
    }
};
