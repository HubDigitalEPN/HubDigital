<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Enums\Lab;
use Modules\GestionPrestamosRecepciones\Application\Ports\ExtraccionDatosDocumentoPort;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DatosIntegradosDocumento;
use Modules\GestionPrestamosRecepciones\Infrastructure\Exceptions\ModeloIANoDisponibleException;
use Smalot\PdfParser\Parser;

final class GroqExtraccionDatosDocumentoAdapter implements ExtraccionDatosDocumentoPort
{
    private const TIPO_SOLICITUD = 'solicitud';

    private const TIPO_AUTORIZACION = 'autorizacion';

    private const TIPO_MOVILIZACION = 'movilizacion';

    private const TIPO_CESION = 'cesion';

    private const TIPO_DESCONOCIDO = 'desconocido';

    /** @var string[] */
    private const PROVINCIAS_ECUADOR = [
        'Azuay', 'Bolívar', 'Cañar', 'Carchi', 'Chimborazo', 'Cotopaxi',
        'El Oro', 'Esmeraldas', 'Galápagos', 'Guayas', 'Imbabura', 'Loja',
        'Los Ríos', 'Manabí', 'Morona Santiago', 'Napo', 'Orellana',
        'Pastaza', 'Pichincha', 'Santa Elena', 'Santo Domingo de los Tsáchilas',
        'Sucumbíos', 'Tungurahua', 'Zamora Chinchipe',
    ];

    public function __construct(
        private readonly string $modelo,
    ) {}

    /** @param array<string, string> $documentos [nombre => ruta] */
    public function extraerDatos(array $documentos): DatosIntegradosDocumento
    {
        $parser = new Parser;
        $acumulado = [];

        foreach ($documentos as $nombre => $ruta) {
            $texto = $this->leerPdf($parser, $nombre, $ruta);

            if ($texto === null) {
                continue;
            }

            $parcial = $this->consultarGroq($nombre, $texto);

            // Red de seguridad para el número de autorización MAE: el modelo
            // puede truncar el código. Complementamos con un regex sobre el texto
            // crudo del PDF y nos quedamos con el resultado más completo.
            if ($this->clasificarDocumento($nombre) === self::TIPO_AUTORIZACION) {
                $parcial['nroPermisoRecoleccion'] = $this->consolidarCodigoRecoleccion(
                    $this->limpiar($parcial['nroPermisoRecoleccion'] ?? null),
                    $this->extraerCodigoRecoleccionPorRegex($texto),
                );
            }

            // Conservar el primer valor no nulo encontrado para cada campo.
            foreach ($parcial as $campo => $valor) {
                $campo = (string) $campo;
                if (! array_key_exists($campo, $acumulado) && $this->limpiar($valor) !== null) {
                    $acumulado[$campo] = $valor;
                }
            }
        }

        return new DatosIntegradosDocumento(
            nroPermisoRecoleccion: $this->limpiar($acumulado['nroPermisoRecoleccion'] ?? null),
            nroPermisoMovilizacion: $this->limpiar($acumulado['nroPermisoMovilizacion'] ?? null),
            grupoAnimal: $this->limpiar($acumulado['grupoAnimal'] ?? null),
            provinciaOrigen: $this->limpiar($acumulado['provinciaOrigen'] ?? null),
            localidad: $this->limpiar($acumulado['localidad'] ?? null),
            origenDonacion: $this->limpiar($acumulado['origenDonacion'] ?? null),
            nombreInvestigador: $this->limpiar($acumulado['nombreInvestigador'] ?? null),
        );
    }

    // ── Lectura de PDF ───────────────────────────────────────────────────────────

    private function leerPdf(Parser $parser, string $nombre, string $ruta): ?string
    {
        $rutaAbsoluta = Storage::disk('public')->path($ruta);

        if (! file_exists($rutaAbsoluta)) {
            Log::warning('GroqExtraccion: archivo no encontrado', [
                'documento' => $nombre,
                'ruta' => $ruta,
            ]);

            return null;
        }

        try {
            $texto = trim($parser->parseFile($rutaAbsoluta)->getText());

            if (empty($texto)) {
                Log::warning('GroqExtraccion: PDF sin texto extraíble (posible escaneo sin OCR)', [
                    'documento' => $nombre,
                ]);

                return null;
            }

            return mb_substr($texto, 0, $this->limiteCaracteres($nombre), 'UTF-8');
        } catch (\Throwable $e) {
            Log::warning('GroqExtraccion: no se pudo parsear PDF', [
                'documento' => $nombre,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function limiteCaracteres(string $nombreDocumento): int
    {
        return match ($this->clasificarDocumento($nombreDocumento)) {
            self::TIPO_AUTORIZACION => 5000,
            self::TIPO_MOVILIZACION => 4000,
            default => 3000,
        };
    }

    // ── Consulta a Groq ──────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function consultarGroq(string $nombreDocumento, string $texto): array
    {
        $resultado = $this->intentarExtraccion($nombreDocumento, $texto);

        // Si la extracción devolvió todo null para un documento reconocido,
        // reintentamos una vez. Un segundo intento a menudo resuelve fallos transitorios.
        if ($this->esDocumentoReconocido($nombreDocumento) && $this->todoNulo($resultado)) {
            Log::info('GroqExtraccion: reintentando extracción', ['documento' => $nombreDocumento]);
            $resultado = $this->intentarExtraccion($nombreDocumento, $texto);
        }

        return $resultado;
    }

    /** @return array<string, mixed> */
    private function intentarExtraccion(string $nombreDocumento, string $texto): array
    {
        try {
            set_time_limit(180);

            $tipo = $this->clasificarDocumento($nombreDocumento);
            $instrucciones = $this->construirInstrucciones($tipo);
            $prompt = $this->construirPrompt($nombreDocumento, $texto);

            Log::debug('GroqExtraccion: enviando prompt', [
                'documento' => $nombreDocumento,
                'tipo' => $tipo,
            ]);

            $agente = new ExtractorDocumentoAgent(
                instrucciones: $instrucciones,
            );

            $respuesta = $agente->prompt(
                $prompt,
                provider: Lab::Groq,
                model: $this->modelo,
                timeout: 120,
            );

            $resultado = $this->extraerJsonDeRespuesta($respuesta);
            $resultado = $this->validar($resultado);

            Log::debug('GroqExtraccion: respuesta recibida', [
                'documento' => $nombreDocumento,
                'resultado' => $resultado,
            ]);

            return $resultado;
        } catch (ConnectionException $e) {
            throw ModeloIANoDisponibleException::porConexion($e->getMessage());
        } catch (\Throwable $e) {
            Log::warning('GroqExtraccion: error al contactar GROQ', [
                'documento' => $nombreDocumento,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    // ── Parsing de respuesta ─────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function extraerJsonDeRespuesta(mixed $respuesta): array
    {
        $texto = trim((string) $respuesta);

        // Path 1: JSON directo.
        $resultado = json_decode($texto, true);
        if (is_array($resultado)) {
            return $resultado;
        }

        // Path 2: JSON envuelto en bloque markdown ```json ... ```.
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $texto, $m)) {
            $resultado = json_decode($m[1], true);
            if (is_array($resultado)) {
                Log::info('GroqExtraccion: JSON extraído de bloque markdown');

                return $resultado;
            }
        }

        // Path 3: Primer objeto JSON encontrado en texto libre.
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/', $texto, $m)) {
            $resultado = json_decode($m[0], true);
            if (is_array($resultado)) {
                Log::info('GroqExtraccion: JSON extraído por regex de texto libre');

                return $resultado;
            }
        }

        Log::warning('GroqExtraccion: no se pudo extraer JSON de la respuesta', [
            'respuesta_preview' => mb_substr($texto, 0, 500, 'UTF-8'),
        ]);

        return [];
    }

    // ── Instrucciones del sistema ────────────────────────────────────────────────

    private function construirInstrucciones(string $tipo): string
    {
        $instruccionesPorTipo = $this->instruccionesPorTipo($tipo);

        return <<<INSTRUCCIONES
        Eres un extractor de datos de documentos oficiales ecuatorianos sobre especímenes de vida silvestre.

        Reglas generales:
        - Usa null para campos ausentes. Nunca cadena vacía "".
        - No inventes datos. Si el dato no aparece claramente en el texto, usa null.
        - Extrae códigos y números sin prefijos: sin "N.º", "Nro.", "N°", "No.", "N.°".

        {$instruccionesPorTipo}
        INSTRUCCIONES;
    }

    private function construirPrompt(string $nombreDocumento, string $texto): string
    {
        return <<<PROMPT
        Documento: "{$nombreDocumento}"

        Texto del documento:
        {$texto}

        Extrae los datos solicitados del texto anterior.
        PROMPT;
    }

    private function instruccionesPorTipo(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_SOLICITUD => $this->instruccionesSolicitud(),
            self::TIPO_AUTORIZACION => $this->instruccionesAutorizacion(),
            self::TIPO_MOVILIZACION => $this->instruccionesMovilizacion(),
            self::TIPO_CESION => $this->instruccionesCesion(),
            default => <<<'INST'
            Este tipo de documento no requiere extracción de datos.
            Devuelve null para todos los campos.
            INST,
        };
    }

    private function instruccionesSolicitud(): string
    {
        return <<<'INST'
        Instrucciones para este documento (carta de solicitud):

        Extrae SOLO estos campos:

        - "nombreInvestigador": Nombre completo de quien solicita o firma.
          En cartas suele aparecer como "Yo, [Nombre]" o junto a "suscrito" / "suscrita".
          Extrae solo el nombre completo, sin cédula ni cargo.
          null si no aparece.

        - "grupoAnimal": Grupo taxonómico general de los especímenes mencionados.
          Ejemplos: "Macroinvertebrados acuáticos", "Lepidoptera", "Coleoptera", "Herpetofauna".
          Si hay varios grupos, elige el primero mencionado. null si no aparece.

        Para todos los demás campos devuelve null.

        --- Ejemplos de extracción correcta ---

        Texto: "...Yo, María José Fernández Torres, con cédula 1712345678, solicito el depósito de especímenes de Lepidoptera recolectados en..."
        Resultado: {"nroPermisoRecoleccion": null, "nroPermisoMovilizacion": null, "grupoAnimal": "Lepidoptera", "provinciaOrigen": null, "localidad": null, "origenDonacion": null, "nombreInvestigador": "María José Fernández Torres"}

        Texto: "...El suscrito Luis Gómez, docente investigador, solicita depositar muestras de macroinvertebrados acuáticos y coleópteros..."
        Resultado: {"nroPermisoRecoleccion": null, "nroPermisoMovilizacion": null, "grupoAnimal": "Macroinvertebrados acuáticos", "provinciaOrigen": null, "localidad": null, "origenDonacion": null, "nombreInvestigador": "Luis Gómez"}
        INST;
    }

    private function instruccionesAutorizacion(): string
    {
        return <<<'INST'
        Instrucciones para este documento (autorización de recolección MAE):

        Extrae SOLO estos campos:

        - "nroPermisoRecoleccion": Código alfanumérico COMPLETO de la autorización emitida por el MAE.
          Suele aparecer precedido por un prefijo tipo "N.º", "N.°", "Nro." o "No.".
          El código está formado por una secuencia contigua de segmentos alfanuméricos unidos
          por guiones "-", barras "/" y espacios, que pueden incluir acrónimos institucionales
          en mayúsculas. La estructura puede variar entre documentos: no asumas un formato fijo.
          Captura TODA la cadena desde el inicio del código hasta donde termina la secuencia
          contigua de letras, dígitos, guiones, barras y espacios entre acrónimos. No la cortes
          en el primer espacio ni en el primer acrónimo.
          No incluyas el prefijo "N.º", "N.°", "Nro." o "No.".
          No incluyas texto que aparezca después del código (nombres, fechas, frases, títulos).
          Si no aparece, usa null.

        - "grupoAnimal": Grupo taxonómico principal autorizado para recolección.
          Puede estar en una tabla (Entomofauna, Macroinvertebrados, Herpetofauna, etc.).
          Si hay múltiples grupos, extrae el primero listado. null si no aparece.

        - "provinciaOrigen": Provincia ecuatoriana de recolección.
          Suele aparecer cerca de "Área geográfica" o "Provincia" dentro del cuerpo del documento.
          null si no aparece.

        - "localidad": Lugar, bloque, sector o área específica de recolección.
          Puede ser un nombre de reserva, bloque de exploración, sector, etc.
          null si no aparece.

        Para todos los demás campos devuelve null.

        --- Ejemplos de extracción correcta ---

        Texto: "...AUTORIZACIÓN DE INVESTIGACIÓN CIENTÍFICA N.º 007-2023-IC-FAU-DPAO-MAE... se autoriza la recolección de fauna silvestre (Entomofauna)... Área geográfica: Provincia de Sucumbíos... Bloque 56 Lago Agrio..."
        Resultado: {"nroPermisoRecoleccion": "007-2023-IC-FAU-DPAO-MAE", "nroPermisoMovilizacion": null, "grupoAnimal": "Entomofauna", "provinciaOrigen": "Sucumbíos", "localidad": "Bloque 56 Lago Agrio", "origenDonacion": null, "nombreInvestigador": null}

        Texto: "...Autorización Nro. 012-2024-ENT-DPAN-MAE... recolección de macroinvertebrados acuáticos... Provincia: Napo..."
        Resultado: {"nroPermisoRecoleccion": "012-2024-ENT-DPAN-MAE", "nroPermisoMovilizacion": null, "grupoAnimal": "Macroinvertebrados acuáticos", "provinciaOrigen": "Napo", "localidad": null, "origenDonacion": null, "nombreInvestigador": null}
        INST;
    }

    private function instruccionesMovilizacion(): string
    {
        return <<<'INST'
        Instrucciones para este documento (guía de movilización):

        Extrae SOLO estos campos:

        - "nroPermisoMovilizacion": Número principal de la GUÍA de movilización.
          Aparece en el encabezado del documento junto al título, bajo la etiqueta "Nro.".
          IMPORTANTE: NO es el campo "Autorización Nro." — ese es el número de un documento
          relacionado diferente. Ignóralo para este campo.
          Extrae solo el código, sin el prefijo "Nro.".
          null si no aparece.

        - "grupoAnimal": Grupo o grupos de especies a transportar.
          Suele estar en la tabla de especies, bajo "Nombre científico" u "Orden".
          Si hay múltiples, extrae el primer grupo mencionado. null si no aparece.

        - "provinciaOrigen": Provincia de donde provienen los especímenes (sección ORIGEN).
          IMPORTANTE: Si el documento tiene secciones ORIGEN y DESTINO, extrae SOLO
          la provincia de ORIGEN, no la de DESTINO.
          null si no aparece.

        - "localidad": Sitio específico de donde provienen los especímenes.
          Suele estar en la tabla de especies bajo "Sitio" o dentro de la sección ORIGEN.
          null si no aparece.

        Para todos los demás campos devuelve null.

        --- Ejemplos de extracción correcta ---

        Texto: "...GUÍA DE MOVILIZACIÓN DE ESPECÍMENES Nro. GM-2024-00145... Autorización Nro. 007-2023-IC-FAU-DPAO-MAE... ORIGEN: Provincia Orellana, Cantón Aguarico, Sitio: Estación Tiputini... DESTINO: Provincia Pichincha, Cantón Quito... Orden: Lepidoptera..."
        Resultado: {"nroPermisoRecoleccion": null, "nroPermisoMovilizacion": "GM-2024-00145", "grupoAnimal": "Lepidoptera", "provinciaOrigen": "Orellana", "localidad": "Estación Tiputini", "origenDonacion": null, "nombreInvestigador": null}

        Texto: "...Guía de Movilización Nro. 0089-2024-SUIA... Origen: Napo... Especie: Pristimantis sp. (Anura)..."
        Resultado: {"nroPermisoRecoleccion": null, "nroPermisoMovilizacion": "0089-2024-SUIA", "grupoAnimal": "Anura", "provinciaOrigen": "Napo", "localidad": null, "origenDonacion": null, "nombreInvestigador": null}
        INST;
    }

    private function instruccionesCesion(): string
    {
        return <<<'INST'
        Instrucciones para este documento (carta de procedencia o cesión):

        Extrae SOLO este campo:

        - "origenDonacion": Nombre de la institución, colección o persona que entrega o cede
          los especímenes. null si no aparece claramente.

        Para todos los demás campos devuelve null.

        --- Ejemplos de extracción correcta ---

        Texto: "...Por medio de la presente, la Universidad Central del Ecuador, a través de su Departamento de Biología, cede formalmente los especímenes..."
        Resultado: {"nroPermisoRecoleccion": null, "nroPermisoMovilizacion": null, "grupoAnimal": null, "provinciaOrigen": null, "localidad": null, "origenDonacion": "Universidad Central del Ecuador", "nombreInvestigador": null}

        Texto: "...Yo, Carlos Andrés Mejía, entrego voluntariamente mi colección personal de insectos al Museo de Historia Natural..."
        Resultado: {"nroPermisoRecoleccion": null, "nroPermisoMovilizacion": null, "grupoAnimal": null, "provinciaOrigen": null, "localidad": null, "origenDonacion": "Carlos Andrés Mejía", "nombreInvestigador": null}
        INST;
    }

    // ── Clasificación de documentos ──────────────────────────────────────────────

    private function clasificarDocumento(string $nombreDocumento): string
    {
        $nombre = $this->normalizarTexto($nombreDocumento);

        $patrones = [
            self::TIPO_SOLICITUD => [
                'formato solicitud dep',
                'formato solicitud don',
                'carta solicitud deposito',
                'carta solicitud donacion',
            ],
            self::TIPO_AUTORIZACION => [
                'autorizacion de recoleccion',
                'autorizacion mae',
                'permiso recoleccion mae',
                'autorizacion investigacion',
            ],
            self::TIPO_MOVILIZACION => [
                'permiso de movilizacion',
                'guia de movilizacion',
                'guia movilizacion',
                'permiso movilizacion',
            ],
            self::TIPO_CESION => [
                'carta de procedencia',
                'carta de cesion',
                'carta cesion',
                'carta procedencia',
                'cesion de derechos',
            ],
        ];

        foreach ($patrones as $tipo => $variantes) {
            foreach ($variantes as $variante) {
                if (str_contains($nombre, $variante)) {
                    return $tipo;
                }
            }
        }

        return self::TIPO_DESCONOCIDO;
    }

    private function normalizarProvincia(string $valor): string
    {
        $valorNorm = $this->normalizarTexto(trim($valor));

        $mejorDistancia = PHP_INT_MAX;
        $mejorNombre = $valor;

        foreach (self::PROVINCIAS_ECUADOR as $provincia) {
            $distancia = levenshtein($valorNorm, $this->normalizarTexto($provincia));
            if ($distancia < $mejorDistancia) {
                $mejorDistancia = $distancia;
                $mejorNombre = $provincia;
            }
        }

        return $mejorDistancia <= 2 ? $mejorNombre : $valor;
    }

    private function normalizarTexto(string $texto): string
    {
        $normalizado = mb_strtolower($texto, 'UTF-8');

        return strtr($normalizado, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ñ' => 'n', 'ü' => 'u',
        ]);
    }

    private function esDocumentoReconocido(string $nombreDocumento): bool
    {
        return $this->clasificarDocumento($nombreDocumento) !== self::TIPO_DESCONOCIDO;
    }

    // ── Validación ligera de campos extraídos ────────────────────────────────────

    /** @return array<string, mixed> */
    private function validar(array $resultado): array
    {
        // Provincia: al menos 2 caracteres y no solo dígitos; luego normalizar contra lista canónica.
        if (isset($resultado['provinciaOrigen'])) {
            $prov = trim((string) $resultado['provinciaOrigen']);
            if (mb_strlen($prov, 'UTF-8') < 2 || preg_match('/^\d+$/', $prov)) {
                Log::info('GroqExtraccion: provinciaOrigen descartada por validación', ['valor' => $prov]);
                $resultado['provinciaOrigen'] = null;
            } else {
                $normalizada = $this->normalizarProvincia($prov);
                if ($normalizada !== $prov) {
                    Log::info('GroqExtraccion: provinciaOrigen normalizada', ['original' => $prov, 'canonica' => $normalizada]);
                }
                $resultado['provinciaOrigen'] = $normalizada;
            }
        }

        // Permisos: deben contener al menos un dígito.
        foreach (['nroPermisoRecoleccion', 'nroPermisoMovilizacion'] as $campo) {
            if (isset($resultado[$campo]) && is_string($resultado[$campo]) && ! preg_match('/\d/', $resultado[$campo])) {
                Log::info("GroqExtraccion: {$campo} descartado por no contener dígitos", ['valor' => $resultado[$campo]]);
                $resultado[$campo] = null;
            }
        }

        // Grupo animal: no debe ser un párrafo entero.
        if (isset($resultado['grupoAnimal']) && is_string($resultado['grupoAnimal']) && mb_strlen($resultado['grupoAnimal'], 'UTF-8') > 80) {
            Log::info('GroqExtraccion: grupoAnimal descartado por longitud excesiva', ['valor' => $resultado['grupoAnimal']]);
            $resultado['grupoAnimal'] = null;
        }

        return $resultado;
    }

    /** @param array<string, mixed> $resultado */
    private function todoNulo(array $resultado): bool
    {
        if (empty($resultado)) {
            return true;
        }

        foreach ($resultado as $valor) {
            if ($this->limpiar($valor) !== null) {
                return false;
            }
        }

        return true;
    }

    // ── Seguridad para código de recolección MAE ───────────────────────────────

    /**
     * Busca en el texto crudo del PDF un código con la forma típica de
     * autorización MAE: dígitos-año seguido de uno o más segmentos
     * alfanuméricos en mayúsculas unidos por guiones, barras o espacios.
     */
    private function extraerCodigoRecoleccionPorRegex(string $texto): ?string
    {
        $patron = '/\d{1,4}-\d{4}(?:[ \t\-\/]+[A-Z][A-Z0-9]*)+/u';

        if (preg_match_all($patron, $texto, $coincidencias) === 0) {
            return null;
        }

        $mejor = '';
        foreach ($coincidencias[0] as $candidato) {
            $candidato = trim($candidato);
            if (mb_strlen($candidato, 'UTF-8') > mb_strlen($mejor, 'UTF-8')) {
                $mejor = $candidato;
            }
        }

        return $mejor !== '' ? $mejor : null;
    }

    /**
     * Decide entre la extracción del LLM y la del regex. Si ambas existen y
     * el regex contiene al output del LLM como substring (truncamiento),
     * gana el regex. En cualquier otro caso, conservamos el valor del LLM.
     */
    private function consolidarCodigoRecoleccion(?string $llm, ?string $regex): ?string
    {
        if ($llm === null) {
            return $regex;
        }

        if ($regex === null) {
            return $llm;
        }

        if (mb_stripos($regex, $llm, 0, 'UTF-8') !== false
            && mb_strlen($regex, 'UTF-8') > mb_strlen($llm, 'UTF-8')) {
            return $regex;
        }

        return $llm;
    }

    // ── Utilidades ───────────────────────────────────────────────────────────────

    private function limpiar(mixed $valor): ?string
    {
        if (! is_string($valor) || trim($valor) === '' || strtolower(trim($valor)) === 'null') {
            return null;
        }

        return trim($valor);
    }
}
