<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divulgacion.chat_trace', function (Blueprint $table): void {
            $table->uuid('message_id')->primary();
            $table->char('session_hash', 64);
            $table->string('source', 32);
            $table->string('intent', 100)->nullable();
            $table->unsignedBigInteger('node_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('catalog_action', 100)->nullable();
            $table->text('public_filters')->nullable();
            $table->string('confidence_level', 16)->nullable();
            $table->float('confidence_value')->nullable();
            $table->float('processing_ms');
            $table->timestamp('created_at');
            $table->index('created_at');
            $table->index('session_hash');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La trazabilidad requiere una decisión explícita antes de eliminarse.');
    }
};
