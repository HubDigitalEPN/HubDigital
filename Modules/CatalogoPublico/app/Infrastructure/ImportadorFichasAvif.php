<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

/** Carga editorial explícita: identidad completa, licencia, miniatura e integridad. */
final class ImportadorFichasAvif
{
    public function __construct(private readonly FichasTaxonomicas $fichas, private readonly AlmacenamientoDepositos $archivos) {}

    public function preparar(array $entrada, string $carpeta): array
    {
        $id = $entrada['taxon_id'] ?? ''; $sha = $entrada['foto_sha256'] ?? '';
        if (!is_string($id) || !Str::isUuid($id) || !is_string($sha) || !preg_match('/^[a-f0-9]{64}$/D', $sha)) {
            throw new \InvalidArgumentException('Identidad o huella editorial inválida.');
        }
        if (($entrada['foto_ruta'] ?? '') !== 'divulgacion/fichas/'.$sha.'.avif') throw new \InvalidArgumentException('Ruta AVIF inválida.');
        $linaje = []; $vistos = []; $taxon = DB::table('taxonomia.taxones')->where('id', $id)->first();
        $actual = $taxon;
        while ($actual) {
            if (isset($vistos[$actual->id]) || count($vistos) >= 64) throw new \InvalidArgumentException('Linaje cíclico o excesivo.');
            $vistos[$actual->id] = true; array_unshift($linaje, [$actual->rango, $actual->nombre_cientifico]);
            $actual = $actual->padre_id ? DB::table('taxonomia.taxones')->where('id', $actual->padre_id)->first() : null;
        }
        if (!$taxon || $linaje !== ($entrada['linaje'] ?? null) || $taxon->nombre_cientifico !== ($entrada['nombre'] ?? null)
            || $taxon->rango !== ($entrada['rango'] ?? null)) throw new \InvalidArgumentException('El linaje del manifiesto no corresponde al taxón de destino.');
        if (!in_array($entrada['licencia'] ?? '', ['cc0', 'cc-by', 'cc-by-sa', 'cc-by-nc', 'cc-by-nc-sa'], true)
            || !is_int($entrada['foto_id'] ?? null) || $entrada['foto_id'] <= 0
            || ($entrada['fuente_url'] ?? '') !== 'https://www.inaturalist.org/photos/'.$entrada['foto_id']) {
            throw new \InvalidArgumentException('Falta una fuente fotográfica con licencia permitida.');
        }
        foreach (['descripcion' => 800, 'foto_alt' => 180, 'credito' => 500] as $campo => $maximo) {
            if (!is_string($entrada[$campo] ?? null) || trim($entrada[$campo]) === '' || mb_strlen($entrada[$campo]) > $maximo) {
                throw new \InvalidArgumentException('Leyenda, texto alternativo o crédito inválido.');
            }
        }
        if (($entrada['rango'] ?? '') === 'especie' && ($entrada['coincidencia_exacta'] ?? false) !== true) {
            throw new \InvalidArgumentException('La cuota de especies requiere fotografías identificadas para esa especie.');
        }
        $ruta = rtrim($carpeta, '/\\').DIRECTORY_SEPARATOR.$sha.'.avif';
        if (!is_file($ruta) || is_link($ruta) || filesize($ruta) > FichasTaxonomicas::FOTO_MAX_BYTES) throw new \InvalidArgumentException('Miniatura ausente o demasiado grande.');
        $bytes = file_get_contents($ruta); $tamano = @getimagesizefromstring($bytes);
        if (!hash_equals($sha, hash('sha256', $bytes)) || !$tamano || $tamano['mime'] !== 'image/avif'
            || $tamano[0] > 360 || $tamano[1] > 270) throw new \InvalidArgumentException('La miniatura AVIF no corresponde al manifiesto.');
        $ficha = $this->fichas->buscar($id);
        $estado = ($ficha?->foto_sha256 === $sha) ? 'ya_importada' : (
            ($ficha->foto_sha256 ?? null) === ($entrada['sha_anterior'] ?? null)
            && (string) ($ficha->updated_at ?? '') === (string) ($entrada['version_anterior'] ?? '') ? 'lista' : 'conflicto');
        return ['entrada' => $entrada, 'bytes' => $bytes, 'estado' => $estado, 'version' => (string) ($ficha->updated_at ?? '')];
    }

    public function aplicar(array $preparada): string
    {
        if ($preparada['estado'] !== 'lista') return $preparada['estado'];
        $entrada = $preparada['entrada'];
        // Objetos inmutables por huella. La escritura certificada sucede antes
        // de la transacción y una edición concurrente conserva la ficha vigente.
        $this->archivos->guardarContenido($entrada['foto_ruta'], $preparada['bytes'], 'image/avif');
        $this->fichas->guardar($entrada['taxon_id'], array_intersect_key($entrada, array_flip([
            'descripcion', 'foto_alt', 'credito', 'fuente_url', 'publicada',
        ])), ['foto_ruta' => $entrada['foto_ruta'], 'foto_sha256' => $entrada['foto_sha256']], false, null, $preparada['version']);
        return 'importada';
    }
}
