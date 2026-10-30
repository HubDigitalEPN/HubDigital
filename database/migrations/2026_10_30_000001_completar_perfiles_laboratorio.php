<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $fila = DB::table('usuarios.textos_portal')->where('clave', 'pagina_laboratorio.contenido')->lockForUpdate()->first();
            if (!$fila) return;
            $contenido = json_decode($fila->contenido, true, flags: JSON_THROW_ON_ERROR);
            $anteriores = [
                'personal_descripcion' => 'Profesionales y colaboradores que investigan, gestionan y conservan la diversidad de invertebrados del Ecuador.',
                'adrian_bio' => 'Biólogo entomólogo.',
                'adrian_especialidad' => 'Hormigas (Formicidae), taxonomía, sistemática, biogeografía, filogenia y evolución.',
                'vladimir_bio' => 'Taxonomía de invertebrados terrestres y de agua dulce, crustáceos y moluscos. Macrofotografía y fotomicrografía.',
                'david_bio' => 'Ecología de ecosistemas, cadenas tróficas del suelo, ciclos de nutrientes, mirmecología y entomología forense.',
            ];
            $cambio = false;
            foreach ($anteriores as $clave => $anterior) {
                if (($contenido['textos'][$clave] ?? null) !== $anterior) continue;
                $contenido['textos'][$clave] = config('pagina-laboratorio.textos.'.$clave);
                $cambio = true;
            }
            if ($cambio) DB::table('usuarios.textos_portal')->where('clave', $fila->clave)->update([
                'contenido' => json_encode($contenido, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'updated_at' => now(),
            ]);
        });
        \App\Support\PortalCopy::forget();
    }

    public function down(): void {}
};
