<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const FK_NAME = 'taxonomia_taxones_padre_id_foreign';

    public function up(): void
    {
        if ($this->fkExists()) {
            return;
        }

        Schema::table('taxonomia.taxones', function (Blueprint $table): void {
            $table->foreign('padre_id')
                ->references('id')
                ->on('taxonomia.taxones')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! $this->fkExists()) {
            return;
        }

        Schema::table('taxonomia.taxones', function (Blueprint $table): void {
            $table->dropForeign(['padre_id']);
        });
    }

    private function fkExists(): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM pg_constraint WHERE conname = ? LIMIT 1',
            [self::FK_NAME]
        ) !== null;
    }
};
