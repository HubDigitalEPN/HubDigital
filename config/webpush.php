<?php

use NotificationChannels\WebPush\PushSubscription;

return [
    'vapid' => [
        'subject' => env('VAPID_SUBJECT'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'pem_file' => env('VAPID_PEM_FILE'),
    ],

    'model' => PushSubscription::class,
    'table_name' => env('WEBPUSH_DB_TABLE', 'push_subscriptions'),
    'database_connection' => env('WEBPUSH_DB_CONNECTION', env('DB_CONNECTION', 'pgsql')),
    'automatic_padding' => true,
    // Conservar el envío pendiente durante una semana si el equipo está apagado.
    // FCM admite como máximo cuatro semanas; el historial de la campana es independiente.
    'ttl' => max(60, min(2419200, (int) env('WEBPUSH_TTL', 604800))),
    'client_options' => [
        'timeout' => 10,
        'allow_redirects' => false,
    ],

    // Evita usar una suscripción manipulada como destino SSRF. Los sufijos
    // adicionales deben revisarse antes de agregarlos por configuración.
    'allowed_endpoint_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'WEBPUSH_ALLOWED_ENDPOINT_HOSTS',
            'fcm.googleapis.com,updates.push.services.mozilla.com,push.services.mozilla.com,web.push.apple.com,.notify.windows.com'
        )),
    ))),
];
