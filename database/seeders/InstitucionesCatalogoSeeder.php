<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** IES del CES e institución del perfil bootstrap; respeta ediciones y bajas. */
final class InstitucionesCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        // https://www.ces.gob.ec/?page_id=328 (35 públicas)
        // https://www.ces.gob.ec/?page_id=5398 (8 cofinanciadas)
        // https://www.ces.gob.ec/?page_id=1540 (7 particulares)
        $instituciones = [
            '1001' => 'Escuela Politécnica Nacional',
            '1002' => 'Escuela Superior Politécnica de Chimborazo',
            '1003' => 'Escuela Superior Politécnica Agropecuaria de Manabí Manuel Félix López',
            '1005' => 'Universidad Central del Ecuador',
            '1006' => 'Universidad de Guayaquil',
            '1007' => 'Universidad de Cuenca',
            '1008' => 'Universidad Nacional de Loja',
            '1009' => 'Universidad Técnica de Manabí',
            '1010' => 'Universidad Técnica de Ambato',
            '1011' => 'Universidad Técnica de Machala',
            '1012' => 'Universidad Técnica Luis Vargas Torres de Esmeraldas',
            '1013' => 'Universidad Técnica de Babahoyo',
            '1014' => 'Universidad Técnica Estatal de Quevedo',
            '1015' => 'Universidad Técnica del Norte',
            '1016' => 'Universidad Laica Eloy Alfaro de Manabí',
            '1017' => 'Universidad Estatal de Bolívar',
            '1018' => 'Universidad Agraria del Ecuador',
            '1019' => 'Universidad Nacional de Chimborazo',
            '1020' => 'Universidad Técnica de Cotopaxi',
            '1021' => 'Escuela Superior Politécnica del Litoral',
            '1022' => 'Universidad Andina Simón Bolívar',
            '1023' => 'Universidad Estatal Península de Santa Elena',
            '1024' => 'Universidad Estatal de Milagro',
            '1025' => 'Universidad Estatal del Sur de Manabí',
            '1026' => 'Facultad Latinoamericana de Ciencias Sociales',
            '1057' => 'Instituto de Altos Estudios Nacionales',
            '1058' => 'Universidad Estatal Amazónica',
            '1068' => 'Universidad Intercultural Amawtay Wasi',
            '1074' => 'Universidad Politécnica Estatal del Carchi',
            '1079' => 'Universidad de las Fuerzas Armadas ESPE',
            '1080' => 'Universidad Regional Amazónica Ikiam',
            '1081' => 'Universidad de Investigación de Tecnología Experimental Yachay',
            '1082' => 'Universidad de las Artes',
            '1083' => 'Universidad Nacional de Educación',
            '3068' => 'Universidad de Seguridad Ciudadana y Ciencias Policiales',
            '1027' => 'Pontificia Universidad Católica del Ecuador',
            '1028' => 'Universidad Católica de Santiago de Guayaquil',
            '1029' => 'Universidad Católica de Cuenca',
            '1030' => 'Universidad Laica Vicente Rocafuerte de Guayaquil',
            '1031' => 'Universidad Técnica Particular de Loja',
            '1032' => 'Universidad UTE',
            '1033' => 'Universidad del Azuay',
            '1034' => 'Universidad Politécnica Salesiana',
            '1036' => 'Universidad Particular Internacional SEK',
            '1037' => 'Universidad de Especialidades Espíritu Santo',
            '1038' => 'Universidad San Francisco de Quito',
            '1040' => 'Universidad de las Américas',
            '1041' => 'Universidad Internacional del Ecuador',
            '1042' => 'Universidad Regional Autónoma de los Andes',
            '1044' => 'Universidad del Pacífico',
        ];

        foreach ($instituciones as $codigo => $nombre) {
            DB::table('usuarios.instituciones_catalogo')->insertOrIgnore([
                'codigo_ces' => $codigo,
                'nombre' => $nombre,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // La empresa del depositante bootstrap no es una IES y no tiene código CES.
        // Debe poder seleccionarse sin atribuirle la institución del proyecto.
        DB::table('usuarios.instituciones_catalogo')->insertOrIgnore([
            'nombre' => 'Kintiflow', 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
