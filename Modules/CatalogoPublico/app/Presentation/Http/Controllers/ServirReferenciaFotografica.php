<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\Response;

/** Miniaturas públicas de la fuente abierta: origen fijo, lectura acotada y conversión almacenada. */
final class ServirReferenciaFotografica
{
    public function __invoke(string $foto, string $extension): Response
    {
        try {
            $contenido = Cache::remember('portal-referencia-webp-v1:'.$foto.':'.$extension, now()->addDays(14), static function () use ($foto, $extension): string {
                $respuesta = Http::timeout(8)->connectTimeout(3)->withOptions(['allow_redirects' => false, 'stream' => true])
                    ->get('https://inaturalist-open-data.s3.amazonaws.com/photos/'.$foto.'/medium.'.$extension);
                abort_unless($respuesta->successful() && in_array(strtolower(explode(';', $respuesta->header('Content-Type'))[0]), ['image/jpeg', 'image/png'], true), 404);
                abort_if((int) $respuesta->header('Content-Length') > 2000000, 404);
                $flujo = $respuesta->toPsrResponse()->getBody();
                $binario = '';
                try {
                    while (!$flujo->eof() && strlen($binario) <= 2000000) {
                        $fragmento = $flujo->read(min(65536, 2000001 - strlen($binario)));
                        abort_if($fragmento === '' && !$flujo->eof(), 404);
                        $binario .= $fragmento;
                    }
                } finally {
                    $flujo->close();
                }
                abort_if(strlen($binario) > 2000000, 404);
                $tamano = @getimagesizefromstring($binario);
                abort_unless($tamano && $tamano[0] <= 4096 && $tamano[1] <= 4096 && $tamano[0] * $tamano[1] <= 6000000, 404);
                return (string) ImageManager::usingDriver(new Driver)->decodeBinary($binario)
                    ->scaleDown(width: 480, height: 360)->encode(new WebpEncoder(quality: 76, strip: true));
            });
        } catch (\Throwable $error) {
            if ($error instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) throw $error;
            abort(404);
        }
        return response($contenido, 200, ['Content-Type' => 'image/webp', 'Cache-Control' => 'public, max-age=1209600', 'X-Content-Type-Options' => 'nosniff']);
    }
}
