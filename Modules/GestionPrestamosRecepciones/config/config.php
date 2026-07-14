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
