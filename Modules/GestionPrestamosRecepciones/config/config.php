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
];
