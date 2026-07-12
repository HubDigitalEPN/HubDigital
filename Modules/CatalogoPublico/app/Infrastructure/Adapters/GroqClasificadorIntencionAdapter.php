<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Modules\CatalogoPublico\Application\Ports\ClasificadorIntencionPort;
use Modules\CatalogoPublico\Domain\ValueObjects\EntidadesExtraidas;
use Modules\CatalogoPublico\Domain\ValueObjects\IntencionConsulta;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;

final class GroqClasificadorIntencionAdapter implements ClasificadorIntencionPort
{
    public function __construct(
        private readonly string $modelo,
    ) {}

    public function clasificar(string $pregunta): IntencionConsulta
    {
        try {
            set_time_limit(60);

            $agente = new ChatBotAgent(instrucciones: $this->construirInstrucciones());

            $respuesta = $agente->prompt(
                $this->construirPrompt($pregunta),
                provider: Lab::Groq,
                model: $this->modelo,
                timeout: 60,
            );

            $datos = $this->extraerJson((string) $respuesta);

            if ($datos === null) {
                Log::warning('ChatBotClasificador: no se pudo interpretar la respuesta del LLM', [
                    'pregunta' => $pregunta,
                ]);

                return IntencionConsulta::fueraDelDominio();
            }

            if (! ($datos['dentroDeDominio'] ?? false)) {
                return IntencionConsulta::fueraDelDominio();
            }

            $tipo = strtolower((string) ($datos['tipo'] ?? 'lookup'));

            if ($tipo === 'ranking') {
                $rango = $this->parsearRango($datos['rangoAgregacion'] ?? null);

                if ($rango === null) {
                    Log::info('ChatBotClasificador: ranking sin rango válido, caigo a lookup', [
                        'pregunta' => $pregunta,
                    ]);
                } else {
                    $limite = (int) ($datos['limiteRanking'] ?? 5);

                    return IntencionConsulta::ranking($rango, $limite > 0 ? $limite : null);
                }
            }

            $entidades = (array) ($datos['entidades'] ?? []);

            return IntencionConsulta::dentroDelDominio(
                EntidadesExtraidas::desde(
                    genero: $this->limpiar($entidades['genero'] ?? null),
                    especie: $this->limpiar($entidades['especie'] ?? null),
                    occurrenceID: $this->limpiar($entidades['occurrenceID'] ?? null),
                    localidad: $this->limpiar($entidades['localidad'] ?? null),
                    country: $this->limpiar($entidades['country'] ?? null),
                ),
            );
        } catch (\Throwable $e) {
            Log::warning('ChatBotClasificador: fallo al invocar GROQ', [
                'pregunta' => $pregunta,
                'error' => $e->getMessage(),
            ]);

            return IntencionConsulta::fueraDelDominio();
        }
    }

    private function construirInstrucciones(): string
    {
        return <<<'INST'
        Eres un clasificador de intención para un chatbot de una colección entomológica.
        Solo se aceptan preguntas sobre especímenes de insectos, su taxonomía, localidades
        de recolección, protocolos de recolección y datos de curatoría.

        Devuelve SIEMPRE un objeto JSON válido con esta estructura:
        {
          "dentroDeDominio": bool,
          "tipo": "lookup" | "ranking",
          "entidades": {
            "genero": string|null,
            "especie": string|null,
            "occurrenceID": string|null,
            "localidad": string|null,
            "country": string|null
          },
          "rangoAgregacion": "phylum" | "class" | "order" | "family" | "genus" | "species" | null,
          "limiteRanking": int
        }

        Reglas generales:
        - dentroDeDominio = false para cualquier pregunta que no trate de la colección entomológica
          (deportes, cocina, generación de código, política, tareas administrativas, etc.).
        - Si dentroDeDominio es false, todas las entidades deben ser null y tipo = "lookup".

        Cuándo usar tipo = "lookup" (búsqueda de datos específicos):
        - La pregunta menciona un occurrenceID (ej. "EPN-0012"), un género taxonómico, una especie,
          una localidad o un país concretos.
        - Preguntas ABIERTAS sobre un taxón concreto también son lookup: "qué me puedes decir de X",
          "info sobre X", "cuéntame de X", "dato relevante de X", "algo sobre X". La entidad
          extraída es X (especie si es binomio, género si es una sola palabra taxonómica).
        - Llenar las entidades correspondientes; dejar rangoAgregacion = null y limiteRanking = 5.

        Cuándo usar tipo = "ranking" (agregación por rango taxonómico):
        - La pregunta usa palabras como "cuántos", "cuántas", "más", "menos", "mayor", "top",
          "ranking", "listado", junto con un rango taxonómico
          (filo/phylum, clase/class, orden/order, familia/family, género/genus, especie/species)
          o la palabra "registros".
        - IMPORTANTE: la palabra "distribución" NO implica ranking cuando se refiere a una
          especie/género concreto ("la distribución de X" es lookup por especie/género).
          Solo es ranking si se usa como "distribución por familia/género" sin nombrar taxón.
        - IMPORTANTE: preguntas por "provincias", "localidades" o "países donde se encontró X"
          son lookup por el taxón X, no ranking. El objetivo es traer los registros de X para
          poder listar sus provincias/localidades.
        - Traducir el rango al valor en inglés del enum: filo→phylum, clase→class, orden→order,
          familia→family, género→genus, especie→species.
        - Todas las entidades deben ser null en modo ranking.
        - limiteRanking: si la pregunta pide "top N" o "los N con más...", usar N. Por defecto 5.
          Para "cuántos hay" usar limiteRanking = 5 (el generador expondrá también el total).

        Ejemplos:
        Pregunta: "¿cuál es la familia con más registros?"
        JSON: {"dentroDeDominio": true, "tipo": "ranking", "entidades": {"genero": null, "especie": null, "occurrenceID": null, "localidad": null, "country": null}, "rangoAgregacion": "family", "limiteRanking": 5}

        Pregunta: "¿cuántas especies hay en la colección?"
        JSON: {"dentroDeDominio": true, "tipo": "ranking", "entidades": {"genero": null, "especie": null, "occurrenceID": null, "localidad": null, "country": null}, "rangoAgregacion": "species", "limiteRanking": 5}

        Pregunta: "top 3 géneros"
        JSON: {"dentroDeDominio": true, "tipo": "ranking", "entidades": {"genero": null, "especie": null, "occurrenceID": null, "localidad": null, "country": null}, "rangoAgregacion": "genus", "limiteRanking": 3}

        Pregunta: "¿qué especímenes del género Atta hay?"
        JSON: {"dentroDeDominio": true, "tipo": "lookup", "entidades": {"genero": "Atta", "especie": null, "occurrenceID": null, "localidad": null, "country": null}, "rangoAgregacion": null, "limiteRanking": 5}

        Pregunta: "sintetiza el espécimen EPN-002"
        JSON: {"dentroDeDominio": true, "tipo": "lookup", "entidades": {"genero": null, "especie": null, "occurrenceID": "EPN-002", "localidad": null, "country": null}, "rangoAgregacion": null, "limiteRanking": 5}

        Pregunta: "¿cuál es la distribución de la especie Dichotomius satanas?"
        JSON: {"dentroDeDominio": true, "tipo": "lookup", "entidades": {"genero": null, "especie": "Dichotomius satanas", "occurrenceID": null, "localidad": null, "country": null}, "rangoAgregacion": null, "limiteRanking": 5}

        Pregunta: "¿en qué provincias se recolectó Sulcophanaeus noctis?"
        JSON: {"dentroDeDominio": true, "tipo": "lookup", "entidades": {"genero": null, "especie": "Sulcophanaeus noctis", "occurrenceID": null, "localidad": null, "country": null}, "rangoAgregacion": null, "limiteRanking": 5}

        Pregunta: "¿qué me puedes decir de Neoponera apicalis?"
        JSON: {"dentroDeDominio": true, "tipo": "lookup", "entidades": {"genero": null, "especie": "Neoponera apicalis", "occurrenceID": null, "localidad": null, "country": null}, "rangoAgregacion": null, "limiteRanking": 5}

        Pregunta: "algún dato relevante sobre Bombus"
        JSON: {"dentroDeDominio": true, "tipo": "lookup", "entidades": {"genero": "Bombus", "especie": null, "occurrenceID": null, "localidad": null, "country": null}, "rangoAgregacion": null, "limiteRanking": 5}

        Pregunta: "¿receta para hacer pan?"
        JSON: {"dentroDeDominio": false, "tipo": "lookup", "entidades": {"genero": null, "especie": null, "occurrenceID": null, "localidad": null, "country": null}, "rangoAgregacion": null, "limiteRanking": 5}

        No agregues texto fuera del JSON. No inventes datos.
        INST;
    }

    private function construirPrompt(string $pregunta): string
    {
        return <<<PROMPT
        Pregunta del visitante:
        "{$pregunta}"

        Clasifica esta pregunta siguiendo las reglas y devuelve solo el objeto JSON.
        PROMPT;
    }

    /** @return array<string, mixed>|null */
    private function extraerJson(string $texto): ?array
    {
        $texto = trim($texto);

        $decoded = json_decode($texto, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $texto, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (preg_match('/\{[\s\S]*\}/', $texto, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function parsearRango(mixed $valor): ?RangoTaxonomico
    {
        if (! is_string($valor)) {
            return null;
        }

        $normalizado = strtolower(trim($valor));

        return match ($normalizado) {
            'phylum', 'filo' => RangoTaxonomico::Phylum,
            'class', 'clase' => RangoTaxonomico::Class_,
            'order', 'orden' => RangoTaxonomico::Order,
            'family', 'familia' => RangoTaxonomico::Family,
            'genus', 'genero', 'género' => RangoTaxonomico::Genus,
            'species', 'especie' => RangoTaxonomico::Species,
            default => null,
        };
    }

    private function limpiar(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $limpio = trim($valor);

        if ($limpio === '' || strtolower($limpio) === 'null') {
            return null;
        }

        return $limpio;
    }
}
