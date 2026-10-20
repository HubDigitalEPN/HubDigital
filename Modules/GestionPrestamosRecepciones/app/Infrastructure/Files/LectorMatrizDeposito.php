<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Files;

use Modules\GestionPrestamosRecepciones\Application\Ports\EsquemaMatrizPort;
use Modules\GestionPrestamosRecepciones\Domain\Services\EsquemaMatriz;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

final class LectorMatrizDeposito
{
    public function __construct(private readonly EsquemaMatrizPort $esquemas) {}

    public function leer(string $ruta): array
    {
        $definicion = $this->esquemas->leer();
        $mapa = EsquemaMatriz::mapa($definicion['columnas']);
        $libro = IOFactory::load($ruta);
        try {
            // La tabla va en la primera hoja; consultar la guía y guardar el
            // libro no debe convertir esa guía en la matriz que se importa.
            $hoja = $libro->getSheet(0);
            $alto = $hoja->getHighestDataRow();
            $ancho = Coordinate::columnIndexFromString($hoja->getHighestDataColumn());
            if ($alto > 50000 || $ancho > 500) throw new \DomainException('La matriz admite hasta 50.000 filas y 500 columnas por archivo.');
            $cabeceras = null; $registros = []; $numeros = [];
            for ($fila = 1; $fila <= $alto; $fila++) {
                $valores = [];
                for ($col = 1; $col <= $ancho; $col++) {
                    $celda = $hoja->getCell([$col, $fila]);
                    $valor = $celda->getDataType() === 'f' ? $celda->getOldCalculatedValue() : $celda->getValue();
                    if ($celda->getDataType() === 'f' && $valor === null) throw new \DomainException("Fila {$fila}: reemplaza las fórmulas sin resultado guardado por valores.");
                    $valores[$col] = trim((string) $valor);
                }
                $noVacios = array_filter($valores, static fn ($v): bool => $v !== '');
                if ($noVacios === []) continue;
                if ($cabeceras === null) {
                    $reconocidas = array_filter($valores, static fn ($v): bool => isset($mapa[EsquemaMatriz::clave($v)]));
                    $tieneNombre = array_filter($reconocidas, static fn ($v): bool => $mapa[EsquemaMatriz::clave($v)]['campo'] === 'scientificName') !== [];
                    if (count($reconocidas) < 2 && ! $tieneNombre) continue;
                    $cabeceras = []; $vistos = [];
                    foreach ($valores as $col => $nombre) {
                        if ($nombre === '') continue;
                        $config = $mapa[EsquemaMatriz::clave($nombre)] ?? null;
                        if ($config === null) throw new \DomainException("La columna «{$nombre}» no está configurada. Solicita al curador que la registre o corrija su cabecera.");
                        if (isset($vistos[$config['campo']])) throw new \DomainException("La columna «{$nombre}» está repetida o coincide con otro alias del mismo campo.");
                        $vistos[$config['campo']] = true; $cabeceras[$col] = $config;
                    }
                    continue;
                }
                // La plantilla v2 incluye descripciones y una clave de colores.
                // Se reconocen por su contenido configurado, nunca por número de fila,
                // color o por faltar un dato obligatorio en una observación.
                $esGuia = count($noVacios) === 1 && in_array(EsquemaMatriz::clave(reset($noVacios)), array_map(EsquemaMatriz::clave(...), $definicion['etiquetas_guia'] ?? []), true);
                if (! $esGuia && count($noVacios) > 1) {
                    $esGuia = true;
                    foreach ($noVacios as $col => $valor) {
                        if (EsquemaMatriz::clave($valor) !== EsquemaMatriz::clave($cabeceras[$col]['descripcion'] ?? '')) { $esGuia = false; break; }
                    }
                }
                if ($esGuia) continue;
                $registro = [];
                foreach ($valores as $col => $valor) {
                    if (! isset($cabeceras[$col])) {
                        if ($valor !== '') throw new \DomainException("Fila {$fila}: hay un valor en una columna sin cabecera.");
                        continue;
                    }
                    $config = $cabeceras[$col]; $celda = $hoja->getCell([$col, $fila]);
                    if ($config['formato'] === 'fecha' && $valor !== '' && is_numeric($valor) && Date::isDateTime($celda)) {
                        $valor = Date::excelToDateTimeObject((float) $valor)->format('Y-m-d');
                    }
                    $registro[$config['campo']] = $valor;
                }
                if (trim($registro['scientificName'] ?? '') === '') {
                    foreach ($definicion['identificacion'] ?? [] as $campos) {
                        $partes = array_map(static fn ($c): string => trim($registro[$c] ?? ''), $campos);
                        if ($partes !== [] && ! in_array('', $partes, true)) { $registro['scientificName'] = implode(' ', $partes); break; }
                    }
                }
                $registros[] = $registro; $numeros[] = $fila;
            }
            if ($registros === []) throw new \DomainException('El Excel debe incluir las cabeceras de la matriz y al menos una fila de datos.');
            EsquemaMatriz::validarRegistros($registros, $numeros, $definicion['columnas']);
            foreach ($registros as $i => $registro) {
                if (trim($registro['scientificName'] ?? '') === '') throw new \DomainException('Fila '.$numeros[$i].': completa el nombre científico o la clasificación del espécimen.');
            }
            return ['campos' => array_keys($registros[0]), 'registros' => $registros, 'filas' => $numeros,
                'criticos' => array_column(array_filter($definicion['columnas'], static fn ($c): bool => $c['requisito'] === 'obligatorio' && ($c['excepcion_campo'] ?? '') === ''), 'campo'),
                'recomendados' => array_column(array_filter($definicion['columnas'], static fn ($c): bool => $c['requisito'] === 'recomendado'), 'campo')];
        } finally {
            $libro->disconnectWorksheets();
        }
    }
}
