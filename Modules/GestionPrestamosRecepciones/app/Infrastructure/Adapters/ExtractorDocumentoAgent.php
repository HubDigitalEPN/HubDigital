<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;

/**
 * Agente de IA especializado en la extracción de datos de documentos PDF.
 *
 * Utiliza capacidades de procesamiento de lenguaje natural para identificar y
 * estructurar información relevante dentro del texto extraído de los documentos.
 */
#[Temperature(0.15)]
final class ExtractorDocumentoAgent implements Agent, Conversational
{
    use Promptable;

    /**
     * Inicializa el agente de extracción con sus instrucciones.
     *
     * @param string $instrucciones Instrucciones de sistema para el LLM.
     */
    public function __construct(
        private readonly string $instrucciones,
    ) {}

    /**
     * Retorna las instrucciones del sistema para el agente.
     */
    public function instructions(): string
    {
        return $this->instrucciones;
    }

    /**
     * Retorna el historial de mensajes (vacío por defecto para este agente).
     */
    public function messages(): iterable
    {
        return [];
    }
}
