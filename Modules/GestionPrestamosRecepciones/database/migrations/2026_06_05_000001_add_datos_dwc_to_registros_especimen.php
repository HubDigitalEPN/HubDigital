<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega la columna datos_dwc a recepciones.registros_especimen para
 * persistir el registro DwC completo (normalizado) del Excel del depositante.
 *
 * Permite que el curador vea todos los campos DwC del espécimen al revisar
 * la solicitud, y que fluyan limpios a taxonomia.especimenes al aceptar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones.registros_especimen', function (Blueprint $table): void {
            $table->jsonb('datos_dwc')->nullable()->after('motivo_justificacion');
            $table->jsonb('normalizaciones')->nullable()->after('datos_dwc')
                ->comment('Campos que fueron normalizados: [{campo, original, normalizado}]');
        });
    }

    public function down(): void
    {
        Schema::table('recepciones.registros_especimen', function (Blueprint $table): void {
            $table->dropColumn(['datos_dwc', 'normalizaciones']);
        });
    }
};
