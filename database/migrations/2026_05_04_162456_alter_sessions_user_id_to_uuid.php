<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sessions') && DB::selectOne("SELECT data_type FROM information_schema.columns WHERE table_name='sessions' AND column_name='user_id'")?->data_type !== 'uuid') {
            DB::statement('ALTER TABLE sessions ALTER COLUMN user_id TYPE uuid USING NULL');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sessions ALTER COLUMN user_id TYPE bigint USING NULL');
    }
};
