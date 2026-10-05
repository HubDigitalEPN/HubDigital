<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

/** Resuelve la relación geográfica antes de reutilizar contexto o contar registros. */
final class OperadorProvinciaChat
{
    // «Especies distintas de un taxón» pide riqueza; el comparativo excluye solo con objeto provincia.
    private const OPERADOR = '(?:fuera (?:de|del)|excepto|salvo|(?<!al )menos|excluy\w*|exclu(?:ye|ir|yendo)|sin la provincia|no en|no (?:son|es|estan|esta|se recolectaron|se recolecto|sean) (?:de|en)|no (?:busco|quiero|deseo) (?:registros|ejemplares|especimenes)(?: (?:de|en))?|provincias? (?:distintas?|diferentes?) de)';

    public function __construct(private readonly TextoChat $texto) {}

    public function solicitado(string $pregunta): bool
    {
        return (bool) preg_match('/\b'.self::OPERADOR.'\b/', $this->texto->normalizar($pregunta));
    }

    /** @param list<string> $provincias Provincias de registros públicos con el campo visible. */
    public function extraer(string $pregunta, array $provincias): array
    {
        $normal = $this->texto->normalizar($pregunta);
        $provincias = array_values(array_filter($provincias, static fn (string $p): bool => \Modules\CatalogoPublico\Infrastructure\CalidadDatoPublico::esTextoValido($p)));
        preg_match_all('/\b'.self::OPERADOR.'\s+(?:(?:la )?provincia (?:de )?)?(.+)/', $normal, $operaciones);
        if ($operaciones[1] === []) return $this->solicitado($pregunta) ? $this->error() : [];
        $excluidas = [];
        foreach ($operaciones[1] as $cola) {
            $coincidencias = [];
            foreach ($provincias as $provincia) {
                $nombre = $this->texto->normalizar($provincia);
                if ($nombre === '') continue;
                if (preg_match('/^'.preg_quote($nombre, '/').'\b(.*)$/', $cola, $resto)) {
                    // No calcular una sola alternativa de una exclusión múltiple.
                    if (preg_match('/^\s+(?:y|o)\s+(?!(?:busco|quiero|deseo|cuant|descarg|ver|volver|en)\b)/', $resto[1])) return $this->error();
                    $coincidencias[$nombre] = $provincia;
                }
            }
            if (count($coincidencias) !== 1) return $this->error();
            $excluidas += $coincidencias;
        }
        if (count($excluidas) !== 1) return $this->error();
        // Otro operador en la cola no debe quedar absorbido por la coincidencia anterior.
        if (preg_match_all('/\b'.self::OPERADOR.'\b/', $normal) !== 1) return $this->error();
        $excluida = reset($excluidas);
        $sinExclusion = preg_replace('/\b'.self::OPERADOR.'\s+(?:(?:la )?provincia (?:de )?)?'.preg_quote($this->texto->normalizar($excluida), '/').'\b/', ' ', $normal);
        $incluidas = [];
        foreach ($provincias as $provincia) {
            $nombre = $this->texto->normalizar($provincia);
            if ($nombre === '') continue;
            if (preg_match('/\b(?:en|de)\s+(?:(?:la )?provincia (?:de )?)?'.preg_quote($nombre, '/').'\b/', $sinExclusion)) {
                if (isset($excluidas[$nombre])) return $this->error();
                $incluidas[$nombre] = $provincia;
            }
        }
        if (count($incluidas) > 1) return $this->error();
        return ['provincia_excluida' => $excluida] + ($incluidas === [] ? [] : ['provincia' => reset($incluidas)]);
    }

    private function error(): array
    {
        return ['error_consulta' => 'No pude resolver la exclusión de una única provincia pública. Indica el taxón y una provincia que deseas excluir; no he calculado un conteo ni preparado filtros parciales.'];
    }
}
