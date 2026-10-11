<?php

use Illuminate\Database\Migrations\Migration;
use Modules\CatalogoPublico\Infrastructure\FichasTaxonomicas;

return new class extends Migration
{
    public function up(): void { FichasTaxonomicas::crearTabla(); }
    public function down(): void { \Illuminate\Support\Facades\Schema::dropIfExists('divulgacion.fichas_taxonomicas'); }
};
