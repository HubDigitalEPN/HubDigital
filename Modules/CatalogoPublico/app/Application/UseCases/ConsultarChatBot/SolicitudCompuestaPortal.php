<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

/** Atiende acciones de consulta en orden, manteniendo una única selección entre partes. */
final class SolicitudCompuestaPortal
{
    public function __construct(
        private readonly TextoChat $texto,
        private readonly DetectorEntidadesChat $detector,
        private readonly ConsultaCatalogoPublico $consulta,
        private readonly AyudaContextualPortal $ayuda,
    ) {}

    public function responder(string $pregunta, array $contexto, ?array $seleccionPortal): ?array
    {
        $normal = $this->texto->normalizar($pregunta);
        if (! preg_match('/descarg|export|xlsx|excel|csv/', $normal)
            || preg_match('/\b(?:deposito|donacion|prestamo|prestan|depositar)\b/', $normal)) return null;
        if (preg_match('/^(?:dame|indica) (?:los )?pasos para (?:consultar|buscar|filtrar)\b/', $normal)) return null;
        // Los procedimientos de filtro ya tienen su contrato y su aclaración de exclusión.
        if (! preg_match('/\b(?:primero|despues|luego|ultimo|cuant[oa]s?)\b/', $normal)) return null;
        $clausulas = preg_split('/[.;!?]|\b(?:primero|despu[eé]s|luego|por [uú]ltimo|finalmente)\b/iu', $pregunta, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $acciones = [];
        foreach ($clausulas as $clausula) {
            $frase = $this->texto->normalizar($clausula);
            $patrones = [
                'conteo' => '/\b(?:cuant[oa]s?|numero|total)\b.*\b(?:registros|especies|generos|familias)\b/',
                'consulta' => '/\b(?:quiero ver|quiero buscar|busca|buscar|muestrame|consultar)\b/',
                'descarga' => '/\b(?:descarg\w*|export\w*)\b/',
                'mapa' => '/\b(?:vuelve|volver|regresa|regresar|abrir|abre|cambia|cambiar)\b.*\bmapa\b/',
                'precision' => '/\b(?:explica|explicame|significa)\b.*\b(?:precision|incertidumbre|coordenadas?)\b/',
                'sin_soporte' => '/\b(?:envia|enviar|publica|publicar|modifica|modificar|borra|borrar)\b/',
            ];
            $enClausula = [];
            foreach ($patrones as $tipo => $patron) {
                if (preg_match($patron, $frase, $m, PREG_OFFSET_CAPTURE)) $enClausula[] = ['tipo' => $tipo, 'texto' => trim($clausula), 'posicion' => $m[0][1]];
            }
            usort($enClausula, static fn (array $a, array $b): int => $a['posicion'] <=> $b['posicion']);
            array_push($acciones, ...$enClausula);
        }
        if (count($acciones) < 2) return null;
        $parametros = $seleccionPortal === null ? $this->consulta->parametros($contexto) : EnlaceSeleccionCatalogo::limpiar($seleccionPortal);
        $partes = []; $opciones = []; $resultados = []; $entidades = $contexto; $seleccionFallida = false;
        foreach ($acciones as $accion) {
            $tipo = $accion['tipo'];
            if (in_array($tipo, ['consulta', 'conteo'], true)) {
                $consulta = $accion['texto'];
                if ($tipo === 'conteo' && $this->detector->extraer($consulta) === [] && ! str_contains($this->texto->normalizar($consulta), 'seleccion')) {
                    $consulta .= ' en esta selección';
                }
                $respuesta = $this->consulta->responder($consulta, $entidades, $seleccionPortal);
                if ($respuesta === null || ($respuesta['intent'] ?? '') === 'catalogo.aclaracion') {
                    $seleccionFallida = true;
                    $partes[] = $respuesta['texto'] ?? 'No pude preparar esa selección. Indica el nombre científico y los criterios de consulta.';
                    $resultados[] = ['accion' => $tipo, 'estado' => 'pendiente'];
                    continue;
                }
                $partes[] = $respuesta['texto'];
                $entidades = $respuesta['entidades'] ?? $entidades;
                $usaPagina = preg_match('/\bseleccion\b/', $this->texto->normalizar($consulta));
                if (! $usaPagina) {
                    $parametros = $this->consulta->parametros($entidades);
                    $seleccionPortal = $parametros;
                }
                $seleccionFallida = false;
                $opciones = array_merge($opciones, $respuesta['opciones']);
                $resultados[] = ['accion' => $tipo, 'estado' => 'respondida', 'datos' => $respuesta['datos'] ?? []];
            } elseif ($tipo === 'sin_soporte') {
                $partes[] = 'No puedo ejecutar esta parte desde el chat: '.$accion['texto'].'.';
                $resultados[] = ['accion' => $tipo, 'estado' => 'pendiente'];
            } else {
                if ($seleccionFallida && in_array($tipo, ['descarga', 'mapa'], true)) {
                    $partes[] = 'Esta parte queda pendiente hasta aclarar la selección; no he preparado un enlace con filtros parciales.';
                    $resultados[] = ['accion' => $tipo, 'estado' => 'pendiente'];
                    continue;
                }
                $instruccion = match ($tipo) {
                    'descarga' => preg_match('/\b(?:xlsx|excel)\b/', $this->texto->normalizar($accion['texto'])) ? 'Cómo descargar datos XLSX' : 'Cómo descargar resultados CSV',
                    'mapa' => 'Volver al mapa sin quitar filtros',
                    default => 'Explica la precisión de las coordenadas recuperadas',
                };
                $respuesta = $this->ayuda->responder($instruccion, $parametros);
                $partes[] = $respuesta['texto'];
                $opciones = array_merge($opciones, $respuesta['opciones']);
                $resultados[] = ['accion' => $tipo, 'estado' => 'orientada'];
            }
        }
        $texto = [];
        foreach ($partes as $i => $parte) $texto[] = ($i + 1).'. '.$parte;
        $unicas = [];
        foreach ($opciones as $opcion) $unicas[$opcion['url'] ?? $opcion['pregunta'] ?? $opcion['label']] = $opcion;
        return ['texto' => implode("\n\n", $texto).' No he descargado archivos ni cambiado la vista por ti; los enlaces permiten completar los pasos.',
            'fuente' => 'portal', 'intent' => 'portal.solicitud_compuesta', 'entidades' => $entidades,
            'opciones' => array_values($unicas), 'partes' => $resultados];
    }
}
