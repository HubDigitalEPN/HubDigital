<?php

return [
    'java_binary' => env('JAVA_BINARY', 'java'),
    'java_timeout' => (int) env('JAVA_SIGNATURE_TIMEOUT', 60),
    'java_signature_jar' => env('JAVA_SIGNATURE_JAR', base_path('resources/bin/hubdigital-pdf-signature.jar')),
    'java_signature_trust_dir' => env('JAVA_SIGNATURE_TRUST_DIR', base_path('resources/signature-trust')),
    'render_dpi' => 110,
    'max_pages' => (int) env('FIRMA_MAX_PAGES', 40),
    'max_page_points' => (int) env('FIRMA_MAX_PAGE_POINTS', 1440),
    'max_render_pixels' => (int) env('FIRMA_MAX_RENDER_PIXELS', 100000000),
];
