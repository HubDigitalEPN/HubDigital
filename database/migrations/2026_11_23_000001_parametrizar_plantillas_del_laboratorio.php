<?php

declare(strict_types=1);

use App\Support\PlantillasLaboratorio;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\GestionPrestamosRecepciones\Domain\Services\EsquemaMatriz;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ConfiguracionPlantillaCatalogo;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $catalogo = DB::table('taxonomia.configuracion_plantilla_catalogo')->where('clave', 'catalogo');
            $filaCatalogo = (clone $catalogo)->lockForUpdate()->first();
            if ($filaCatalogo !== null) {
                $definicion = PlantillasLaboratorio::catalogo(
                    json_decode($filaCatalogo->definicion, true, 512, JSON_THROW_ON_ERROR),
                    ConfiguracionPlantillaCatalogo::anterior(),
                );
                ConfiguracionPlantillaCatalogo::validar($definicion);
                $catalogo->update(['definicion' => json_encode($definicion, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            }

            $tablaDepositos = DB::table('recepciones.configuracion_matriz');
            $depositos = (clone $tablaDepositos)->where('clave', 'depositos');
            $filaDepositos = (clone $depositos)->lockForUpdate()->first();
            $ruta = base_path('Modules/GestionPrestamosRecepciones/database/data');
            $definicion = PlantillasLaboratorio::depositantes(
                $filaDepositos === null ? null : json_decode($filaDepositos->definicion, true, 512, JSON_THROW_ON_ERROR),
                json_decode(file_get_contents($ruta.'/matriz-deposito-v2.json'), true, 512, JSON_THROW_ON_ERROR),
                json_decode(file_get_contents($ruta.'/matriz-depositantes-v3.json'), true, 512, JSON_THROW_ON_ERROR),
            );
            EsquemaMatriz::mapa($definicion['columnas']);
            $datos = ['definicion' => json_encode($definicion, JSON_THROW_ON_ERROR), 'updated_at' => now()];
            if ($filaDepositos === null) {
                $tablaDepositos->insert(['clave' => 'depositos', 'created_at' => now()] + $datos);
            } else {
                $depositos->update($datos);
            }
        });
    }

    public function down(): void
    {
        // Las definiciones son editables. Revertir sus datos descartaría cambios curatoriales posteriores.
    }
};
