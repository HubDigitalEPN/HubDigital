<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('recepciones.registros_especimen')
            ->where('motivo_justificacion', 'Es una Especie Nueva')
            ->update(['motivo_justificacion' => 'Es una especie nueva']);
    }

    public function down(): void
    {
        DB::table('recepciones.registros_especimen')
            ->where('motivo_justificacion', 'Es una especie nueva')
            ->update(['motivo_justificacion' => 'Es una Especie Nueva']);
    }
};
