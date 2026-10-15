<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->text('localidad')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Conservar texto evita truncar localidades ya guardadas al retirar esta release.
    }
};
