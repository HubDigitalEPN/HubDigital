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

    public function guardar(string $contenido, string $nombreOriginal): ArchivoImagen
    {
        $ruta = self::CARPETA.'/'.Str::uuid().'_'.$this->sanitizar($nombreOriginal);

        Storage::disk(self::DISCO)->put($ruta, $contenido);

        return ArchivoImagen::crear(
            nombreOriginal: $nombreOriginal,
            ruta: $ruta,
            disco: self::DISCO,
        );
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
