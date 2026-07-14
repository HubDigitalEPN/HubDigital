<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

final class ChatBotMensajes
{
    public const string FUERA_DE_DOMINIO = 'Solo puedo responder preguntas sobre la colección entomológica del laboratorio. ¿Deseas consultar algo sobre nuestros especímenes?';

    public const string SIN_RESULTADOS = 'No encontré especímenes en la colección que coincidan con tu consulta. ¿Puedes reformularla?';

    private function __construct() {}
}
