<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::selectOne("SELECT 1 FROM information_schema.columns WHERE table_schema = 'divulgacion' AND table_name = 'especimenes' AND column_name = 'phylum'")) {
            return;
        }

        Schema::table('divulgacion.especimenes', function (Blueprint $table): void {
            $table->string('phylum')->nullable()->after('genus');
            $table->string('class')->nullable()->after('phylum');
            $table->string('order')->nullable()->after('class');
            $table->string('specific_epithet')->nullable()->after('order');
        });
    }

    public function down(): void
    {
        Schema::table('divulgacion.especimenes', function (Blueprint $table): void {
            $table->dropColumn(['phylum', 'class', 'order', 'specific_epithet']);
        });
    }
};
