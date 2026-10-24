<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Http\Request;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Repositories\EloquentProveedorEspecimenesParaArbol;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DescargarCatalogo
{
    public function __invoke(Request $request, string $exportacion): StreamedResponse
    {
        \App\Support\AccesoCatalogo::autorizar();
        $catalogo = app(PortalCatalogo::class);
        app()->call([$catalogo, 'boot']);
        $catalogo->prepararDescargaHttp($request->query());
        $respuesta = match ($exportacion) {
            'datos' => $catalogo->descargarDatos(),
            'resultados' => $catalogo->descargarResultados(app(EloquentProveedorEspecimenesParaArbol::class)),
            'especies' => $catalogo->descargarListaEspecies(),
            default => $catalogo->descargarAnalisis($exportacion),
        };
        // Entrega binaria HTTP: conserva la privacidad y evita Base64 de Livewire.
        $respuesta->headers->set('Cache-Control', 'private, no-store');
        $respuesta->headers->set('X-Accel-Buffering', 'no');
        return $respuesta;
    }
}
