<?php

return [
    // El agente vive en HubDigital; no necesita un asistente alojado ni herramientas de pago.
    'enabled' => env('IA_HIBRIDA_ENABLED', false),
    'openai_key' => env('OPENAI_API_KEY', ''),
    'organization' => env('OPENAI_ORGANIZATION', ''),
    'project' => env('OPENAI_PROJECT', ''),
    'model' => 'gpt-6.1-terra',
    'reasoning_effort' => 'low',
    // Ruta persistente privada, compartida entre workers y releases del mismo entorno.
    'ledger' => env('IA_PRESUPUESTO_PATH', env('APP_ENV') === 'production'
        ? '/var/lib/hubdigital/ia/presupuesto.json' : storage_path('app/private/ia/presupuesto.json')),
    'max_output_tokens' => 1600,
    'timeout_seconds' => 12,
];
