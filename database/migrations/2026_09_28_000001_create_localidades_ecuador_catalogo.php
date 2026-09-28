<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recepciones.localidades_ecuador_catalogo', function (Blueprint $table): void {
            $table->string('codigo', 64)->primary();
            $table->string('provincia_codigo', 2)->index();
            $table->string('nombre', 254);
            $table->string('canton', 160)->default('');
            $table->string('parroquia', 254)->default('');
            $table->string('busqueda', 700);
            $table->string('fuente', 100);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['provincia_codigo', 'activo', 'nombre'], 'localidades_provincia_activas');
        });
        $datos = json_decode(file_get_contents(resource_path('data/ecuador-localidades-inec.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach (array_chunk($datos['localidades'], 500) as $lote) {
            $filas = array_map(static fn (array $fila): array => [...$fila, 'activo' => true,
                'created_at' => now(), 'updated_at' => now()], $lote);
            DB::table('recepciones.localidades_ecuador_catalogo')->insert($filas);
        }
        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->string('localidad_origen_codigo', 64)->nullable();
            $table->jsonb('validacion_previa_documentos')->nullable();
            $table->foreign('localidad_origen_codigo', 'solicitud_localidad_origen_fk')
                ->references('codigo')->on('recepciones.localidades_ecuador_catalogo')->restrictOnDelete();
            $table->index('localidad_origen_codigo');
        });
        if (Schema::hasTable('usuarios.textos_wizard')) {
            foreach ([
                'envio.certificado_explicacion' => 'El certificado se abre localmente en un proceso aislado de tu navegador.',
                'envio.clave_explicacion' => 'La contraseña nunca se transmite ni se almacena.',
            ] as $clave => $antiguo) {
                DB::table('usuarios.textos_wizard')->where('clave', $clave)->where('contenido', $antiguo)
                    ->update(['contenido' => config('wizard-copy.'.$clave), 'updated_at' => now()]);
            }
            \App\Support\WizardCopy::forget();
        }
        // El cantón histórico se conserva. Una localidad no puede inferirse de un cantón.
    }

    public function down(): void
    {
        Schema::table('recepciones.solicitudes_deposito', function (Blueprint $table): void {
            $table->dropForeign('solicitud_localidad_origen_fk');
            $table->dropColumn('localidad_origen_codigo');
            $table->dropColumn('validacion_previa_documentos');
        });
        Schema::dropIfExists('recepciones.localidades_ecuador_catalogo');
    }
};
