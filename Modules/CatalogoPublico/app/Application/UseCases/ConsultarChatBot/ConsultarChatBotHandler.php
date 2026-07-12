<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Modules\CatalogoPublico\Application\Ports\ClasificadorIntencionPort;
use Modules\CatalogoPublico\Application\Ports\GeneradorRespuestaChatBotPort;
use Modules\CatalogoPublico\Domain\Services\CalculadorEstadisticasTaxonomicas;
use Modules\CatalogoPublico\Domain\Services\RecuperadorContextoEspecimenes;
use Modules\CatalogoPublico\Domain\ValueObjects\ChatBotMensajes;
use Modules\CatalogoPublico\Domain\ValueObjects\ContextoLLM;
use Modules\CatalogoPublico\Domain\ValueObjects\IntencionConsulta;
use Modules\CatalogoPublico\Domain\ValueObjects\RangoTaxonomico;
use Modules\CatalogoPublico\Domain\ValueObjects\TipoDeConsulta;

final readonly class ConsultarChatBotHandler
{
    public function __construct(
        private ClasificadorIntencionPort $clasificador,
        private RecuperadorContextoEspecimenes $recuperador,
        private CalculadorEstadisticasTaxonomicas $calculador,
        private GeneradorRespuestaChatBotPort $generador,
    ) {}

    public function handle(ConsultarChatBotInput $input): ConsultarChatBotOutput
    {
        $pregunta = trim($input->pregunta);

        if ($pregunta === '') {
            throw new \InvalidArgumentException('La pregunta del chatbot no puede ser vacía');
        }

        $intencion = $this->clasificador->clasificar($pregunta);

        if (! $intencion->dentroDeDominio) {
            return ConsultarChatBotOutput::fueraDeDominio(ChatBotMensajes::FUERA_DE_DOMINIO);
        }

        $contexto = $intencion->tipo === TipoDeConsulta::Ranking
            ? $this->contextoRanking($pregunta, $intencion)
            : $this->contextoLookup($pregunta, $intencion);

        if ($this->debeCortarComoSinResultados($intencion, $contexto)) {
            return ConsultarChatBotOutput::conRespuesta(
                respuesta: ChatBotMensajes::SIN_RESULTADOS,
                contexto: $contexto,
            );
        }

        return ConsultarChatBotOutput::conRespuesta(
            respuesta: $this->generador->generar($contexto),
            contexto: $contexto,
        );
    }

    /**
     * Ranking vacío → no hay datos para agregar, cortar.
     * Lookup vacío + entidades vacías → no hay pista, cortar.
     * Lookup vacío + entidades reconocidas → dejar que el LLM aporte contexto general
     *   sobre el taxón indicado, con la separación explícita del bloque "Contexto adicional".
     */
    private function debeCortarComoSinResultados(IntencionConsulta $intencion, ContextoLLM $contexto): bool
    {
        if (! $contexto->estaVacio()) {
            return false;
        }

        if ($intencion->tipo === TipoDeConsulta::Ranking) {
            return true;
        }

        return $intencion->entidades->estaVacio();
    }

    private function contextoLookup(string $pregunta, IntencionConsulta $intencion): ContextoLLM
    {
        $especimenes = $this->recuperador->recuperar($intencion->entidades);

        return ContextoLLM::conEspecimenes(pregunta: $pregunta, especimenes: $especimenes);
    }

    private function contextoRanking(string $pregunta, IntencionConsulta $intencion): ContextoLLM
    {
        $rango = $intencion->rangoAgregacion ?? RangoTaxonomico::Family;
        $estadisticas = $this->calculador->topPorRango($rango, $intencion->limiteRanking);
        $total = $this->calculador->conteoTotalTaxonesUnicos($rango);

        return ContextoLLM::conRanking(
            pregunta: $pregunta,
            rango: $rango,
            estadisticas: $estadisticas,
            totalUnicosEnRango: $total,
        );
    }
}
