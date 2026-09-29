<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('taxonomia.localidades', 'codigo_inec')) {
            Schema::table('taxonomia.localidades', function (Blueprint $table): void {
                $table->string('codigo_inec', 64)->nullable()->index();
                $table->string('referencia_inec', 100)->nullable();
                $table->foreign('codigo_inec')
                    ->references('codigo')->on('recepciones.localidades_ecuador_catalogo')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('taxonomia.localidades', function (Blueprint $table): void {
            $table->dropForeign(['codigo_inec']);
            $table->dropColumn(['codigo_inec', 'referencia_inec']);
        });
    }
};
