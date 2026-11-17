<?php

declare(strict_types=1);

namespace App\Support;

final class DisponibilidadChatPortal
{
    public static function habilitado(): bool
    {
        if (! config('chatbot.enabled', true)) return false;
        $host = request()->getHost();
        $url = rtrim((string) config('app.url'), '/');
        // Las pruebas aisladas usan loopback; nunca habilitan el dominio DEV.
        if (config('app.env') === 'testing' && in_array($host, ['localhost', '127.0.0.1'], true)
            && in_array(parse_url($url, PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) return true;
        return config('app.env') === 'production' && $url === 'https://labinvepn.org' && $host === 'labinvepn.org';
    }
}
