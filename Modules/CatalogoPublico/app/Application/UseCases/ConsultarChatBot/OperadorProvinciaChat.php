<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use App\Support\CatalogoTerritorialEcuador;

/** Reconoce solicitudes de la función retirada para no devolver conteos parciales. */
final class OperadorProvinciaChat
{
    // «Especies distintas de un taxón» pide riqueza; el comparativo excluye solo con objeto provincia.
    private const OPERADOR = '(?:fuera (?:de|del)|excepto|salvo|(?<!al )menos|excluy\w*|exclu(?:ye|ir|yendo)|sin la provincia|no en|no (?:son|es|estan|esta|se recolectaron|se recolecto|sean) (?:de|en)|no (?:busco|quiero|deseo) (?:registros|ejemplares|especimenes)(?: (?:de|en))?|provincias? (?:distintas?|diferentes?) de)';

    public function __construct(private readonly TextoChat $texto) {}

    public function solicitado(string $pregunta): bool
    {
        $normal = $this->texto->normalizar($pregunta);
        if (preg_match('/\b'.self::OPERADOR.'\b/', $normal)
            || preg_match('/\b(?:resto de (?:las )?provincias|(?:otras|demas|restantes) provincias|provincias restantes)\b/', $normal)) return true;
        foreach (CatalogoTerritorialEcuador::provincias() as $provincia) {
            if (preg_match('/\bno (?:quiero|deseo|busco) (?:(?:la )?provincia (?:de )?)?'.preg_quote($this->texto->normalizar($provincia['nombre']), '/').'\b/', $normal)) return true;
        }
        return false;
    }

}
