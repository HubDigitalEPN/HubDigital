<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\CatalogoPublico\Application\Ports\AlmacenamientoImagenesPort;
use Modules\CatalogoPublico\Domain\ValueObjects\ArchivoImagen;

final class StorageImagenesAdapter implements AlmacenamientoImagenesPort
{
    private const DISCO = 'public';

    private const CARPETA = 'divulgacion/imagenes';

    public function guardar(string $contenido, string $nombreDeseado): ArchivoImagen
    {
        $nombreFinal = $this->resolverColision($this->sanitizar($nombreDeseado));
        $ruta = self::CARPETA.'/'.$nombreFinal;

        Storage::disk(self::DISCO)->put($ruta, $contenido);

        return ArchivoImagen::crear(
            nombreOriginal: $nombreFinal,
            ruta: $ruta,
            disco: self::DISCO,
        );
    }

    private function resolverColision(string $nombre): string
    {
        $ruta = self::CARPETA.'/'.$nombre;

        if (! Storage::disk(self::DISCO)->exists($ruta)) {
            return $nombre;
        }

        $info = pathinfo($nombre);
        $base = $info['filename'];
        $ext = isset($info['extension']) ? '.'.$info['extension'] : '';
        $n = 2;

        do {
            $candidato = $base.'_'.$n.$ext;
            $n++;
        } while (Storage::disk(self::DISCO)->exists(self::CARPETA.'/'.$candidato));

        return $candidato;
    }

    public function eliminar(ArchivoImagen $archivo): void
    {
        Storage::disk($archivo->disco)->delete($archivo->ruta);
    }

    public function url(ArchivoImagen $archivo): string
    {
        return Storage::disk($archivo->disco)->url($archivo->ruta);
    }

    private function sanitizar(string $nombre): string
    {
        return Str::of($nombre)->basename()->toString();
    }
}
