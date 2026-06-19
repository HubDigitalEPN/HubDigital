<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios.users', function (Blueprint $table) {
            $table->string('first_name')->after('id')->default('');
            $table->string('last_name')->after('first_name')->default('');
        });

        // Migrar datos existentes: primera palabra → first_name, resto → last_name
        DB::table('usuarios.users')->orderBy('id')->each(function ($user) {
            $parts = explode(' ', trim($user->name), 2);

            DB::table('usuarios.users')
                ->where('id', $user->id)
                ->update([
                    'first_name' => $parts[0],
                    'last_name' => $parts[1] ?? '',
                ]);
        });

        Schema::table('usuarios.users', function (Blueprint $table) {
            $table->string('first_name')->nullable(false)->default(null)->change();
            $table->string('last_name')->nullable(false)->default(null)->change();
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios.users', function (Blueprint $table) {
            $table->string('name')->after('id')->default('');
        });

        DB::table('usuarios.users')->orderBy('id')->each(function ($user) {
            DB::table('usuarios.users')
                ->where('id', $user->id)
                ->update([
                    'name' => trim($user->first_name.' '.$user->last_name),
                ]);
        });

        Schema::table('usuarios.users', function (Blueprint $table) {
            $table->string('name')->nullable(false)->default(null)->change();
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
