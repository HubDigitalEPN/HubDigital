<?php

return [
    'name' => 'InventarioGestionColeccion',

    /*
    |--------------------------------------------------------------------------
    | Geocodificación inversa (Nominatim / OpenStreetMap)
    |--------------------------------------------------------------------------
    | El User-Agent es OBLIGATORIO por la política de uso de Nominatim y debería
    | identificar a la institución con un contacto. Cámbialo por env en producción.
    */
    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'HubDigital MEPN (Colección Entomológica) - contacto@example.org'),
        'timeout' => (int) env('NOMINATIM_TIMEOUT', 10),
        'cache_days' => (int) env('NOMINATIM_CACHE_DAYS', 30),
    ],
];
