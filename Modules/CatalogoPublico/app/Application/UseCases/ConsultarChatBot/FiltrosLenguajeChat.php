<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use DateTimeImmutable;
use Illuminate\Support\Str;

/** Mantiene la precisión de los límites pedidos antes de construir una selección pública. */
final class FiltrosLenguajeChat
{
    private const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    private const PAREJAS_FECHA = ['desde' => 'hasta', 'del' => 'al', 'entre' => 'y'];

    public function extraer(string $pregunta): array
    {
        // La normalización de palabras elimina los guiones; las fechas se leen antes de ella.
        $texto = Str::lower(Str::ascii($pregunta));
        $filtros = [];
        $meses = implode('|', self::MESES);
        $fecha = '\d{4}-\d{2}-\d{2}';
        $fechasConsumidas = [];
        $rangoNaturalCompleto = false;

        if (preg_match('/\b(desde|del|entre)\s+('.$fecha.')\s+(hasta|al|y)\s+('.$fecha.')\b/', $texto, $m)) {
            $filtros = ['desde' => $m[2], 'hasta' => $m[4]];
            $fechasConsumidas = [$m[2], $m[4]];
            if (self::PAREJAS_FECHA[$m[1]] !== $m[3]) {
                $filtros['error_consulta'] = 'Los límites de fecha necesitan una pareja clara: desde … hasta, del … al o entre … y. Corrige el intervalo antes de consultar.';
            }
        } elseif (preg_match('/\b(desde|del|entre)\s+(?:el\s+)?(\d{1,2})\s+de\s+('.$meses.')\s+(?:de\s+)?(\d{4})\s+(hasta|al|y)\s+(?:el\s+)?(\d{1,2})\s+de\s+('.$meses.')\s+(?:de\s+)?(\d{4})\b/', $texto, $m)) {
            $rangoNaturalCompleto = true;
            $mesDesde = array_search($m[3], self::MESES, true) + 1;
            $mesHasta = array_search($m[7], self::MESES, true) + 1;
            $filtros = ['desde' => sprintf('%04d-%02d-%02d', (int) $m[4], $mesDesde, (int) $m[2]),
                'hasta' => sprintf('%04d-%02d-%02d', (int) $m[8], $mesHasta, (int) $m[6])];
            if (self::PAREJAS_FECHA[$m[1]] !== $m[5]) {
                $filtros['error_consulta'] = 'Los límites de fecha necesitan una pareja clara: desde … hasta, del … al o entre … y. Corrige el intervalo antes de consultar.';
            }
        } elseif (preg_match('/\b(del|desde|entre)\s+(\d{1,2})\s+(al|hasta|y)\s+(\d{1,2})\s+(?:de\s+)?('.$meses.')\s+(?:de\s+)?(\d{4})\b/', $texto, $m)) {
            $rangoNaturalCompleto = true;
            $mes = array_search($m[5], self::MESES, true) + 1;
            $filtros = ['desde' => sprintf('%04d-%02d-%02d', (int) $m[6], $mes, (int) $m[2]),
                'hasta' => sprintf('%04d-%02d-%02d', (int) $m[6], $mes, (int) $m[4])];
            if (self::PAREJAS_FECHA[$m[1]] !== $m[3]) {
                $filtros['error_consulta'] = 'Los límites de fecha necesitan una pareja clara: desde … hasta, del … al o entre … y. Corrige el intervalo antes de consultar.';
            }
        } elseif (preg_match('/\b(?:antes|despues)\s+(?:de(?:\s+el)?|del)\s+('.$fecha.'|\d{4})\b/', $texto, $m)) {
            $antes = str_starts_with($m[0], 'antes');
            if (strlen($m[1]) === 10) $fechasConsumidas[] = $m[1];
            $limite = strlen($m[1]) === 4 ? $m[1].($antes ? '-01-01' : '-12-31') : $m[1];
            if (! $this->fechaValida($limite)) {
                $filtros['error_consulta'] = 'La fecha indicada no es válida. Usa una fecha ISO válida (AAAA-MM-DD).';
            } else {
                $filtros[$antes ? 'hasta' : 'desde'] = (new DateTimeImmutable($limite))->modify($antes ? '-1 day' : '+1 day')->format('Y-m-d');
            }
        } elseif (preg_match('/\b(?:el|en|del)\s+(\d{1,2})\s+de\s+('.$meses.')\s+(?:de\s+)?(\d{4})\b/', $texto, $m)) {
            $mes = array_search($m[2], self::MESES, true) + 1;
            $filtros = ['desde' => sprintf('%04d-%02d-%02d', (int) $m[3], $mes, (int) $m[1]),
                'hasta' => sprintf('%04d-%02d-%02d', (int) $m[3], $mes, (int) $m[1])];
        } elseif (preg_match('/\bentre\s+((?:18|19|20)\d{2})\s+y\s+((?:18|19|20)\d{2})\b(?!\s*(?:metros?\b|m\b))/', $texto, $m)) {
            $filtros = ['desde' => $m[1].'-01-01', 'hasta' => $m[2].'-12-31'];
        } else {
            foreach (['desde' => '(?:desde|a partir de)', 'hasta' => 'hasta'] as $clave => $operador) {
                if (preg_match('/\b'.$operador.'\s+('.$fecha.')\b/', $texto, $m)) {
                    $filtros[$clave] = $m[1];
                    $fechasConsumidas[] = $m[1];
                }
            }
            if ($filtros === [] && preg_match('/\b(?:el|en)\s+('.$fecha.')\b/', $texto, $m)) {
                $filtros = ['desde' => $m[1], 'hasta' => $m[1]];
                $fechasConsumidas[] = $m[1];
            }
        }

        // No convertir una fecha ISO desconocida en un año o descartarla en silencio.
        preg_match_all('/\b\d{4}-\d{1,2}-\d{1,2}\b/', $texto, $fechasMencionadas);
        if (array_diff($fechasMencionadas[0], $fechasConsumidas) !== []) {
            $filtros['error_consulta'] = 'No pude interpretar todos los límites de fecha. Indica desde AAAA-MM-DD hasta AAAA-MM-DD.';
        }
        $diaNatural = '\s+(?:el\s+)?\d{1,2}\s+de\s+(?:'.$meses.')\s+(?:de\s+)?\d{4}\b';
        if (! $rangoNaturalCompleto && (preg_match('/\b(?:desde|entre|hasta|al)'.$diaNatural.'/', $texto)
            || preg_match('/\bdel'.$diaNatural.'.*\b(?:al|hasta|y)\b/', $texto))) {
            $filtros['error_consulta'] = 'No pude interpretar todos los límites de fecha. Indica desde AAAA-MM-DD hasta AAAA-MM-DD.';
        }
        if (($filtros === [] && preg_match('/\b(?:antes|despues)\s+(?:de|del)\b|\b(?:desde|hasta)\s+(?:'.$meses.')\b/', $texto))
            || (preg_match('/\bdesde\s+\d{4}-\d{2}-\d{2}\b.*\bhasta\b/', $texto) && ! isset($filtros['hasta']))) {
            $filtros['error_consulta'] = 'No pude interpretar todos los límites de fecha. Indica desde AAAA-MM-DD hasta AAAA-MM-DD.';
        }
        if (! isset($filtros['error_consulta'])) {
            foreach (['desde', 'hasta'] as $clave) {
                if (isset($filtros[$clave]) && ! $this->fechaValida($filtros[$clave])) {
                    $filtros['error_consulta'] = 'La fecha indicada no es válida. Revisa el día, mes y año antes de consultar.';
                }
            }
        }
        if (isset($filtros['desde'], $filtros['hasta']) && $filtros['desde'] > $filtros['hasta']) {
            $filtros['error_consulta'] = 'Desde no puede ser posterior a Hasta. Corrige el intervalo de fechas antes de consultar.';
        }

        if (! isset($filtros['desde'], $filtros['hasta']) && ! isset($filtros['error_consulta'])) {
            foreach (self::MESES as $indice => $mes) {
                if (! preg_match('/\b'.$mes.'\b/', $texto)) continue;
                $filtros['mes'] = (string) ($indice + 1);
                if (! isset($filtros['desde']) && ! isset($filtros['hasta']) && preg_match('/\b'.$mes.'\s+(?:de\s+)?(\d{4})\b/', $texto, $anio)) {
                    $inicio = new DateTimeImmutable(sprintf('%04d-%02d-01', (int) $anio[1], $indice + 1));
                    $filtros['desde'] = $inicio->format('Y-m-d');
                    $filtros['hasta'] = $inicio->format('Y-m-t');
                }
                break;
            }
            if (! isset($filtros['desde']) && ! isset($filtros['hasta']) && preg_match('/\b(?:en|durante|del|de)\s+(?:el\s+)?(?:ano\s+)?((?:18|19|20)\d{2})\b/', $texto, $anio)) {
                $filtros['desde'] = $anio[1].'-01-01';
                $filtros['hasta'] = $anio[1].'-12-31';
            }
        }

        $numero = '-?\d+(?:[.,]\d+)?';
        if (preg_match('/\bentre\s+('.$numero.')\s+y\s+('.$numero.')\s*(?:m\b|metros?\b)(?:\s+(?:de\s+)?(?:altitud|elevacion))?/', $texto, $m)) {
            $filtros['elev_desde'] = $this->numero($m[1]);
            $filtros['elev_hasta'] = $this->numero($m[2]);
            if ((float) $filtros['elev_desde'] > (float) $filtros['elev_hasta']) {
                $filtros['error_consulta'] = 'La elevación inicial no puede ser mayor que la final. Corrige el intervalo antes de consultar.';
            }
        } elseif (preg_match('/\b(?:altitud|elevacion)\b/', $texto)) {
            $filtros['error_consulta'] = 'No pude interpretar el intervalo de elevación. Indica entre 1000 y 2000 metros de altitud, por ejemplo.';
        }
        return $filtros;
    }

    private function fechaValida(string $fecha): bool
    {
        $valor = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        return $valor !== false && $valor->format('Y-m-d') === $fecha && (int) substr($fecha, 0, 4) >= 1;
    }

    private function numero(string $numero): string
    {
        if (preg_match('/^-?\d{1,3}\.\d{3}$/', $numero)) return str_replace('.', '', $numero);
        return str_replace(',', '.', $numero);
    }
}
