<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS usuarios');

        if (! Schema::hasTable('usuarios.user_roles')) {
            Schema::create('usuarios.user_roles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('rol');
                $table->timestamps();

                $table->foreign('user_id')
                    ->references('id')
                    ->on('usuarios.users')
                    ->cascadeOnDelete();

                $table->unique(['user_id', 'rol']);
            });
        }

        // Backfill: cada usuario existente conserva su rol escalar como membresía.
        // insertOrIgnore + unique(user_id, rol) hacen el backfill idempotente.
        DB::table('usuarios.users')
            ->whereNotNull('rol')
            ->orderBy('id')
            ->each(function ($user) {
                DB::table('usuarios.user_roles')->insertOrIgnore([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'rol' => $user->rol,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // Solo se elimina el pivote; la columna usuarios.users.rol permanece
        // intacta como rol primario, por lo que el rollback no pierde datos.
        Schema::dropIfExists('usuarios.user_roles');
    }
};
