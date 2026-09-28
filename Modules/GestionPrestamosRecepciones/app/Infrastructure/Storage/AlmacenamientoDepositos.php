<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Fachada unica para los objetos privados del expediente de deposito. */
final class AlmacenamientoDepositos
{
    private ?R2S3Client $r2 = null;

    public function driver(): string
    {
        $seleccionado = strtolower(trim((string) config('deposit-storage.driver', 'auto')));
        if ($seleccionado === 'local'
            && app()->environment(['local', 'testing'])
            && ! (bool) config('deposit-storage.require_remote', true)) {
            return 'local';
        }
        if ($seleccionado !== 'r2') {
            throw new \RuntimeException('DEPOSIT_STORAGE_DRIVER debe ser r2; los expedientes no admiten fallback local.');
        }

        $configR2 = (array) config('deposit-storage.r2', []);
        $campos = ['endpoint', 'bucket', 'access_key_id', 'secret_access_key'];
        $presentes = array_filter($campos, static fn (string $campo): bool => trim((string) ($configR2[$campo] ?? '')) !== '');
        $r2Completo = count($presentes) === count($campos);

        if (! $r2Completo) {
            throw new \RuntimeException('R2 fue exigido pero faltan endpoint, bucket o credenciales S3.');
        }

        return 'r2';
    }

    public function guardarArchivo(UploadedFile $archivo, string $directorio): string
    {
        return $this->guardarArchivoConHuella($archivo, $directorio)['ruta'];
    }

    /** @return array{ruta: string, sha256: string} */
    public function guardarArchivoConHuella(UploadedFile $archivo, string $directorio): array
    {
        $extension = strtolower($archivo->getClientOriginalExtension());
        $nombre = Str::uuid().($extension !== '' ? '.'.$extension : '');
        $ruta = trim($directorio, '/').'/'.$nombre;
        $contenido = file_get_contents($archivo->getRealPath());
        if ($contenido === false) {
            throw new \RuntimeException('No se pudo leer el archivo cargado.');
        }
        $this->validarCargaPdf($archivo->getRealPath(), $contenido);
        $sha256 = $this->guardarContenido($ruta, $contenido, $archivo->getMimeType() ?: 'application/octet-stream');

        return ['ruta' => $ruta, 'sha256' => $sha256];
    }

    public function guardarSubidoComo(UploadedFile $archivo, string $ruta): string
    {
        $contenido = file_get_contents($archivo->getRealPath());
        if ($contenido === false) {
            throw new \RuntimeException('No se pudo leer el archivo cargado.');
        }
        $this->validarCargaPdf($archivo->getRealPath(), $contenido);
        $this->guardarContenido($ruta, $contenido, $archivo->getMimeType() ?: 'application/pdf');

        return $ruta;
    }

    public function guardarContenido(string $ruta, string $contenido, string $mime = 'application/octet-stream'): string
    {
        $ruta = $this->normalizarRuta($ruta);
        $maximo = (int) config('deposit-storage.max_object_bytes', 25 * 1024 * 1024);
        if (strlen($contenido) > $maximo) {
            throw new \RuntimeException("El objeto excede el limite interno de {$maximo} bytes.");
        }
        $sha256 = hash('sha256', $contenido);
        if ($this->driver() === 'r2') {
            $rutaR2 = $this->rutaR2($ruta);
            $this->clienteR2()->put($rutaR2, $contenido, $mime);
            if ((bool) config('deposit-storage.verify_after_write', true)) {
                try {
                    $cabecera = $this->clienteR2()->head($rutaR2);
                    if ($cabecera['content_length'] !== null && $cabecera['content_length'] !== strlen($contenido)) {
                        throw new \RuntimeException('R2 no confirmo el tamano integro del objeto guardado.');
                    }

                    $contenidoPersistido = $this->clienteR2()->get($rutaR2);
                    if (! hash_equals($sha256, hash('sha256', $contenidoPersistido))) {
                        throw new \RuntimeException('R2 no confirmo la integridad SHA-256 del objeto guardado.');
                    }
                } catch (\Throwable $e) {
                    try {
                        $this->clienteR2()->delete($rutaR2);
                    } catch (\Throwable $cleanupError) {
                        report($cleanupError);
                    }

                    throw $e;
                }
            }

            return $sha256;
        }

        $guardado = Storage::disk($this->discoLocal())->put($ruta, $contenido);
        if ($guardado !== true) {
            throw new \RuntimeException('No se pudo guardar el documento en el disco privado local.');
        }

        return $sha256;
    }

    private function validarCargaPdf(string $ruta, string $contenido): void
    {
        app(ValidadorPdfDeposito::class)->validar($ruta);
        $huellaInspeccionada = hash_file('sha256', $ruta);
        if ($huellaInspeccionada === false || ! hash_equals(hash('sha256', $contenido), $huellaInspeccionada)) {
            throw new \RuntimeException('El contenido del PDF cambió durante su inspección.');
        }
    }

    public function existe(string $ruta): bool
    {
        $ruta = $this->normalizarRuta($ruta);
        if ($this->driver() === 'r2') {
            // R2 es la fuente autoritativa en los entornos remotos. No se
            // consulta un disco local/legado si el objeto no esta en R2: hacerlo
            // ocultaria una perdida remota y podria exponer una copia residual.
            return $this->clienteR2()->exists($this->rutaR2($ruta));
        }
        if (Storage::disk($this->discoLocal())->exists($ruta)) {
            return true;
        }

        // Compatibilidad solo para local/testing durante la migracion de
        // objetos historicos. El driver r2 retorna antes de llegar aqui.
        return Storage::disk($this->discoPublicoLegado())->exists($ruta);
    }

    public function obtener(string $ruta): string
    {
        $ruta = $this->normalizarRuta($ruta);
        if ($this->driver() === 'r2') {
            $rutaR2 = $this->rutaR2($ruta);
            if (! $this->clienteR2()->exists($rutaR2)) {
                throw new \RuntimeException('El documento solicitado no existe en Cloudflare R2.');
            }

            return $this->clienteR2()->get($rutaR2);
        }
        if (Storage::disk($this->discoLocal())->exists($ruta)) {
            return Storage::disk($this->discoLocal())->get($ruta);
        }
        if (Storage::disk($this->discoPublicoLegado())->exists($ruta)) {
            return Storage::disk($this->discoPublicoLegado())->get($ruta);
        }

        throw new \RuntimeException('El documento solicitado no existe en el almacenamiento privado.');
    }

    /**
     * Obtiene un objeto y, cuando PostgreSQL conserva su huella, rechaza cualquier
     * contenido alterado antes de entregarlo al consumidor.
     */
    public function obtenerVerificado(string $ruta, ?string $sha256Esperado): string
    {
        $contenido = $this->obtener($ruta);
        $esperado = strtolower(trim((string) $sha256Esperado));

        if ($esperado === '') {
            throw new \RuntimeException('PostgreSQL no contiene una huella SHA-256 certificada para este objeto.');
        }
        if (! hash_equals($esperado, hash('sha256', $contenido))) {
            throw new \RuntimeException('La integridad SHA-256 del objeto almacenado no coincide con PostgreSQL.');
        }

        return $contenido;
    }

    /** @return resource */
    public function readStream(string $ruta)
    {
        $ruta = $this->normalizarRuta($ruta);
        if ($this->driver() === 'r2') {
            $rutaR2 = $this->rutaR2($ruta);
            if (! $this->clienteR2()->exists($rutaR2)) {
                throw new \RuntimeException('El documento solicitado no existe en Cloudflare R2.');
            }

            return $this->clienteR2()->readStream($rutaR2);
        }
        if (Storage::disk($this->discoLocal())->exists($ruta)) {
            $stream = Storage::disk($this->discoLocal())->readStream($ruta);
            if (is_resource($stream)) {
                return $stream;
            }
        }
        $stream = Storage::disk($this->discoPublicoLegado())->readStream($ruta);
        if (! is_resource($stream)) {
            throw new \RuntimeException('No se pudo abrir el documento privado para lectura.');
        }

        return $stream;
    }

    public function eliminar(string $ruta): void
    {
        $ruta = $this->normalizarRuta($ruta);
        if ($this->driver() === 'r2') {
            $rutaR2 = $this->rutaR2($ruta);
            if ($this->clienteR2()->exists($rutaR2)) {
                $this->clienteR2()->delete($rutaR2);
            }

            return;
        }
        Storage::disk($this->discoLocal())->delete($ruta);
        Storage::disk($this->discoPublicoLegado())->delete($ruta);
    }

    /** @param list<string> $rutas */
    public function eliminarCandidatosSinOcultarError(array $rutas): void
    {
        foreach (array_reverse(array_values(array_unique($rutas))) as $ruta) {
            try {
                $this->eliminar($ruta);
            } catch (\Throwable $cleanupError) {
                report($cleanupError);
            }
        }
    }

    public function mimeType(string $ruta): string
    {
        $ruta = $this->normalizarRuta($ruta);
        if ($this->driver() === 'r2') {
            $rutaR2 = $this->rutaR2($ruta);
            if (! $this->clienteR2()->exists($rutaR2)) {
                throw new \RuntimeException('El documento solicitado no existe en Cloudflare R2.');
            }

            return (string) ($this->clienteR2()->head($rutaR2)['content_type'] ?: 'application/octet-stream');
        }
        if (Storage::disk($this->discoLocal())->exists($ruta)) {
            return Storage::disk($this->discoLocal())->mimeType($ruta) ?: 'application/octet-stream';
        }

        return Storage::disk($this->discoPublicoLegado())->mimeType($ruta) ?: 'application/octet-stream';
    }

    public function sha256(string $ruta): string
    {
        $stream = $this->readStream($ruta);
        try {
            $contexto = hash_init('sha256');
            hash_update_stream($contexto, $stream);

            return hash_final($contexto);
        } finally {
            fclose($stream);
        }
    }

    /** @return array{content_type: ?string, content_length: ?int, etag: ?string} */
    public function inspeccionar(string $ruta): array
    {
        $ruta = $this->normalizarRuta($ruta);
        if ($this->driver() === 'r2') {
            return $this->clienteR2()->head($this->rutaR2($ruta));
        }
        if (! Storage::disk($this->discoLocal())->exists($ruta)) {
            throw new \RuntimeException('El objeto no existe.');
        }

        return [
            'content_type' => Storage::disk($this->discoLocal())->mimeType($ruta) ?: null,
            'content_length' => Storage::disk($this->discoLocal())->size($ruta),
            'etag' => null,
        ];
    }

    /**
     * @return array{objetos: list<array{ruta: string, tamano: int, etag: ?string}>, truncado: bool, cursor: ?string}
     */
    public function listar(string $prefijo, ?string $cursor = null, int $limite = 1000): array
    {
        $prefijo = $this->normalizarPrefijo($prefijo);
        if ($this->driver() === 'r2') {
            $prefijoR2 = $this->rutaR2($prefijo);
            $resultado = $this->clienteR2()->listar($prefijoR2, $cursor, $limite);
            $baseR2 = $this->prefijoR2();

            if ($baseR2 !== '') {
                $resultado['objetos'] = array_map(
                    static fn (array $objeto): array => [
                        ...$objeto,
                        'ruta' => substr($objeto['ruta'], strlen($baseR2) + 1),
                    ],
                    $resultado['objetos'],
                );
            }

            return $resultado;
        }
        $todos = collect(Storage::disk($this->discoLocal())->allFiles($prefijo))->sort()->values();
        $inicio = $cursor === null ? 0 : max(0, (int) $cursor);
        $pagina = $todos->slice($inicio, max(1, $limite))->map(fn (string $ruta): array => [
            'ruta' => $ruta,
            'tamano' => Storage::disk($this->discoLocal())->size($ruta),
            'etag' => null,
        ])->values()->all();
        $siguiente = $inicio + count($pagina);

        return ['objetos' => $pagina, 'truncado' => $siguiente < $todos->count(), 'cursor' => $siguiente < $todos->count() ? (string) $siguiente : null];
    }

    public function copiaLocal(string $ruta): ArchivoLocalDeposito
    {
        $ruta = $this->normalizarRuta($ruta);
        $driver = $this->driver();
        if ($driver === 'local' && Storage::disk($this->discoLocal())->exists($ruta)) {
            return new ArchivoLocalDeposito(Storage::disk($this->discoLocal())->path($ruta), false);
        }
        if ($driver === 'local' && Storage::disk($this->discoPublicoLegado())->exists($ruta)) {
            return new ArchivoLocalDeposito(Storage::disk($this->discoPublicoLegado())->path($ruta), false);
        }

        // En R2 siempre se materializa primero el objeto remoto autoritativo. Una
        // copia publica heredada nunca debe prevalecer sobre el expediente remoto.
        $contenido = $this->obtener($ruta);
        $directorio = DirectorioTemporalHubDigital::crear('r2-copia', strlen($contenido));
        $temporal = $directorio.DIRECTORY_SEPARATOR.basename($ruta);
        if (file_put_contents($temporal, $contenido, LOCK_EX) === false) {
            DirectorioTemporalHubDigital::eliminar($directorio);
            throw new \RuntimeException('No se pudo crear la copia local temporal del objeto R2.');
        }
        @chmod($temporal, 0600);

        return new ArchivoLocalDeposito($temporal, true, $directorio);
    }

    private function clienteR2(): R2S3Client
    {
        return $this->r2 ??= new R2S3Client((array) config('deposit-storage.r2'));
    }

    private function discoLocal(): string
    {
        return (string) config('deposit-storage.local_disk', 'local');
    }

    private function discoPublicoLegado(): string
    {
        return (string) config('deposit-storage.legacy_public_disk', 'public');
    }

    private function prefijoR2(): string
    {
        $prefijo = trim((string) config('deposit-storage.prefix', ''), '/');

        return $prefijo === '' ? '' : $this->normalizarRuta($prefijo);
    }

    private function rutaR2(string $ruta): string
    {
        $prefijo = $this->prefijoR2();

        return $prefijo === '' ? $ruta : $prefijo.'/'.$ruta;
    }

    private function normalizarRuta(string $ruta): string
    {
        $ruta = trim($ruta);
        $segmentos = explode('/', $ruta);
        if ($ruta === '' || str_starts_with($ruta, '/') || str_contains($ruta, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $ruta) === 1
            || in_array('.', $segmentos, true) || in_array('..', $segmentos, true)
        ) {
            throw new \InvalidArgumentException('La ruta del objeto de deposito no es valida.');
        }

        return implode('/', array_filter($segmentos, static fn (string $segmento): bool => $segmento !== ''));
    }

    private function normalizarPrefijo(string $prefijo): string
    {
        $prefijo = trim($prefijo);
        if ($prefijo === '' || str_starts_with($prefijo, '/') || str_contains($prefijo, '\\') || str_contains($prefijo, '..')) {
            throw new \InvalidArgumentException('El prefijo de objetos no es valido.');
        }

        return trim($prefijo, '/').'/';
    }
}
