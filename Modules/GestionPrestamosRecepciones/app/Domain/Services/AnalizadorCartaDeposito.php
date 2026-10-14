<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Services;

use Illuminate\Support\Str;

/** Identifica el propósito de cartas por el texto, conservando evidencias para la revisión. */
final class AnalizadorCartaDeposito
{
    public function tipoEsperado(string $nombre): ?string
    {
        return match ($nombre) {
            'Documento de explicación de motivos y/o carta de justificación (institucional o personal)' => 'carta_justificacion',
            'Documento de procedencia de los especimenes' => 'carta_procedencia',
            'Carta de cesión de derechos / origen lícito' => 'carta_cesion',
            'Carta de delegación / justificación de tercero' => 'carta_delegacion',
            default => null,
        };
    }

    /** @return array{compatible: bool, tipo_compatible: bool, tipo_detectado: string, evidencias: array<string,string>, discrepancias: list<string>} */
    public function analizar(string $texto, string $tipo, array $expediente = []): array
    {
        $normal = $this->normalizar($texto);
        $patron = match ($tipo) {
            'carta_justificacion' => '/\b(?:justific(?:acion|o|amos)|explicacion de motivos)\b/',
            'carta_procedencia' => '/\b(?:procedencia|origen (?:del material|de los especimenes|de las muestras))\b/',
            'carta_cesion' => '/\b(?:cesion|cedo|cedemos|dono|donamos)\b/',
            'carta_delegacion' => '/\b(?:delegacion|delego|delegamos|autorizo a|representacion)\b/',
            default => '/(?!)/',
        };
        $evidencias = [];
        if (preg_match($patron, $normal, $hallazgo)) $evidencias['proposito'] = $hallazgo[0];
        if (preg_match('/\b(?:especimenes|material biologico|muestras biologicas|deposito|coleccion|museo de historia natural)\b/', $normal, $hallazgo)) $evidencias['material'] = $hallazgo[0];
        $declaracion = match ($tipo) {
            'carta_justificacion' => '/(?:no (?:tengo|dispongo|cuento|poseo|se dispone)|sin|ausencia|falta|no fue posible|no se cuenta)[\s\S]{0,100}?(?:permiso|autorizaci|document)|(?:permiso|autorizaci)[\s\S]{0,70}?(?:no disponibles|ausencia|no se dispone)/',
            'carta_procedencia' => '/\b(?:provienen|procede|proceden|recolectad[oa]s?|obtenid[oa]s?|colectad[oa]s?|entregad[oa]s?)\b[\s\S]{0,100}\b(?:de|en|por)\b/',
            'carta_cesion' => '/(?:cedo|cedemos|dono|donamos|ceder|donar|cesion)[\s\S]{0,160}(?:derechos|propiedad|coleccion|museo|material|especimenes)/',
            'carta_delegacion' => '/(?:delego|delegamos|autorizo a|representacion)[\s\S]{0,180}(?:tramite|deposito|entrega|representar|especimenes)/',
            default => '/(?!)/',
        };
        if (preg_match($declaracion, $normal, $hallazgo)) $evidencias['declaracion'] = $hallazgo[0];
        $discrepancias = [];
        foreach (['titular', 'proyecto'] as $campo) {
            $esperado = $this->normalizar((string) ($expediente[$campo] ?? ''));
            if ($esperado === '') continue;
            $patronCampo = $campo === 'titular' ? '/(?:titular|solicitante|nombre)\s*[:#-]\s*([^\r\n]{4,180})/iu' : '/proyecto\s*[:#-]\s*([^\r\n]{4,350})/iu';
            if (preg_match($patronCampo, $texto, $hallazgo)) {
                $declarado = $this->normalizar($hallazgo[1]);
                if ($declarado !== $esperado) $discrepancias[] = 'El '.$campo.' declarado en la carta no corresponde al de esta solicitud.';
                else $evidencias[$campo] = $hallazgo[1];
            } elseif (str_contains($normal, $esperado)) {
                $evidencias[$campo] = (string) $expediente[$campo];
            } else {
                $discrepancias[] = 'No se puede identificar en la carta el '.$campo.' de esta solicitud. Inclúyelo de forma legible.';
            }
        }

        $tipoCompatible = count(array_intersect(['proposito', 'material', 'declaracion'], array_keys($evidencias))) === 3;
        return ['compatible' => $tipoCompatible && $discrepancias === [], 'tipo_compatible' => $tipoCompatible,
            'tipo_detectado' => $tipo, 'evidencias' => $evidencias, 'discrepancias' => $discrepancias];
    }

    private function normalizar(string $texto): string
    {
        return trim(preg_replace('/\s+/u', ' ', Str::lower(Str::ascii($texto))) ?? '');
    }
}
