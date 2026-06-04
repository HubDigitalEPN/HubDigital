<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('recepciones.solicitudes_deposito')
            ->where('estado', 'Retenida para Asesoría Curatorial')
            ->update(['estado' => 'Pausada para Asesoría']);
    }

    public function down(): void
    {
        DB::table('recepciones.solicitudes_deposito')
            ->where('estado', 'Pausada para Asesoría')
            ->update(['estado' => 'Retenida para Asesoría Curatorial']);
    }
};
