<?php

return [
    // Sólo para un envío de QA en localhost; nunca redirige correo en OCI.
    'qa_deposit_id' => env('HUBDIGITAL_QA_DEPOSIT_ID', ''),
    'qa_mail_to' => env('HUBDIGITAL_QA_MAIL_TO', ''),

    // Durante restauracion, pruebas internas y mediciones no se permite enviar
    // notificaciones fuera de HubDigital. El canal `database` se conserva para
    // no ocultar eventos ni alterar la cola; correo, web push y otros canales
    // de Notification se bloquean desde AppServiceProvider.
    'validation_mode' => filter_var(env('HUBDIGITAL_VALIDATION_MODE', false), FILTER_VALIDATE_BOOL),

    // Permite únicamente los correos de alta imprescindibles mientras la
    // instancia OCI continúa aislando el resto de notificaciones externas.
    'allow_auth_emails_during_validation' => filter_var(
        env('HUBDIGITAL_ALLOW_AUTH_EMAILS', false),
        FILTER_VALIDATE_BOOL,
    ),

    // El worker de validación sólo consume esta cola. Los trabajos restaurados
    // de `default` se conservan para revisión y nunca se ejecutan por accidente
    // al activar localmente la release.
    'validation_queue' => env('HUBDIGITAL_VALIDATION_QUEUE', 'validation'),

    // En Oracle apunta a /run/hubdigital/document-processing (tmpfs). El
    // fallback facilita pruebas locales, pero el perfil Oracle lo reemplaza.
    'temporary_directory' => env(
        'HUBDIGITAL_TEMPORARY_DIRECTORY',
        storage_path('app/private/tmp/document-processing'),
    ),
    // Reserva mínima compartida que se exige antes de materializar un PDF/R2.
    // No es una cuota del kernel: los límites cgroup de cada servicio son la
    // segunda barrera y se deben medir en la VM real.
    'temporary_min_free_bytes' => (int) env('HUBDIGITAL_TEMPORARY_MIN_FREE_BYTES', 64 * 1024 * 1024),
];
