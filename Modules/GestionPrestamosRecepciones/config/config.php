<?php

return [
    'name' => 'GestionPrestamosRecepciones',

    /*
     * Ruta absoluta al binario 'pdfsig' (poppler-utils) usado para validar firmas
     * electrónicas de PDFs. En Linux (producción) suele resolverse solo vía PATH
     * (/usr/bin/pdfsig). En macOS con Herd/Valet el proceso php-fpm no hereda el PATH
     * de Homebrew, por lo que conviene fijarlo con PDFSIG_BINARY=/opt/homebrew/bin/pdfsig.
     */
    'pdfsig_binary' => env('PDFSIG_BINARY'),

    /*
     * Validación taxonómica contra la API Species Match de GBIF.
     *
     * Umbrales de confianza (0–100) por debajo de los cuales un candidato de
     * corrección NO se sugiere al depositante. Se distingue por tipo de match:
     * un candidato EXACT (coincidencia exacta con un nombre ya catalogado) es más
     * confiable que uno FUZZY (aproximado), por eso admite un umbral más bajo.
     * La sugerencia nunca se auto-aplica: el depositante la revisa y confirma,
     * así que el costo de un falso positivo es bajo (se ignora y se justifica).
     */
    'gbif' => [
        'umbral_confianza_fuzzy' => (int) env('GBIF_UMBRAL_CONFIANZA_FUZZY', 85),
        'umbral_confianza_exact' => (int) env('GBIF_UMBRAL_CONFIANZA_EXACT', 70),
    ],

    /*
     * Firma criptográfica automática del acta al aprobarla el curador (pyHanko).
     */
    'firma' => [
        // Ruta o nombre del ejecutable pyhanko (fallback si no está en PATH).
        'binario' => env('PYHANKO_BIN', 'pyhanko'),

        // Python con la librería `cryptography` (para leer .p12 legacy RC2). En dev
        // apunta al python del venv; en prod, al que tiene pyhanko instalado.
        'python_bin' => env('PYTHON_BIN', 'python'),

        // Campo de firma visible: "pagina/x1,y1,x2,y2/Nombre". La PÁGINA se recalcula
        // en runtime a la última del PDF (donde acta-documento pone la línea del
        // curador), así que el número aquí es solo placeholder; ajustar x1,y1,x2,y2.
        'campo' => env('FIRMA_CAMPO', '1/50,50,300,120/FirmaCurador'),

        // Servidor de sellado de tiempo (TSA). Vacío => PAdES-B-B sin sello de tiempo.
        'tsa_url' => env('FIRMA_TSA_URL'),
    ],
];
