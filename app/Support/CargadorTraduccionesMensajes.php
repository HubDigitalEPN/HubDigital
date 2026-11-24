<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;
use Illuminate\Translation\FileLoader;

final class CargadorTraduccionesMensajes implements Loader
{
    public function __construct(private readonly FileLoader $archivos) {}

    public function load($locale, $group, $namespace = null)
    {
        $lineas = $this->archivos->load($locale, $group, $namespace);
        if ($locale !== 'es' || !isset(MensajesSistema::GRUPOS_IDIOMA[$group]) || !in_array($namespace, [null, '*'], true)) {
            return $lineas;
        }

        $prefijo = 'idioma.'.$group.'.';
        foreach (app(MensajesSistema::class)->guardados() as $clave => $contenido) {
            if (str_starts_with($clave, $prefijo)) {
                Arr::set($lineas, substr($clave, strlen($prefijo)), $contenido);
            }
        }

        return $lineas;
    }

    public function addNamespace($namespace, $hint)
    {
        $this->archivos->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->archivos->addJsonPath($path);
    }

    public function addPath($path)
    {
        $this->archivos->addPath($path);
    }

    public function namespaces()
    {
        return $this->archivos->namespaces();
    }
}
