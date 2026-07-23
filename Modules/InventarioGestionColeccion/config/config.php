<?php

return [
    'name' => 'InventarioGestionColeccion',

    /*
    |--------------------------------------------------------------------------
    | Geocodificación inversa (Nominatim / OpenStreetMap)
    |--------------------------------------------------------------------------
    | El User-Agent es OBLIGATORIO por la política de uso de Nominatim y debe
    | identificar a la aplicación. NO usar dominios/emails placeholder como
    | "example.org": Nominatim los rechaza con HTTP 403 "Access denied".
    | En producción, sobrescribe con un contacto real vía NOMINATIM_USER_AGENT.
    */
    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'HubDigital-MEPN/1.0 (Coleccion de Invertebrados MEPN)'),
        'timeout' => (int) env('NOMINATIM_TIMEOUT', 10),
        'cache_days' => (int) env('NOMINATIM_CACHE_DAYS', 30),
    ],
];
