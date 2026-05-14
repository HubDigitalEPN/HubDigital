<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxonomia.taxones', function (Blueprint $table): void {
            $table->foreign('padre_id')
                ->references('id')
                ->on('taxonomia.taxones')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('taxonomia.taxones', function (Blueprint $table): void {
            $table->dropForeign(['padre_id']);
        });
    }
};
