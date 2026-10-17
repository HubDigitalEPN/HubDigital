<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\ImageManager;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

/** Fichas editoriales por identidad, independientes de las fotografías de ejemplares. */
final class FichasTaxonomicas
{
    public const TABLA = 'divulgacion.fichas_taxonomicas';
    private const CARPETA = 'divulgacion/fichas/';
    public const FOTO_MAX_BYTES = 32768;

    public function __construct(private readonly AlmacenamientoDepositos $archivos) {}

    public static function crearTabla(): void
    {
        if (Schema::hasTable(self::TABLA)) return;
        Schema::create(self::TABLA, function (Blueprint $table): void {
            $table->uuid('taxon_id')->primary();
            $table->foreign('taxon_id')->references('id')->on('taxonomia.taxones')->cascadeOnDelete();
            $table->text('descripcion')->nullable();
            $table->string('foto_ruta')->nullable();
            $table->char('foto_sha256', 64)->nullable();
            $table->string('foto_alt', 180)->nullable();
            $table->string('credito', 500)->nullable();
            $table->string('fuente_url', 1000)->nullable();
            $table->boolean('publicada')->default(false);
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function buscar(string $id, bool $publicada = false): ?object
    {
        if (!Schema::hasTable(self::TABLA)) return null;
        $query = DB::table(self::TABLA)->where('taxon_id', $id);
        if ($publicada) $query->where('publicada', true);
        return $query->first();
    }

    /** Comprime y almacena antes de abrir la transacción de PostgreSQL. */
    public function prepararFoto(string $contenido): array
    {
        $tamano = @getimagesizefromstring($contenido);
        if (strlen($contenido) > 8 * 1024 * 1024 || !$tamano || $tamano[0] > 4096 || $tamano[1] > 4096
            || $tamano[0] * $tamano[1] > 12000000 || !in_array($tamano['mime'], ['image/jpeg', 'image/png', 'image/webp', 'image/avif'], true)) {
            throw new \InvalidArgumentException('Carga una fotografía JPG, PNG, WebP o AVIF de hasta 8 MB y 4096 × 4096 píxeles.');
        }
        $imagen = ImageManager::usingDriver(new Driver)->decodeBinary($contenido)->scaleDown(width: 360, height: 270);
        foreach ([48, 40, 32, 24] as $calidad) {
            $avif = (string) $imagen->encode(new AvifEncoder(quality: $calidad, strip: true));
            if (strlen($avif) <= self::FOTO_MAX_BYTES) break;
        }
        if ($avif === '' || strlen($avif) > self::FOTO_MAX_BYTES || (@getimagesizefromstring($avif)['mime'] ?? '') !== 'image/avif') {
            throw new \RuntimeException('No se pudo generar una miniatura AVIF de hasta 32 KB. La fotografía anterior se conserva.');
        }
        $hash = hash('sha256', $avif);
        $ruta = self::CARPETA.$hash.'.avif';
        $this->archivos->guardarContenido($ruta, $avif, 'image/avif');
        return ['foto_ruta' => $ruta, 'foto_sha256' => $hash, 'bytes' => strlen($avif)];
    }

    public function guardar(string $id, array $campos, ?array $foto, bool $quitarFoto, ?string $autor, ?string $version = null): void
    {
        DB::transaction(function () use ($id, $campos, $foto, $quitarFoto, $autor, $version): void {
            abort_unless(DB::table('taxonomia.taxones')->where('id', $id)->lockForUpdate()->exists(), 404);
            $actual = DB::table(self::TABLA)->where('taxon_id', $id)->first();
            if ($version !== null && $version !== (string) ($actual->updated_at ?? '')) {
                throw \Illuminate\Validation\ValidationException::withMessages(['descripcion' => 'Otra persona actualizó esta ficha. Vuelve a seleccionar el taxón antes de guardar.']);
            }
            $valores = array_intersect_key($campos, array_flip(['descripcion', 'foto_alt', 'credito', 'fuente_url', 'publicada']));
            if ($foto) $valores += ['foto_ruta' => $foto['foto_ruta'], 'foto_sha256' => $foto['foto_sha256']];
            elseif ($quitarFoto) $valores += ['foto_ruta' => null, 'foto_sha256' => null];
            $valores += ['updated_by' => $autor, 'updated_at' => now()];
            if (!$actual) $valores['created_at'] = now();
            DB::table(self::TABLA)->updateOrInsert(['taxon_id' => $id], $valores);
        });
        // Los objetos se direccionan por huella y pueden ser compartidos entre fichas.
        // Reemplazar o retirar la foto no borra imágenes referidas por otros taxones.
    }

    public function contenidoFoto(object $ficha): string
    {
        $ruta = (string) $ficha->foto_ruta;
        abort_unless(preg_match('#^divulgacion/fichas/[a-f0-9]{64}\.(?:avif|webp)$#D', $ruta), 404);
        $hash = (string) $ficha->foto_sha256;
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $hash), 404);
        // Sólo la miniatura certificada se cachea; el controlador consulta la
        // publicación vigente antes de cada entrega, incluida la caché.
        abort_unless(basename($ruta) === $hash.'.'.pathinfo($ruta, PATHINFO_EXTENSION), 404);
        $contenido = Cache::remember('portal-ficha-foto-v2:'.$hash, now()->addMinutes(5), fn (): string => $this->archivos->obtenerVerificado($ruta, $hash));
        abort_unless(is_string($contenido) && hash_equals($hash, hash('sha256', $contenido)), 404);
        abort_unless((@getimagesizefromstring($contenido)['mime'] ?? '') === $this->tipoFoto($ficha), 404);
        return $contenido;
    }

    public function tipoFoto(object $ficha): string
    {
        return str_ends_with((string) $ficha->foto_ruta, '.avif') ? 'image/avif' : 'image/webp';
    }
}
