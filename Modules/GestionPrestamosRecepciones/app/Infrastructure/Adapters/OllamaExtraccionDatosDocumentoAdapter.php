<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Application\Ports\ExtraccionDatosDocumentoPort;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DatosIntegradosDocumento;
use Smalot\PdfParser\Parser;

final class OllamaExtraccionDatosDocumentoAdapter implements ExtraccionDatosDocumentoPort
{
    public function __construct(
        private readonly string $ollamaUrl,
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

            $parcial = $this->consultarOllama($nombre, $texto);

            // Conservar el primer valor no nulo encontrado para cada campo.
            foreach ($parcial as $campo => $valor) {
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

    private function leerPdf(Parser $parser, string $nombre, string $ruta): ?string
    {
        $rutaAbsoluta = Storage::disk('public')->path($ruta);

        if (! file_exists($rutaAbsoluta)) {
            return null;
        }

        try {
            $texto = trim($parser->parseFile($rutaAbsoluta)->getText());

            if (empty($texto)) {
                return null;
            }

            // Limitar a los primeros 3000 caracteres: los datos relevantes
            // (número de permiso, grupo animal, provincia) siempre aparecen
            // en las primeras páginas. Esto reduce drásticamente el tiempo de inferencia.
            return mb_substr($texto, 0, 3000, 'UTF-8');
        } catch (\Throwable $e) {
            Log::warning('OllamaExtraccion: no se pudo parsear PDF', [
                'documento' => $nombre,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** @return array<string, mixed> */
    private function consultarOllama(string $nombreDocumento, string $texto): array
    {
        try {
            set_time_limit(180);
            $prompt = $this->construirPrompt($nombreDocumento, $texto);

            Log::debug('OllamaExtraccion: enviando prompt', [
                'documento' => $nombreDocumento,
                'prompt_preview' => mb_substr($prompt, 0, 800),
            ]);

            $respuesta = Http::timeout(120)->post("{$this->ollamaUrl}/api/generate", [
                'model' => $this->modelo,
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json',
                'think' => false,
            ]);

            if (! $respuesta->successful()) {
                Log::warning('OllamaExtraccion: respuesta no exitosa', ['status' => $respuesta->status()]);

                return [];
            }

            $resultado = json_decode($respuesta->json('response', '{}'), true) ?? [];

            Log::debug('OllamaExtraccion: respuesta recibida', [
                'documento' => $nombreDocumento,
                'resultado' => $resultado,
            ]);

            return $resultado;
        } catch (\Throwable $e) {
            Log::warning('OllamaExtraccion: error al contactar Ollama', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function construirPrompt(string $nombreDocumento, string $texto): string
    {
        $instrucciones = $this->instruccionesPorDocumento($nombreDocumento);

        return <<<PROMPT
Eres un extractor de datos de documentos oficiales ecuatorianos sobre especímenes de vida silvestre.

Documento: "{$nombreDocumento}"

Reglas generales:
- Responde ÚNICAMENTE con un objeto JSON válido con estos campos exactos:
  {"nroPermisoRecoleccion": null, "nroPermisoMovilizacion": null, "grupoAnimal": null, "provinciaOrigen": null, "localidad": null, "origenDonacion": null, "nombreInvestigador": null}
- Usa null sin comillas para campos ausentes. Nunca cadena vacía "".
- No inventes datos. Si el dato no aparece claramente en el texto, usa null.
- Extrae códigos y números sin prefijos: sin "N.º", "Nro.", "N°", "No.", "N.°".

{$instrucciones}

Texto:
{$texto}

JSON:
PROMPT;
    }

    private function instruccionesPorDocumento(string $nombreDocumento): string
    {
        $nombre = mb_strtolower($nombreDocumento, 'UTF-8');

        // Carta de solicitud (Depósito o Donación)
        if (str_contains($nombre, 'formato solicitud dep') || str_contains($nombre, 'formato solicitud don')) {
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
INST;
        }

        // Autorización de Recolección emitida por el MAATE
        if (str_contains($nombre, 'autorización de recolección') || str_contains($nombre, 'autorizacion de recoleccion')
            || str_contains($nombre, 'autorización maate') || str_contains($nombre, 'autorizacion maate')) {
            return <<<'INST'
Instrucciones para este documento (autorización de recolección MAATE):

Extrae SOLO estos campos:

- "nroPermisoRecoleccion": Código alfanumérico de la autorización emitida por el MAATE.
  Suele comenzar con "N.º" o "N.°" seguido de un código como "006-2025 RVS-...".
  Extrae solo el código, sin el prefijo "N.º", "N.°" o similares.
  null si no aparece.

- "grupoAnimal": Grupo taxonómico principal autorizado para recolección.
  Puede estar en una tabla (Entomofauna, Macroinvertebrados, Herpetofauna, etc.).
  Si hay múltiples grupos, extrae el primero listado. null si no aparece.

- "provinciaOrigen": Provincia ecuatoriana de recolección.
  Suele aparecer cerca de "Área geográfica" o "Provincia" dentro del cuerpo del documento.
  null si no aparece.

- "localidad": Lugar, bloque, sector o área específica de recolección.
  Puede ser un nombre de reserva, bloque de exploración, sector, etc.
  Ejemplos: "Bloque 56 Lago Agrio", "Reserva Cotacachi-Cayapas".
  null si no aparece.

Para todos los demás campos devuelve null.
INST;
        }

        // Permiso / Guía de Movilización
        if (str_contains($nombre, 'permiso de movilización') || str_contains($nombre, 'permiso de movilizacion')
            || str_contains($nombre, 'guía de movilización') || str_contains($nombre, 'guia de movilizacion')) {
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
INST;
        }

        // Carta de Procedencia o Carta de Cesión
        if (str_contains($nombre, 'carta de procedencia') || str_contains($nombre, 'carta de cesión')
            || str_contains($nombre, 'carta de cesion')) {
            return <<<'INST'
Instrucciones para este documento (carta de procedencia o cesión):

Extrae SOLO este campo:

- "origenDonacion": Nombre de la institución, colección o persona que entrega o cede
  los especímenes. null si no aparece claramente.

Para todos los demás campos devuelve null.
INST;
        }

        // Documento no reconocido
        return <<<'INST'
Este tipo de documento no requiere extracción de datos.
Devuelve null para todos los campos.
INST;
    }

    private function limpiar(mixed $valor): ?string
    {
        if (! is_string($valor) || trim($valor) === '' || strtolower(trim($valor)) === 'null') {
            return null;
        }

        return trim($valor);
    }
}
