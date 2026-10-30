<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $clave = \App\Services\PaginaLaboratorio::CLAVE;
            $fila = DB::table('usuarios.textos_portal')->where('clave', $clave)->lockForUpdate()->first();
            if (!$fila) return;
            $contenido = json_decode($fila->contenido, true);
            $anteriores = [
                'adrian_bio' => ['Biólogo entomólogo.', 'Biólogo y entomólogo dedicado al estudio de las hormigas (Formicidae). Investiga taxonomía, sistemática, biogeografía, filogenia y evolución. Los especímenes de las colecciones apoyan sus estudios sobre diversidad, identificación de especies y parentesco evolutivo.'],
                'vladimir_bio' => ['Taxonomía de invertebrados terrestres y de agua dulce, crustáceos y moluscos. Macrofotografía y fotomicrografía.', 'Biólogo, entomólogo y fotógrafo de naturaleza con formación en conservación. Investiga invertebrados terrestres y de agua dulce, especialmente crustáceos continentales y moluscos terrestres. Documenta su diversidad mediante macrofotografía y fotomicrografía. Contacto: vladimir.carvajal@epn.edu.ec · 022-976-300, ext. 6004.'],
                'david_bio' => ['Ecología de ecosistemas, cadenas tróficas del suelo, ciclos de nutrientes, mirmecología y entomología forense.', 'Biólogo y entomólogo que estudia ecología de ecosistemas, redes tróficas del suelo, ciclos de nutrientes y cambio global. Trabaja también en comunidades y sistemática de hormigas neotropicales y entomología forense. Contacto: david.donoso@epn.edu.ec · 022-976-300, ext. 6002.'],
            ];
            $cambio = false;
            foreach ($anteriores as $campo => $valores) {
                if (!in_array($contenido['textos'][$campo] ?? null, $valores, true)) continue;
                $contenido['textos'][$campo] = config('pagina-laboratorio.textos.'.$campo);
                $cambio = true;
            }
            if (!$cambio) return;
            DB::table('usuarios.textos_portal')->where('clave', $clave)->update(['contenido' => json_encode($contenido, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
            \App\Support\PortalCopy::forget();
        });
    }

    public function down(): void {}
};
