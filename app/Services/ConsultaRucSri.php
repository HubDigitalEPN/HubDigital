<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Consulta pública oficial; no sustituye una caída del SRI por un rechazo del RUC. */
final class ConsultaRucSri
{
    public const URL = 'https://srienlinea.sri.gob.ec/sri-catastro-sujeto-servicio-internet/rest/ConsolidadoContribuyente/obtenerPorNumerosRuc';

    /** @return array{estado:string,razon_social?:string,estado_ruc?:string} */
    public function consultar(string $ruc): array
    {
        if (preg_match('/^[0-9]{10}001$/D', $ruc) !== 1) return ['estado' => 'formato_invalido'];
        $clave = 'consulta-ruc-sri:'.hash('sha256', $ruc);
        $cuerpo = null;
        try {
            $previa = Cache::get($clave);
            if (is_array($previa)) return $previa;
            $respuesta = Http::acceptJson()->connectTimeout(1)->timeout(3)
                ->withOptions(['allow_redirects' => false, 'stream' => true])->get(self::URL, ['ruc' => $ruc]);
            if ($respuesta->status() !== 200 || ! str_contains(strtolower($respuesta->header('Content-Type')), 'application/json')) {
                return ['estado' => 'no_disponible'];
            }
            $cuerpo = $respuesta->toPsrResponse()->getBody();
            $texto = $cuerpo->read(65537);
            if (strlen($texto) > 65536) return ['estado' => 'no_disponible'];
            $datos = json_decode($texto, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($datos) || ! array_is_list($datos)) return ['estado' => 'no_disponible'];
            if ($datos === []) {
                $resultado = ['estado' => 'no_encontrado'];
            } else {
                $fila = collect($datos)->first(static fn ($fila): bool => is_array($fila) && (string) ($fila['numeroRuc'] ?? '') === $ruc);
                if (! is_array($fila) || ! is_string($fila['razonSocial'] ?? null) || ! is_string($fila['estadoContribuyenteRuc'] ?? null)) {
                    return ['estado' => 'no_disponible'];
                }
                $resultado = ['estado' => 'verificado', 'razon_social' => mb_substr($fila['razonSocial'], 0, 250),
                    'estado_ruc' => mb_substr($fila['estadoContribuyenteRuc'], 0, 40)];
            }
            Cache::put($clave, $resultado, now()->addMinutes($resultado['estado'] === 'verificado' ? 5 : 1));
            return $resultado;
        } catch (\Throwable) {
            return ['estado' => 'no_disponible'];
        } finally {
            $cuerpo?->close();
        }
    }
}
