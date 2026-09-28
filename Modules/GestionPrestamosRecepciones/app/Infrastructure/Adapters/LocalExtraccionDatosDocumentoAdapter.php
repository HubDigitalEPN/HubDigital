<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DirectorioTemporalHubDigital;
use Modules\GestionPrestamosRecepciones\Application\Ports\ExtraccionDatosDocumentoPort;
use Modules\GestionPrestamosRecepciones\Domain\Services\AnalizadorDocumentoAmbiental;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DatosIntegradosDocumento;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\ValidadorPdfDeposito;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;

/**
 * Extractor privado y gratuito: texto embebido + OCR Tesseract 5 (spa/eng).
 * Los resultados son propuestas trazables que el ciudadano debe confirmar.
 */
final class LocalExtraccionDatosDocumentoAdapter implements ExtraccionDatosDocumentoPort
{
    private AnalizadorDocumentoAmbiental $analizador;

    private AlmacenamientoDepositos $almacenamiento;

    public function __construct(
        ?AnalizadorDocumentoAmbiental $analizador = null,
        ?AlmacenamientoDepositos $almacenamiento = null,
    )
    {
        $this->analizador = $analizador ?? new AnalizadorDocumentoAmbiental;
        $this->almacenamiento = $almacenamiento ?? app(AlmacenamientoDepositos::class);
    }

    public function extraerDatos(array $documentos): DatosIntegradosDocumento
    {
        $valores = [];
        $metadata = ['motor' => 'pdftotext+tesseract-5', 'requiere_revision_humana' => true, 'documentos' => [], 'campos' => []];

        foreach ($documentos as $nombre => $ruta) {
            $lectura = $this->leerTexto($ruta);
            $texto = $lectura['texto'];
            $motor = $lectura['motor'];
            $analisis = $this->analizador->analizar($texto);
            $tipoEsperado = $this->analizador->tipoEsperadoParaNombre($nombre);
            $contenidoCompatible = $tipoEsperado === null
                || ($analisis['tipo_detectado'] ?? null) === $tipoEsperado;
            $contenidoAutocompletable = $contenidoCompatible
                && ($analisis['autocompletado_habilitado'] ?? false) === true;
            $metadata['documentos'][$nombre] = [
                'motor' => $motor,
                'caracteres' => mb_strlen($texto),
                'texto_sha256' => hash('sha256', $texto),
                'procesado_localmente' => true,
                'modelo_ocr' => $lectura['uso_ocr'] ? 'Tesseract 5 spa+eng' : null,
                'paginas' => $lectura['paginas'],
                'procesamiento_parcial' => $lectura['procesamiento_parcial'],
                'contenido_compatible_con_casilla' => $contenidoCompatible,
                'contenido_autocompletable' => $contenidoAutocompletable,
                'analisis' => $analisis,
            ];

            $estructurados = [
                'nroPermisoRecoleccion' => $analisis['numero_autorizacion'] ?? null,
                'nroPermisoMovilizacion' => ($analisis['tipo_detectado'] ?? null) === AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION
                    ? ($analisis['numero_documento'] ?? null)
                    : null,
                'grupoAnimal' => ($analisis['grupos_biologicos'] ?? []) !== []
                    ? implode(', ', $analisis['grupos_biologicos'])
                    : null,
                'localidad' => $analisis['origen'] ?? null,
                'origenDonacion' => null,
                'nombreInvestigador' => $analisis['titular'] ?? null,
                'nroIndividuos' => isset($analisis['numero_individuos']) ? (string) $analisis['numero_individuos'] : null,
                'nroMorfoespecies' => isset($analisis['numero_morfoespecies']) ? (string) $analisis['numero_morfoespecies'] : null,
                'nroLotes' => isset($analisis['numero_lotes']) ? (string) $analisis['numero_lotes'] : null,
            ];

            foreach ($estructurados as $campo => $valor) {
                if (! $contenidoAutocompletable) {
                    continue;
                }
                if (($valores[$campo] ?? null) === null && is_string($valor) && trim($valor) !== '') {
                    $campoAnalisis = match ($campo) {
                        'nroPermisoRecoleccion' => 'numero_autorizacion',
                        'nroPermisoMovilizacion' => 'numero_documento',
                        'grupoAnimal' => 'grupos_biologicos',
                        'localidad' => 'origen',
                        'nombreInvestigador' => 'titular',
                        'nroIndividuos' => 'numero_individuos',
                        'nroMorfoespecies' => 'numero_morfoespecies',
                        'nroLotes' => 'numero_lotes',
                        default => null,
                    };
                    if ($campoAnalisis === null || ! $this->analizador->campoTieneEvidenciaSuficiente($analisis, $campoAnalisis)) {
                        continue;
                    }
                    $valores[$campo] = trim($valor);
                    $evidencia = $analisis['evidencias_campos'][$campoAnalisis] ?? null;
                    $metadata['campos'][$campo] = [
                        'confianza' => $analisis['confianzas_campos'][$campoAnalisis] ?? 0.0,
                        'fuente' => $nombre,
                        'motor' => $motor,
                        'metodo' => 'clasificador_ambiental',
                        'evidencia' => $evidencia,
                        'pagina' => $this->paginaDeEvidencia($evidencia, $lectura['paginas']),
                        'requiere_confirmacion_humana' => true,
                    ];
                }
            }

            foreach ($tipoEsperado === null ? $this->extraerCampos($texto) : [] as $campo => $hallazgo) {
                if (($valores[$campo] ?? null) === null && $hallazgo['valor'] !== null) {
                    $valores[$campo] = $hallazgo['valor'];
                    $metadata['campos'][$campo] = [
                        'confianza' => $hallazgo['confianza'],
                        'fuente' => $nombre,
                        'motor' => $motor,
                        'metodo' => 'patron_auditable',
                        'evidencia' => $hallazgo['evidencia'],
                        'requiere_confirmacion_humana' => true,
                    ];
                }
            }

            foreach ($contenidoAutocompletable ? ($analisis['codigos_muestra'] ?? []) : [] as $codigo) {
                $metadata['registros_sugeridos'][] = [
                    'recordNumber' => $codigo,
                    'researchPermit' => $analisis['numero_autorizacion'] ?? null,
                    'transportPermit' => ($analisis['tipo_detectado'] ?? null) === AnalizadorDocumentoAmbiental::GUIA_MOVILIZACION
                        ? ($analisis['numero_documento'] ?? null)
                        : null,
                    'verbatimLocality' => $analisis['origen'] ?? null,
                    'gruposBiologicos' => $analisis['grupos_biologicos'] ?? [],
                ];
            }
        }

        $metadata['registros_sugeridos'] = array_values(array_reduce(
            $metadata['registros_sugeridos'] ?? [],
            static function (array $unicos, array $registro): array {
                $codigo = (string) ($registro['recordNumber'] ?? '');
                if ($codigo !== '') {
                    $unicos[$codigo] = array_replace($unicos[$codigo] ?? [], $registro);
                }

                return $unicos;
            },
            [],
        ));

        return new DatosIntegradosDocumento(
            nroPermisoRecoleccion: $valores['nroPermisoRecoleccion'] ?? null,
            nroPermisoMovilizacion: $valores['nroPermisoMovilizacion'] ?? null,
            grupoAnimal: $valores['grupoAnimal'] ?? null,
            provinciaOrigen: null,
            localidad: $valores['localidad'] ?? null,
            origenDonacion: $valores['origenDonacion'] ?? null,
            nombreInvestigador: $valores['nombreInvestigador'] ?? null,
            nroIndividuos: $valores['nroIndividuos'] ?? null,
            nroMorfoespecies: $valores['nroMorfoespecies'] ?? null,
            nroLotes: $valores['nroLotes'] ?? null,
            metadatosExtraccion: $metadata,
        );
    }

    /**
     * @return array{
     *     texto: string,
     *     motor: string,
     *     uso_ocr: bool,
     *     procesamiento_parcial: bool,
     *     paginas: list<array{numero: int, metodo: string, caracteres: int, estado: string}>
     * }
     */
    private function leerTexto(string $ruta): array
    {
        $copia = $this->almacenamiento->copiaLocal($ruta);
        try {
            return $this->leerArchivoLocal($copia->ruta());
        } finally {
            $copia->limpiar();
        }
    }

    /** Lee el temporal de una carga sin escribirlo en el almacenamiento del expediente. */
    public function leerArchivoLocal(string $archivo): array
    {
        if (! is_file($archivo)) {
            return [
                'texto' => '',
                'motor' => 'archivo-no-encontrado',
                'uso_ocr' => false,
                'procesamiento_parcial' => true,
                'paginas' => [],
            ];
        }

        $totalPaginas = $this->validarPdfSeguro($archivo);
        if (PHP_OS_FAMILY === 'Windows') {
            $paginas = [];
            $fragmentos = [];
            $proceso = new Process([
                (string) config('firma-electronica.java_binary', 'java'),
                '-Djava.awt.headless=true', '-Xmx384m', '-jar',
                (string) config('firma-electronica.java_signature_jar'), 'text', '--paths-stdin',
            ]);
            $proceso->setInput($archivo);
            $proceso->setEnv(DirectorioTemporalHubDigital::entornoProcesos());
            $proceso->setTimeout(30);
            $proceso->mustRun();
            $lectura = json_decode($proceso->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($lectura['paginas'] ?? null)) {
                throw new \RuntimeException('Java no pudo leer las páginas del PDF.');
            }
            foreach ($lectura['paginas'] as $pagina) {
                $numero = (int) $pagina['numero'];
                $texto = trim((string) $pagina['texto']);
                $paginas[] = [
                    'numero' => $numero,
                    'metodo' => 'java-pdfbox',
                    'caracteres' => mb_strlen($texto),
                    'estado' => $texto === '' ? 'sin_texto' : 'texto_nativo',
                ];
                if ($texto !== '') {
                    $fragmentos[] = sprintf('[Página %d · java-pdfbox]%s%s', $numero, PHP_EOL, $texto);
                }
            }

            return [
                'texto' => implode(PHP_EOL.PHP_EOL, $fragmentos),
                'motor' => 'java-pdfbox',
                'uso_ocr' => false,
                'procesamiento_parcial' => count($paginas) !== $totalPaginas,
                'paginas' => $paginas,
            ];
        }
        $minimo = max(1, (int) config('document-extraction.minimum_text_length', 80));
        $paginas = [];
        $fragmentos = [];
        $usoOcr = false;
        $procesamientoParcial = false;

        for ($numero = 1; $numero <= $totalPaginas; $numero++) {
            $textoNativo = trim($this->ejecutar(new Process([
                'pdftotext', '-layout', '-f', (string) $numero, '-l', (string) $numero, $archivo, '-',
            ])));
            $metodo = 'pdftotext';
            $textoPagina = $textoNativo;
            $estado = 'texto_nativo';

            if ($this->textoInsuficiente($textoNativo, $minimo)) {
                $ocr = trim($this->aplicarOcrPagina($archivo, $numero));
                $usoOcr = true;
                if ($ocr !== '') {
                    $textoPagina = $ocr;
                    $metodo = 'tesseract-5-spa-eng';
                    $estado = 'ocr';
                } elseif ($textoNativo === '') {
                    $estado = 'sin_texto';
                } else {
                    $estado = 'texto_nativo_insuficiente';
                    $procesamientoParcial = true;
                }
            }

            if ($textoPagina !== '') {
                // La marca conserva la procedencia para que cualquier evidencia
                // extraída pueda asociarse al folio y método real de lectura.
                $fragmentos[] = sprintf('[Página %d · %s]%s%s', $numero, $metodo, PHP_EOL, $textoPagina);
            }
            $paginas[] = [
                'numero' => $numero,
                'metodo' => $metodo,
                'caracteres' => mb_strlen($textoPagina),
                'estado' => $estado,
            ];
        }

        $texto = trim(implode(PHP_EOL.PHP_EOL, $fragmentos));
        if ($texto === '') {
            try {
                $texto = (new Parser)->parseFile($archivo)->getText();
            } catch (\Throwable $e) {
                Log::warning('No se pudo leer PDF para autocompletado', ['error' => $e->getMessage()]);
            }
        }

        return [
            'texto' => trim($texto),
            'motor' => $usoOcr ? 'pdftotext+tesseract-5' : 'pdftotext',
            'uso_ocr' => $usoOcr,
            'procesamiento_parcial' => $procesamientoParcial,
            'paginas' => $paginas,
        ];
    }

    /**
     * Rechaza documentos patológicos antes de entregarlos a Poppler/Tesseract.
     * El peso del archivo no limita el tamaño descomprimido de una página PDF.
     */
    private function validarPdfSeguro(string $archivo): int
    {
        $resultado = app(ValidadorPdfDeposito::class)->inspeccionar($archivo, [
            'max_pages' => max(1, (int) config('document-extraction.ocr_max_pages', 25)),
            'max_page_points' => (int) config('document-extraction.max_page_points', 1440),
            'max_render_pixels' => (int) config('document-extraction.max_render_pixels', 120_000_000),
            'render_dpi' => (int) config('document-extraction.ocr_dpi', 200),
        ]);

        return (int) ($resultado['paginas'] ?? 0);
    }

    private function aplicarOcrPagina(string $archivo, int $numeroPagina): string
    {
        $directorio = DirectorioTemporalHubDigital::crear('ocr', 32 * 1024 * 1024);
        $prefijo = $directorio.DIRECTORY_SEPARATOR.'pagina';

        try {
            $this->ejecutar(new Process([
                'pdftoppm', '-f', (string) $numeroPagina, '-l', (string) $numeroPagina,
                '-r', (string) config('document-extraction.ocr_dpi', 200), '-jpeg', $archivo, $prefijo,
            ], timeout: 180));

            $paginas = File::glob($prefijo.'-*.jpg');
            sort($paginas, SORT_NATURAL);
            $texto = [];
            foreach ($paginas as $pagina) {
                $texto[] = $this->ejecutar(new Process([
                    'tesseract', $pagina, 'stdout', '-l', (string) config('document-extraction.ocr_languages', 'spa+eng'), '--psm', '6',
                ], timeout: 90));
            }

            return trim(implode("\n", $texto));
        } finally {
            DirectorioTemporalHubDigital::eliminar($directorio);
        }
    }

    private function ejecutar(Process $process): string
    {
        try {
            $process->run();
            return $process->isSuccessful() ? $process->getOutput() : '';
        } catch (\Throwable $e) {
            Log::notice('Motor local de documentos no disponible', ['error' => $e->getMessage()]);
            return '';
        }
    }

    private function textoInsuficiente(string $texto, int $minimo): bool
    {
        $legible = preg_replace('/[^\p{L}\p{N}]+/u', '', $texto) ?? '';

        return mb_strlen($legible) < $minimo;
    }

    /**
     * @param list<array{numero: int, metodo: string, caracteres: int, estado: string}> $paginas
     */
    private function paginaDeEvidencia(mixed $evidencia, array $paginas): ?int
    {
        // El analizador ambiental conserva evidencia estructurada para que el
        // fragmento, patrón y versión de reglas permanezcan auditables. Los
        // extractores genéricos todavía pueden entregar una cadena simple.
        $fragmento = is_array($evidencia)
            ? ($evidencia['fragmento'] ?? null)
            : $evidencia;

        if (is_string($fragmento) && preg_match('/\[Página\s+(\d+)\s+·/u', $fragmento, $coincidencia) === 1) {
            return (int) $coincidencia[1];
        }

        return count($paginas) === 1 ? $paginas[0]['numero'] : null;
    }

    /** @return array<string, array{valor: ?string, confianza: float, evidencia: ?string}> */
    private function extraerCampos(string $texto): array
    {
        $texto = preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto;

        return [
            'nroPermisoRecoleccion' => $this->buscar($texto, [
                '/(?:autorizaci[oó]n|permiso)(?:\s+de)?\s+recolecci[oó]n[^\r\n:]{0,35}[:#\-]?\s*([A-Z0-9][A-Z0-9.\/_-]{5,})/iu',
                '/\b((?:MAATE|MAE)[A-Z0-9.\/_-]{5,})\b/iu',
            ]),
            'nroPermisoMovilizacion' => $this->buscar($texto, [
                '/(?:gu[ií]a|permiso)(?:\s+de)?\s+movilizaci[oó]n[^\r\n:]{0,35}[:#\-]?\s*([A-Z0-9][A-Z0-9.\/_-]{5,})/iu',
            ]),
            'grupoAnimal' => $this->buscar($texto, [
                '/(?:grupo(?:\s+animal)?|grupo\s+taxon[oó]mico)\s*[:\-]\s*([^\r\n]{3,90})/iu',
                '/\b(moluscos?|insectos?|crust[aá]ceos?|an[eé]lidos?|ar[aá]cnidos?)\b/iu',
            ]),
            'localidad' => $this->buscar($texto, [
                '/(?:localidad|sitio\s+de\s+colecta)\s*[:\-]\s*([^\r\n]{3,120})/iu',
            ]),
            'origenDonacion' => $this->buscar($texto, [
                '/(?:origen|procedencia)(?:\s+de\s+(?:la\s+)?donaci[oó]n|\s+de\s+los\s+espec[ií]menes)?\s*[:\-]\s*([^\r\n]{3,180})/iu',
            ]),
            'nombreInvestigador' => $this->buscar($texto, [
                '/(?:titular|investigador(?:a)?|depositante|nombre(?:\s+completo)?)\s*[:\-]\s*([\p{L}][\p{L} .\'-]{4,100})/iu',
            ]),
        ];
    }

    /** @param list<string> $patrones @return array{valor: ?string, confianza: float, evidencia: ?string} */
    private function buscar(string $texto, array $patrones): array
    {
        foreach ($patrones as $indice => $patron) {
            if (preg_match($patron, $texto, $m, PREG_OFFSET_CAPTURE) === 1) {
                $capturado = (string) $m[1][0];
                $valor = trim(preg_replace('/\s+/u', ' ', $capturado) ?? $capturado, " \t\n\r\0\x0B.,;:");
                $inicio = max(0, (int) $m[0][1] - 60);
                $fragmento = mb_strcut($texto, $inicio, 220, 'UTF-8');

                return [
                    'valor' => $valor !== '' ? $valor : null,
                    'confianza' => $indice === 0 ? 0.9 : 0.72,
                    'evidencia' => trim(preg_replace('/\s+/u', ' ', $fragmento) ?? $fragmento),
                ];
            }
        }

        return ['valor' => null, 'confianza' => 0.0, 'evidencia' => null];
    }

}
