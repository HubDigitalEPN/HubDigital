<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\PortalCopy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

final class PaginaLaboratorio
{
    public const CLAVE = 'pagina_laboratorio.contenido';
    private const PREFIJO = 'divulgacion/laboratorio/';

    public function __construct(private readonly AlmacenamientoDepositos $archivos) {}

    public function leer(): array
    {
        $original = (string) (PortalCopy::all()[self::CLAVE] ?? '');
        $datos = json_decode($original, true);
        $defectos = config('pagina-laboratorio.textos');
        $textos = array_replace($defectos, array_filter(array_intersect_key((array) ($datos['textos'] ?? []), $defectos), 'is_string'));
        $fotos = [];
        foreach (config('pagina-laboratorio.fotos') as $clave => $defecto) {
            $foto = $datos['fotos'][$clave] ?? [];
            if (! is_array($foto)) $foto = [];
            $fotos[$clave] = $defecto + ['ruta' => null, 'sha256' => null];
            if (is_string($foto['alt'] ?? null)) $fotos[$clave]['alt'] = $foto['alt'];
            if (preg_match('/\A[a-f0-9]{64}\z/', (string) ($foto['sha256'] ?? ''))
                && ($foto['ruta'] ?? '') === self::PREFIJO.$foto['sha256'].'.webp') {
                $fotos[$clave]['ruta'] = $foto['ruta']; $fotos[$clave]['sha256'] = $foto['sha256'];
            }
        }
        $bloques = [];
        foreach (array_slice((array) ($datos['bloques'] ?? []), 0, 30) as $bloque) {
            if (!is_array($bloque) || !preg_match('/\A[a-f0-9]{16}\z/', (string) ($bloque['id'] ?? ''))) continue;
            $bloques[] = [
                'id' => $bloque['id'], 'titulo' => (string) ($bloque['titulo'] ?? ''),
                'texto' => (string) ($bloque['texto'] ?? ''), 'alt' => (string) ($bloque['alt'] ?? ''),
                'foto' => $this->fotoBloque((array) ($bloque['foto'] ?? []), (string) ($bloque['alt'] ?? '')),
            ];
        }
        return ['textos' => $textos, 'fotos' => $fotos, 'bloques' => $bloques, 'posiciones' => (array) ($datos['posiciones'] ?? []), 'version' => hash('sha256', $original)];
    }

    public function prepararFoto(string $contenido): array
    {
        $tamano = @getimagesizefromstring($contenido);
        if (strlen($contenido) > 8 * 1024 * 1024 || !$tamano || $tamano[0] > 4096 || $tamano[1] > 4096
            || $tamano[0] * $tamano[1] > 12000000 || !in_array($tamano['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/avif'], true)) {
            throw new \InvalidArgumentException('Carga una fotografía JPG, PNG, WebP o AVIF de hasta 8 MB y 4096 × 4096 píxeles.');
        }
        $imagen = ImageManager::usingDriver(new Driver)->decodeBinary($contenido)->scaleDown(width: 1400, height: 1400);
        $webp = (string) $imagen->encode(new WebpEncoder(quality: 88, strip: true));
        $hash = hash('sha256', $webp); $ruta = self::PREFIJO.$hash.'.webp';
        $this->archivos->guardarContenido($ruta, $webp, 'image/webp');
        return ['ruta' => $ruta, 'sha256' => $hash];
    }

    public function guardar(array $textos, array $alt, array $nuevas, array $restaurar, string $version, string $autor, ?array $bloques = null, ?array $posiciones = null): void
    {
        DB::transaction(function () use ($textos, $alt, $nuevas, $restaurar, $version, $autor, $bloques, $posiciones): void {
            $fila = DB::table('usuarios.textos_portal')->where('clave', self::CLAVE)->lockForUpdate()->first();
            if (! hash_equals(hash('sha256', (string) ($fila->contenido ?? '')), $version)) {
                throw ValidationException::withMessages(['version' => 'Otra persona actualizó la página. Recarga el editor antes de guardar.']);
            }
            $actual = json_decode((string) ($fila->contenido ?? ''), true);
            $fotos = [];
            foreach (config('pagina-laboratorio.fotos') as $clave => $defecto) {
                $foto = $nuevas[$clave] ?? (!empty($restaurar[$clave]) ? [] : ($actual['fotos'][$clave] ?? []));
                $fotos[$clave] = array_intersect_key($foto, array_flip(['ruta', 'sha256'])) + ['alt' => trim($alt[$clave])];
            }
            $anteriores = collect((array) ($actual['bloques'] ?? []))->keyBy('id');
            $guardados = [];
            foreach ($bloques ?? $anteriores->values()->all() as $bloque) {
                $id = (string) ($bloque['id'] ?? '');
                if (!preg_match('/\A[a-f0-9]{16}\z/', $id)) throw new \InvalidArgumentException('Bloque de contenido no válido.');
                $clave = 'bloque-'.$id;
                // La ruta publicada procede del servidor; nunca del formulario.
                $foto = $nuevas[$clave] ?? (!empty($restaurar[$clave]) ? [] : ($anteriores->get($id)['foto'] ?? []));
                $guardados[] = array_intersect_key($bloque, array_flip(['id', 'titulo', 'texto', 'alt']))
                    + ['foto' => $this->fotoBloque((array) $foto, (string) ($bloque['alt'] ?? ''))];
            }
            $contenido = ['textos' => array_intersect_key($textos, config('pagina-laboratorio.textos')), 'fotos' => $fotos, 'bloques' => $guardados];
            $contenido['posiciones'] = DisenoLaboratorio::validar($posiciones ?? (array) ($actual['posiciones'] ?? []), $contenido);
            $valores = ['contenido' => json_encode($contenido, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'updated_by' => $autor, 'updated_at' => now()];
            if (!$fila) $valores['created_at'] = now();
            DB::table('usuarios.textos_portal')->updateOrInsert(['clave' => self::CLAVE], $valores);
        });
        PortalCopy::forget();
    }

    public function contenidoFoto(string $clave, string $hash): string
    {
        $datos = $this->leer();
        $foto = $datos['fotos'][$clave] ?? null;
        foreach ($datos['bloques'] as $bloque) if ($clave === 'bloque-'.$bloque['id']) $foto = $bloque['foto'];
        abort_unless($foto && is_string($foto['sha256']) && hash_equals($foto['sha256'], $hash), 404);
        $contenido = $this->archivos->obtenerVerificado($foto['ruta'], $hash);
        abort_unless((@getimagesizefromstring($contenido)['mime'] ?? '') === 'image/webp', 404);
        return $contenido;
    }

    private function fotoBloque(array $foto, string $alt): array
    {
        $hash = (string) ($foto['sha256'] ?? '');
        $valida = preg_match('/\A[a-f0-9]{64}\z/', $hash) && ($foto['ruta'] ?? '') === self::PREFIJO.$hash.'.webp';
        return ['alt' => $alt, 'ruta' => $valida ? $foto['ruta'] : null, 'sha256' => $valida ? $hash : null];
    }
}
