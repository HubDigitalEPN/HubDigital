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
        Schema::table('divulgacion.chat_nodes', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->uuid('published_by')->nullable();
        });
        DB::table('divulgacion.chat_nodes')->where('status', 'published')->update(['published_at' => now()]);
        Schema::create('divulgacion.chat_node_drafts', function (Blueprint $table): void {
            $table->unsignedBigInteger('node_id')->primary();
            $table->foreign('node_id')->references('id')->on('divulgacion.chat_nodes')->cascadeOnDelete();
            $table->text('payload');
            $table->uuid('edited_by')->nullable();
            $table->timestamp('updated_at');
        });
        Schema::create('divulgacion.chat_node_revisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('node_id');
            $table->foreign('node_id')->references('id')->on('divulgacion.chat_nodes')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('event', 24);
            $table->uuid('actor_id')->nullable();
            $table->text('payload');
            $table->timestamp('created_at');
            $table->index(['node_id', 'created_at']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Los borradores y revisiones institucionales no se eliminan automáticamente.');
    }
};
