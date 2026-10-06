<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

/** Frases sociales cerradas; no dispara consultas científicas ni inventa políticas. */
final class ConversacionBasica
{
    public function __construct(private readonly TextoChat $texto) {}

    public function responder(string $pregunta): ?array
    {
        $normal = $this->texto->normalizar($pregunta);
        if (in_array($normal, ['hola', 'ola', 'holaa', 'buenos dias', 'buenas tardes', 'buenas noches', 'buenas', 'que tal'], true)
            || preg_match('/^(?:(?:muy )?buen(?:os dias|as tardes|as noches| dia)|hola(?: hola)?|saludos)(?: desde [a-z ]+| estimados| estan por aqui| me ayudan)?$/', $normal)) {
            return ['texto' => '¡Hola! Puedo orientarte sobre depósitos, documentos y registros públicos del catálogo. ¿Qué necesitas?',
                'opciones' => [], 'fuente' => 'conversacion', 'intent' => 'saludo', 'confianza' => 'HIGH', 'confianza_valor' => 1.0];
        }
        if (preg_match('/^(?:muchisimas |muchas )?gracias(?: pana| por la informacion)?$/', $normal)) {
            $normal = 'gracias';
        } elseif (preg_match('/^(?:ok |listo )?(?:perfecto|entendido)$/', $normal)) {
            $normal = 'entendido';
        }
        if (preg_match('/^(?:quien eres(?: tu)?|como te llamas|eres (?:un robot|una ia|humano))$/', $normal)) $normal = 'quien eres';
        $respuestas = [
            'gracias' => 'Con gusto. Si necesitas algo más sobre depósitos o el catálogo, dime.',
            'muchas gracias' => 'Con gusto. Aquí estoy si te surge otra pregunta.',
            'ok' => 'Perfecto. ¿Te ayudo con algo más?',
            'entendido' => 'Bien. Si quieres, podemos revisar el siguiente paso.',
            'perfecto' => 'Me alegra que haya quedado claro. ¿Necesitas algo más?',
            'quien eres' => 'Soy el asistente del portal del Laboratorio de Invertebrados. Puedo orientarte sobre depósitos y consultar registros públicos del catálogo.',
            'adios' => 'Hasta luego. Puedes volver cuando necesites consultar el portal.',
            'hasta luego' => 'Hasta luego. Que tengas buen día.',
        ];
        if (! isset($respuestas[$normal])) {
            return null;
        }
        return ['texto' => $respuestas[$normal], 'opciones' => [], 'fuente' => 'conversacion', 'intent' => 'conversacion.'.$normal, 'confianza' => 'HIGH', 'confianza_valor' => 1.0];
    }
}
