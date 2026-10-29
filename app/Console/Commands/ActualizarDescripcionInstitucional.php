<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\PortalCopy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ActualizarDescripcionInstitucional extends Command
{
    protected $signature = 'portal:descripcion-institucional';
    protected $description = 'Aplica únicamente el párrafo institucional aprobado a la portada.';

    public function handle(): int
    {
        $texto = (string) config('portal-copy.inicio.hero_descripcion');
        DB::transaction(static function () use ($texto): void {
            $fila = DB::table('usuarios.textos_portal')->where('clave', 'inicio.hero_descripcion');
            if ($fila->exists()) $fila->update(['contenido' => $texto, 'updated_at' => now()]);
            else DB::table('usuarios.textos_portal')->insert(['clave' => 'inicio.hero_descripcion',
                'contenido' => $texto, 'created_at' => now(), 'updated_at' => now()]);
        });
        PortalCopy::forget();
        $this->info('Descripción institucional actualizada.');
        return self::SUCCESS;
    }
}
