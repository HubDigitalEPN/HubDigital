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
        Schema::create('divulgacion.chat_ranking_versions', function (Blueprint $table): void {
            $table->id();
            $table->text('settings');
            $table->uuid('actor_id')->nullable();
            $table->string('comment', 240)->nullable();
            $table->timestamp('created_at');
        });
        $current = DB::table('divulgacion.chat_settings')->where('key', 'ranking')->value('value');
        if ($current !== null) {
            DB::table('divulgacion.chat_ranking_versions')->insert([
                'settings' => $current, 'comment' => 'Configuración anterior a las versiones', 'created_at' => now(),
            ]);
        }
        Schema::table('divulgacion.chat_metrics_daily', function (Blueprint $table): void {
            foreach (['high', 'medium', 'low', 'clarifications', 'clarifications_resolved', 'reformulated'] as $column) {
                $table->unsignedInteger($column)->default(0);
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Las versiones y métricas del asistente no se eliminan automáticamente.');
    }
};
