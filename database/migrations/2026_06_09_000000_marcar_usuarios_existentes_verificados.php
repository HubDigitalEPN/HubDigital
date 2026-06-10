<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Marca como verificadas las cuentas que ya existían antes de activar la
     * verificación obligatoria por correo, evitando que usuarios reales en uso
     * queden bloqueados. No reinicia ni elimina datos: solo rellena la columna
     * email_verified_at cuando está vacía. Los registros creados después de
     * esta migración nacerán sin verificar y seguirán el flujo normal.
     */
    public function up(): void
    {
        DB::table('usuarios.users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Irreversible por diseño: no es posible distinguir qué cuentas fueron
        // verificadas manualmente por el usuario de las marcadas aquí, así que
        // no revertimos para no invalidar verificaciones legítimas.
    }
};
