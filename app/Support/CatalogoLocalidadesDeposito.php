<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\CatalogoPublico\Infrastructure\ElegibilidadGeograficaPortal;
use Modules\CatalogoPublico\Infrastructure\LocalidadPublica;

/** Los mismos lugares publicados en el catálogo, sin duplicados ni parroquias administrativas. */
final class CatalogoLocalidadesDeposito
{
    public const MAXIMO_CARACTERES = 2000;
    private const TAMANO_PAGINA = 40;

    /** @return array{opciones: list<array{valor: string, etiqueta: string}>, hayMas: bool} */
    public static function paginaLov(string $provincia, string $busqueda = '', int $pagina = 0): array
    {
        $consulta = self::consulta($provincia);
        $busqueda = Str::ascii(mb_strtolower(mb_substr(trim($busqueda), 0, 120)));
        if ($busqueda !== '') {
            $consulta->whereRaw("translate(lower(localidad), 'áéíóúüñ', 'aeiouun') LIKE ?",
                ['%'.addcslashes($busqueda, '\\%_').'%']);
        }
        $filas = $consulta->orderBy('localidad')->offset(max(0, min($pagina, 1000)) * self::TAMANO_PAGINA)
            ->limit(self::TAMANO_PAGINA + 1)->pluck('localidad');

        $opciones = $filas->take(self::TAMANO_PAGINA)->map(
            fn (string $nombre): array => ['valor' => $nombre, 'etiqueta' => $nombre],
        )->all();
        if ($pagina === 0 && $busqueda === '') array_unshift($opciones, ['valor' => '', 'etiqueta' => 'Sin localidad (opcional)']);

        return ['opciones' => $opciones, 'hayMas' => $filas->count() > self::TAMANO_PAGINA];
    }

    public static function contiene(string $provincia, string $nombre): bool
    {
        return $nombre !== '' && self::consulta($provincia)->where('localidad', $nombre)->exists();
    }

    private static function consulta(string $provincia): Builder
    {
        $normalizada = Str::ascii(mb_strtolower(trim($provincia)));
        $provincias = $normalizada === 'santo domingo de los tsachilas'
            ? [$normalizada, 'santo domingo', 'santo domingo de los colorados'] : [$normalizada];
        $texto = LocalidadPublica::sql('e');
        $publicados = DB::table('taxonomia.especimenes as e')
            ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
            ->where('d.publicado', true)->where('d.locality_name_visible', true)
            ->where('d.state_province_visible', true)
            ->whereRaw(ElegibilidadGeograficaPortal::sql('e', 'd'))
            ->whereIn(DB::raw("translate(lower(btrim(e.state_province)), 'áéíóúüñ', 'aeiouun')"), $provincias)
            ->selectRaw($texto.' AS localidad')->distinct();

        return DB::query()->fromSub($publicados, 'localidades_publicas')
            ->whereNotNull('localidad')->where('localidad', '<>', '')
            ->whereRaw('length(localidad) <= ?', [self::MAXIMO_CARACTERES]);
    }
}
