<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Modules\CatalogoPublico\Application\Ports\GeneradorRespuestaChatBotPort;
use Modules\CatalogoPublico\Domain\ValueObjects\ChatBotMensajes;
use Modules\CatalogoPublico\Domain\ValueObjects\ContextoLLM;
use Modules\CatalogoPublico\Domain\ValueObjects\DatosEspecimenParaContexto;
use Modules\CatalogoPublico\Domain\ValueObjects\EstadisticaTaxon;

final class GroqGeneradorRespuestaChatBotAdapter implements GeneradorRespuestaChatBotPort
{
    public function __construct(
        private readonly string $modelo,
    ) {}

    public function generar(ContextoLLM $contexto): string
    {
        // En ranking sin datos no hay nada útil que aportar (el LLM no puede inventar
        // conteos). En lookup vacío sí tiene sentido: el LLM puede añadir contexto
        // general sobre el taxón en el bloque "Contexto adicional".
        if ($contexto->esRanking() && $contexto->estaVacio()) {
            return ChatBotMensajes::SIN_RESULTADOS;
        }

        try {
            set_time_limit(120);

            $agente = new ChatBotAgent(instrucciones: $this->construirInstrucciones($contexto));

            $respuesta = $agente->prompt(
                $this->construirPrompt($contexto),
                provider: Lab::Groq,
                model: $this->modelo,
                timeout: 90,
            );

            $texto = trim((string) $respuesta);

            return $texto === '' ? ChatBotMensajes::SIN_RESULTADOS : $texto;
        } catch (\Throwable $e) {
            Log::warning('ChatBotGenerador: fallo al invocar GROQ', [
                'pregunta' => $contexto->pregunta,
                'error' => $e->getMessage(),
            ]);

            return ChatBotMensajes::SIN_RESULTADOS;
        }
    }

    private function construirInstrucciones(ContextoLLM $contexto): string
    {
        if ($contexto->esRanking()) {
            return <<<'INST'
            Eres un asistente experto de una colección entomológica.
            Recibirás la pregunta de un visitante y un ranking agregado por rango taxonómico
            calculado sobre los especímenes divulgados de la colección.

            Reglas:
            - Responde en español, claro y conciso.
            - Usa exclusivamente los datos del ranking. No inventes números ni taxones.
            - Menciona el rango consultado (familia, género, especie, etc.) y los conteos.
            - Si la pregunta pide "el/la más" o "top N", enumera los primeros del ranking.
            - Si la pregunta pide un total ("cuántas hay"), usa el "totalUnicosEnRango".
            - Los nombres científicos deben ir en cursiva usando *asteriscos* de Markdown.
            - No agregues JSON, código, ni metadatos. Solo texto natural.
            INST;
        }

        return <<<'INST'
        Eres un asistente experto de una colección entomológica.
        Recibirás la pregunta de un visitante y una tabla con datos estructurados de los
        especímenes divulgados que son relevantes para responderla.

        Estructura de la respuesta (obligatoria cuando aportes información fuera de la
        colección):

        - PRIMERO responde desde los datos de la tabla. Es la fuente autoritativa del
          laboratorio. Si la tabla está vacía, indícalo explícitamente ("En nuestra
          colección no encuentro especímenes que coincidan con [tema]").
        - DESPUÉS, si tienes conocimiento biológico general útil para complementar
          la respuesta (hábitos, distribución conocida, características de la especie,
          etc.), agrégalo en un bloque separado que comience EXACTAMENTE con:
              **Contexto adicional (fuera de la colección):**
          Ese bloque contiene información de tu conocimiento general, NO de la
          colección del laboratorio. El visitante debe poder distinguirlo.

        Reglas generales:
        - Responde en español, de forma clara y concisa.
        - En el primer bloque, usa exclusivamente la información de la tabla. No inventes
          datos sobre la colección.
        - Al referirte al identificador de un espécimen (campo "occurrenceID" en la tabla)
          usa SIEMPRE la expresión "N.º de catálogo" (por ejemplo: "el N.º de catálogo
          EPN-0012"). Nunca uses "occurrenceID", "occurrence_id" ni "ID" al hablar con
          el visitante.
        - Los nombres científicos deben ir en cursiva usando *asteriscos* de Markdown.
        - Si la tabla está vacía Y no tienes conocimiento útil sobre el tema, indica que
          no encontraste información y sugiere reformular la pregunta.
        - No agregues JSON, código o metadatos. Solo texto natural.
        INST;
    }

    private function construirPrompt(ContextoLLM $contexto): string
    {
        if ($contexto->esRanking()) {
            return $this->promptRanking($contexto);
        }

        return $this->promptLookup($contexto);
    }

    private function promptLookup(ContextoLLM $contexto): string
    {
        $tabla = $contexto->especimenes === []
            ? '(sin especímenes coincidentes en la colección)'
            : $this->serializarEspecimenes($contexto);

        return <<<PROMPT
        Pregunta del visitante:
        "{$contexto->pregunta}"

        Datos de los especímenes recuperados (solo campos divulgables):
        {$tabla}

        Sigue la estructura obligatoria: primero responde desde estos datos; si aportas
        conocimiento general adicional, sepáralo con el bloque "**Contexto adicional
        (fuera de la colección):**".
        PROMPT;
    }

    private function promptRanking(ContextoLLM $contexto): string
    {
        $rango = $contexto->rangoAgregacion?->value ?? 'family';
        $total = $contexto->totalUnicosEnRango ?? 0;
        $lista = $this->serializarRanking($contexto->estadisticas);

        return <<<PROMPT
        Pregunta del visitante:
        "{$contexto->pregunta}"

        Agregación por rango taxonómico: {$rango}
        totalUnicosEnRango: {$total}

        Ranking (nombre — cantidad de registros divulgados):
        {$lista}

        Redacta una respuesta natural en español usando únicamente estos datos.
        PROMPT;
    }

    private function serializarEspecimenes(ContextoLLM $contexto): string
    {
        $lineas = [];

        foreach ($contexto->especimenes as $especimen) {
            $lineas[] = $this->serializarEspecimen($especimen);
        }

        return implode("\n---\n", $lineas);
    }

    private function serializarEspecimen(DatosEspecimenParaContexto $especimen): string
    {
        $partes = ["occurrenceID: {$especimen->occurrenceID}"];

        foreach ($especimen->camposVisibles as $campo => $valor) {
            if ($campo === 'occurrenceID') {
                continue;
            }
            $partes[] = "{$campo}: {$this->formatear($valor)}";
        }

        return implode("\n", $partes);
    }

    /** @param list<EstadisticaTaxon> $estadisticas */
    private function serializarRanking(array $estadisticas): string
    {
        if ($estadisticas === []) {
            return '(sin datos)';
        }

        $lineas = [];
        foreach ($estadisticas as $stat) {
            $lineas[] = "- {$stat->nombre} — {$stat->cantidadRegistros}";
        }

        return implode("\n", $lineas);
    }

    private function formatear(mixed $valor): string
    {
        if (is_bool($valor)) {
            return $valor ? 'true' : 'false';
        }

        return (string) $valor;
    }
}
