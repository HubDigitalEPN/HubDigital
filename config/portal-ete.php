<?php

return [
    // ETE escucha sólo en loopback; el navegador siempre pasa por Laravel.
    'url' => env('PORTAL_ETE_URL', 'http://127.0.0.1:8001'),
    'timeout' => 20,
];
