<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mensajes de autenticación
|--------------------------------------------------------------------------
|
| Los muestra Fortify al fallar el inicio de sesión o al confirmar la
| contraseña. Sin este archivo el idioma cae al inglés del framework.
|
*/

return [
    'failed' => 'Las credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña es incorrecta.',
    'throttle' => 'Demasiados intentos de inicio de sesión. Vuelve a intentarlo en :seconds segundos.',
];
