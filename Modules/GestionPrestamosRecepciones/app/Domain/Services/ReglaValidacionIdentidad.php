<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Services;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionIdentidad;

/**
 * Servicio de dominio puro que compara el nombre del perfil del investigador con el
 * nombre que figura en el documento de identidad para validar coincidencia.
 *
 * Aplica una heurística en dos niveles: distancia de Levenshtein para errores
 * tipográficos menores y similitud de tokens (índice de Jaccard) para nombres con
 * partes faltantes o adicionales. Devuelve un {@see ResultadoValidacionIdentidad}
 * (Conforme, discrepancia tipográfica o discrepancia de tercero) y las acciones
 * permitidas para cada resultado.
 */
final class ReglaValidacionIdentidad
{
    private const UMBRAL_DISCREPANCIA_TIPOGRAFICA = 3;

    private const ACCIONES = [
        ResultadoValidacionIdentidad::Conforme->value => ['Continuar trámite'],
        ResultadoValidacionIdentidad::DiscrepanciaTypografica->value => ['Corregir nombre en Perfil'],
        ResultadoValidacionIdentidad::DiscrepanciaTercero->value => ['Adjuntar Justificación / Carta de Delegación'],
    ];

    /**
     * Compara dos nombres (normalizando tildes, ñ y mayúsculas) y clasifica el
     * grado de coincidencia.
     */
    public function comparar(string $nombrePerfil, string $nombreEnDocumento): ResultadoValidacionIdentidad
    {
        $normalPerfil = $this->normalizar($nombrePerfil);
        $normalDocumento = $this->normalizar($nombreEnDocumento);

        if ($normalPerfil === $normalDocumento) {
            return ResultadoValidacionIdentidad::Conforme;
        }

        // Similitud por caracteres (errores tipográficos menores)
        if (levenshtein($normalPerfil, $normalDocumento) <= self::UMBRAL_DISCREPANCIA_TIPOGRAFICA) {
            return ResultadoValidacionIdentidad::DiscrepanciaTypografica;
        }

        // Similitud por tokens: nombres faltantes o adicionales (ej. segundo nombre)
        // Si el índice de Jaccard es > 0.5, es la misma persona con partes del nombre distintas.
        $tokensPerfil = array_filter(explode(' ', $normalPerfil));
        $tokensDocumento = array_filter(explode(' ', $normalDocumento));

        $interseccion = count(array_intersect($tokensPerfil, $tokensDocumento));
        $union = count(array_unique(array_merge($tokensPerfil, $tokensDocumento)));

        if ($union > 0 && ($interseccion / $union) >= 0.5) {
            return ResultadoValidacionIdentidad::DiscrepanciaTypografica;
        }

        return ResultadoValidacionIdentidad::DiscrepanciaTercero;
    }

    /**
     * Retorna las acciones sugeridas al usuario según el resultado de la comparación.
     *
     * @return string[]
     */
    public function accionesPermitidas(ResultadoValidacionIdentidad $resultado): array
    {
        return self::ACCIONES[$resultado->value] ?? [];
    }

    private function normalizar(string $nombre): string
    {
        $mapa = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
            'ñ' => 'n', 'Ñ' => 'n', 'ü' => 'u', 'Ü' => 'u',
        ];

        return strtolower(strtr($nombre, $mapa));
    }
}
