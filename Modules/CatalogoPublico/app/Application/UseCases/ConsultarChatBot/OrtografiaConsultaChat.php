<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

/** Repara vocabulario de intención, sin reescribir entidades ni la pregunta visible. */
final class OrtografiaConsultaChat
{
    private const CORRECCIONES = [
        'quero' => 'quiero', 'quisera' => 'quisiera', 'nececito' => 'necesito', 'nesecito' => 'necesito',
        'cuatnos' => 'cuantos', 'cuatnas' => 'cuantas', 'cunatos' => 'cuantos', 'cuntas' => 'cuantas',
        'rejistros' => 'registros', 'regsitros' => 'registros', 'regitros' => 'registros', 'regsistros' => 'registros',
        'especes' => 'especies', 'especeis' => 'especies', 'espesies' => 'especies',
        'ejempalres' => 'ejemplares', 'ejempares' => 'ejemplares', 'especimnes' => 'especimenes',
        'catalogoo' => 'catalogo', 'catlaogo' => 'catalogo', 'catalgo' => 'catalogo', 'catologo' => 'catalogo',
        'colecion' => 'coleccion', 'coelccion' => 'coleccion', 'colleccion' => 'coleccion',
        'provinica' => 'provincia', 'provicnia' => 'provincia', 'provicias' => 'provincias',
        'coordenads' => 'coordenadas', 'cordenadas' => 'coordenadas', 'coordenadsa' => 'coordenadas',
        'filros' => 'filtros', 'fitlros' => 'filtros', 'flitros' => 'filtros', 'linpiar' => 'limpiar',
        'despositar' => 'depositar', 'deposiatro' => 'depositar', 'deposiot' => 'deposito',
        'solcitud' => 'solicitud', 'solicutud' => 'solicitud', 'solicitu' => 'solicitud',
        'documetos' => 'documentos', 'docuemntos' => 'documentos', 'documntos' => 'documentos', 'requesitos' => 'requisitos',
        'contrasean' => 'contrasena', 'contarsena' => 'contrasena', 'certifcado' => 'certificado',
        'firmra' => 'firmar', 'descagar' => 'descargar', 'descagra' => 'descarga',
        'porqe' => 'por que', 'porq' => 'por que', 'mostar' => 'mostrar', 'muestrame' => 'muestrame',
    ];

    public function corregir(string $pregunta): string
    {
        if (!ModeloLocalPequeno::habilitado() || mb_strlen($pregunta) > 500) return $pregunta;
        // Citas, identificadores, URLs y números se conservan carácter por carácter.
        return preg_replace_callback('~("[^"]*"|“[^”]*”|\x27[^\x27]*\x27|https?://\S+|[\pL\pN]+(?:[-_:/][\pL\pN]+)+)|\b([\pL]+)\b~u', static function (array $m): string {
            if (($m[1] ?? '') !== '') return $m[0];
            $palabra = $m[2] ?? '';
            // Los nombres propios y las identificaciones científicas no son vocabulario corregible.
            if ($palabra !== mb_strtolower($palabra)) return $palabra;
            return self::CORRECCIONES[$palabra] ?? $palabra;
        }, $pregunta) ?? $pregunta;
    }
}
