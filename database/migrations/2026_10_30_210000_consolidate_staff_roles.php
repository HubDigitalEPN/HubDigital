<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE usuarios.users ALTER COLUMN rol SET DEFAULT 'DEPOSITANTE'");
        // Conserva usuarios, expedientes y membresías al reunir los perfiles históricos.
        foreach (['PRESTAMISTA' => 'DEPOSITANTE', 'RECEPTOR' => 'AYUDANTE_CURADOR'] as $anterior => $nuevo) {
            foreach (DB::table('usuarios.user_roles')->where('rol', $anterior)->get() as $membresia) {
                DB::table('usuarios.user_roles')->insertOrIgnore([
                    'id' => (string) Str::uuid(), 'user_id' => $membresia->user_id, 'rol' => $nuevo,
                    'created_at' => $membresia->created_at, 'updated_at' => $membresia->updated_at,
                ]);
            }
            DB::table('usuarios.user_roles')->where('rol', $anterior)->delete();
            DB::table('usuarios.users')->where('rol', $anterior)->update(['rol' => $nuevo]);
            DB::table('usuarios.permisos_botones')->where('rol', $anterior)->delete();
        }
    }

    public function down(): void
    {
        // La consolidación de membresías no se deshace: no se inventan roles previos.
    }
};
