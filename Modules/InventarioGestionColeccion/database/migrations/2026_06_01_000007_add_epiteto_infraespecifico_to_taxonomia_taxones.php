<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxonomia.taxones', function (Blueprint $table): void {
            if (! Schema::hasColumn('taxonomia.taxones', 'epiteto_infraespecifico')) {
                $table->string('epiteto_infraespecifico', 120)->nullable()->after('anio_descripcion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('taxonomia.taxones', function (Blueprint $table): void {
            if (Schema::hasColumn('taxonomia.taxones', 'epiteto_infraespecifico')) {
                $table->dropColumn('epiteto_infraespecifico');
            }
        });
    }
};
