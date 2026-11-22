<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OpcionesRelacionCatalogo
{
    private const NODOS = ['divulgacion.chat_nodes', 'id', 'title', 'slug', 'entero'];
    private const POR_PAGINA = 20;
    private const ULTIMA_PAGINA = 199;

    /** Destino, clave, nombre, detalle para distinguir opciones y tipo de clave. */
    private const RELACIONES = [
        'taxonomia.taxones' => [
            'padre_id' => ['taxonomia.taxones', 'id', 'nombre_cientifico', 'rango', 'uuid'],
        ],
        'taxonomia.localidades' => [
            'padre_id' => ['taxonomia.localidades', 'id', 'nombre_canonico', 'rango', 'uuid'],
            'codigo_inec' => ['recepciones.localidades_ecuador_catalogo', 'codigo', 'nombre', 'canton', 'texto'],
        ],
        'divulgacion.chat_nodes' => ['parent_id' => self::NODOS],
        'divulgacion.chat_aliases' => ['node_id' => self::NODOS],
        'divulgacion.chat_variants' => ['node_id' => self::NODOS],
    ];

    public function tiene(string $tabla, string $campo): bool
    {
        return isset(self::RELACIONES[$tabla][$campo]);
    }

    /** @return array{opciones: list<array{valor: string, etiqueta: string}>, hayMas: bool} */
    public function buscar(string $tabla, string $campo, string $consulta, int $pagina = 0): array
    {
        [$destino, $clave, $nombre, $detalle, $tipo] = self::RELACIONES[$tabla][$campo] ?? abort(404);
        $pagina = max(0, min($pagina, self::ULTIMA_PAGINA));
        $texto = trim(mb_substr($consulta, 0, 100));
        $patron = '%'.addcslashes($texto, '\\%_').'%';
        $filas = DB::table($destino)
            ->when($texto !== '', fn (Builder $filas) => $filas->where(function (Builder $filtro) use ($nombre, $detalle, $clave, $patron, $texto, $tipo): void {
                $filtro->whereLike($nombre, $patron)->orWhereLike($detalle, $patron);
                if ($this->claveValida($texto, $tipo)) {
                    $filtro->orWhere($clave, $texto);
                }
            }))
            ->orderBy($nombre)->orderBy($clave)
            ->offset($pagina * self::POR_PAGINA)
            ->limit(self::POR_PAGINA + 1)
            ->get([$clave, $nombre, $detalle]);

        return [
            'opciones' => $filas->take(self::POR_PAGINA)->map(fn (object $fila): array => [
                'valor' => (string) $fila->{$clave},
                'etiqueta' => $this->rotulo($fila, $nombre, $detalle),
            ])->all(),
            'hayMas' => $pagina < self::ULTIMA_PAGINA && $filas->count() > self::POR_PAGINA,
        ];
    }

    public function etiqueta(string $tabla, string $campo, string $valor): ?string
    {
        [$destino, $clave, $nombre, $detalle, $tipo] = self::RELACIONES[$tabla][$campo] ?? abort(404);
        if (!$this->claveValida($valor, $tipo)) {
            return null;
        }

        $fila = DB::table($destino)->where($clave, $valor)->first([$nombre, $detalle]);

        return $fila ? $this->rotulo($fila, $nombre, $detalle) : null;
    }

    private function claveValida(string $valor, string $tipo): bool
    {
        return match ($tipo) {
            'uuid' => Str::isUuid($valor),
            'entero' => filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false,
            'texto' => $valor !== '' && mb_strlen($valor) <= 64,
        };
    }

    private function rotulo(object $fila, string $nombre, string $detalle): string
    {
        return filled($fila->{$detalle}) ? $fila->{$nombre}.' ('.$fila->{$detalle}.')' : $fila->{$nombre};
    }
}
