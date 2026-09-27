<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divulgacion.chat_metrics_daily', function (Blueprint $table): void {
            $table->unsignedInteger('confidence_observations')->default(0);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La analítica del asistente no se elimina automáticamente.');
    }
};
