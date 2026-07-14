<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('taxonomia.visitantes') && ! Schema::hasColumn('taxonomia.visitantes', 'puede_reubicar')) {
            Schema::table('taxonomia.visitantes', function (Blueprint $table): void {
                $table->boolean('puede_reubicar')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('taxonomia.visitantes', 'puede_reubicar')) {
            Schema::table('taxonomia.visitantes', function (Blueprint $table): void {
                $table->dropColumn('puede_reubicar');
            });
        }
    }
};
