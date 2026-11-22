<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DELETE FROM usuarios.user_roles AS anterior
            USING usuarios.user_roles AS curador
            WHERE anterior.user_id = curador.user_id
                AND anterior.rol = 'ADMIN' AND curador.rol = 'CURADOR'
            SQL);
        DB::table('usuarios.user_roles')->where('rol', 'ADMIN')->update(['rol' => 'CURADOR']);
        DB::table('usuarios.users')->where('rol', 'ADMIN')->update(['rol' => 'CURADOR']);

        // El perfil unificado tiene acceso completo a los botones del sistema.
        DB::table('usuarios.permisos_botones')->whereIn('rol', ['ADMIN', 'CURADOR'])->delete();

        DB::statement(<<<'SQL'
            UPDATE usuarios.menu_lateral AS opcion
            SET configuracion = jsonb_set(opcion.configuracion, '{roles}', (
                SELECT jsonb_agg(DISTINCT CASE WHEN rol = 'ADMIN' THEN 'CURADOR' ELSE rol END)
                FROM jsonb_array_elements_text(opcion.configuracion->'roles') AS anterior(rol)
            ))
            WHERE jsonb_exists(opcion.configuracion->'roles', 'ADMIN')
            SQL);
    }

    public function down(): void
    {
        // Una vez unificada la membresía, ya no distingue los perfiles anteriores.
    }
};
