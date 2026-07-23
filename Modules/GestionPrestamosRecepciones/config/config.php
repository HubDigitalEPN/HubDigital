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

];
