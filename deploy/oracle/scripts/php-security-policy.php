<?php

declare(strict_types=1);

// Versiones estables soportadas por HubDigital con ambos parches PHP2Shell.
// PHP 8.3 corregido sigue fuera del requisito de plataforma de la aplicación.
return static function (string $version): bool {
    if (! preg_match('/\A8\.(4|5)\.\d+\z/', $version, $matches)) {
        return false;
    }

    return version_compare($version, $matches[1] === '4' ? '8.4.27' : '8.5.12', '>=');
};
