<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Services;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionIdentidad;

final class ReglaValidacionIdentidad
{
    private const UMBRAL_DISCREPANCIA_TIPOGRAFICA = 3;

    private const ACCIONES = [
        ResultadoValidacionIdentidad::Conforme->value => ['Continuar trámite'],
        ResultadoValidacionIdentidad::DiscrepanciaTypografica->value => ['Corregir nombre en Perfil'],
        ResultadoValidacionIdentidad::DiscrepanciaTercero->value => ['Adjuntar Justificación / Carta de Delegación'],
    ];

    public function comparar(string $nombrePerfil, string $nombreEnDocumento): ResultadoValidacionIdentidad
    {
        $normalPerfil = $this->normalizar($nombrePerfil);
        $normalDocumento = $this->normalizar($nombreEnDocumento);

        if ($normalPerfil === $normalDocumento) {
            return ResultadoValidacionIdentidad::Conforme;
        }

        $distancia = levenshtein($normalPerfil, $normalDocumento);

        if ($distancia <= self::UMBRAL_DISCREPANCIA_TIPOGRAFICA) {
            return ResultadoValidacionIdentidad::DiscrepanciaTypografica;
        }

        return ResultadoValidacionIdentidad::DiscrepanciaTercero;
    }

    /** @return string[] */
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
