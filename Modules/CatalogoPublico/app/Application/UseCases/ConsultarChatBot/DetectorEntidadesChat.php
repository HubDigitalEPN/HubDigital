<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Illuminate\Support\Facades\DB;

/** Reconoce entidades contra el esquema real; los filtros públicos se aplican en ConsultaCatalogoPublico. */
final class DetectorEntidadesChat
{
    public function __construct(private readonly TextoChat $texto) {}

    /** @return array{taxon?:string,provincia?:string,localidad?:string,pais?:string,codigo?:string} */
    public function extraer(string $pregunta): array
    {
        if (preg_match('/\b(?:buscar|c[oó]digo|catalogo)\s+([A-Za-z0-9][A-Za-z0-9._:-]{2,80})/iu', $pregunta, $match)
            && ! in_array($this->texto->normalizar($match[1]), ['especimenes', 'especies', 'registros', 'taxones', 'familias'], true)
            && (preg_match('/[0-9._:-]/', $match[1]) || DB::table('taxonomia.especimenes as e')
                ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
                ->where('d.occurrence_id_visible', true)->whereRaw('lower(e.occurrence_id) = lower(?)', [$match[1]])->exists())) {
            return ['codigo' => $match[1]];
        }
        preg_match_all('/\b[A-ZÁÉÍÓÚ][a-záéíóúñ]{2,}\b/u', $pregunta, $matches);
        $omitidos = ['que', 'cual', 'cuantos', 'cuantas', 'tienen', 'tengo', 'quiero', 'busca', 'buscar', 'especimenes', 'registros', 'genero', 'familia', 'hola', 'buenos', 'buenas', 'gracias', 'necesito', 'donde', 'muestra', 'existe'];
        $result = [];
        foreach ($matches[0] as $principal) {
            if (in_array($this->texto->normalizar($principal), $omitidos, true)) {
                continue;
            }
            $binomio = preg_match('/\b'.preg_quote($principal, '/').'\s+([a-z]{3,})\b/u', $pregunta, $epiteto)
                ? $principal.' '.$epiteto[1] : null;
            foreach (array_filter([$binomio, $principal]) as $candidato) {
                $taxon = DB::table('taxonomia.taxones')->whereRaw('lower(nombre_cientifico) = lower(?)', [$candidato])->value('nombre_cientifico');
                if ($taxon !== null) {
                    $result['taxon'] = $taxon;
                    continue 2;
                }
            }
            // La geografía solo se reconoce si hay registros divulgables con el campo visible.
            $geografia = DB::table('taxonomia.especimenes as e')
                ->join('divulgacion.especimenes_divulgables as d', 'd.especimen_id', '=', 'e.id')
                ->where(function ($query) use ($principal): void {
                    $query->where(fn ($q) => $q->where('d.state_province_visible', true)->whereRaw('lower(e.state_province) = lower(?)', [$principal]))
                        ->orWhere(fn ($q) => $q->where('d.locality_name_visible', true)->whereRaw('lower(e.locality_name) = lower(?)', [$principal]))
                        ->orWhere(fn ($q) => $q->where('d.country_visible', true)->whereRaw('lower(e.country) = lower(?)', [$principal]));
                })->first(['e.state_province', 'e.locality_name', 'e.country', 'd.state_province_visible', 'd.locality_name_visible', 'd.country_visible']);
            if ($geografia) {
                if ($geografia->state_province_visible && mb_strtolower((string) $geografia->state_province) === mb_strtolower($principal)) {
                    $result['provincia'] = $geografia->state_province;
                    continue;
                }
                if ($geografia->country_visible && mb_strtolower((string) $geografia->country) === mb_strtolower($principal)) {
                    $result['pais'] = $geografia->country;
                    continue;
                }
                $result['localidad'] = $geografia->locality_name;
                continue;
            }
            if (! isset($result['taxon']) && $binomio !== null && preg_match('/(?:tax[oó]n|especie|g[eé]nero|ejemplares|registros|busca|cu[aá]ntos)/iu', $pregunta)) {
                $result['taxon'] = $binomio;
                continue;
            }
            if (! isset($result['taxon']) && preg_match('/(?:tax[oó]n|especie|g[eé]nero|ejemplares|registros|busca|tienen|cu[aá]ntos)/iu', $pregunta)) {
                $result['taxon'] = $principal;
            }
        }
        if (! isset($result['taxon']) && preg_match('/\b(tienen|taxon|taxones|especie|genero|familia|registros|busca|buscar|decir|corregir)\b/u', $this->texto->normalizar($pregunta))) {
            $words = preg_split('/[^\p{L}]+/u', $pregunta, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $candidates = [];
            foreach (array_slice($words, 0, 18) as $index => $word) {
                $word = $this->texto->normalizar($word);
                if (strlen($word) < 4 || in_array($word, $omitidos, true)
                    || in_array($word, ['perdon', 'queria', 'decir', 'pero', 'ahora', 'tambien', 'cuantos', 'cuantas', 'dentro', 'sobre'], true)) {
                    continue;
                }
                $next = isset($words[$index + 1]) ? $this->texto->normalizar($words[$index + 1]) : '';
                array_push($candidates, ...array_filter([$next !== '' && strlen($next) >= 4 ? $word.' '.$next : null, $word]));
            }
            if ($candidates !== []) {
                $found = DB::table('taxonomia.taxones')->whereIn(DB::raw('lower(nombre_cientifico)'), $candidates)
                    ->pluck('nombre_cientifico')->mapWithKeys(static fn ($name) => [mb_strtolower($name) => $name])->all();
                foreach ($candidates as $candidate) {
                    if (isset($found[$candidate])) {
                        $result['taxon'] = $found[$candidate];
                        break;
                    }
                }
            }
        }
        return $result;
    }
}
