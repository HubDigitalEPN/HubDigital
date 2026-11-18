<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Support;

/** Etiquetas y ayudas de captura; las reglas del servidor siguen siendo obligatorias. */
final class AyudasCapturaCatalogo
{
    public static function campos(): array
    {
        return [
            'codigoCatalogo' => ['Código de Catálogo', 'Código único de esta ficha dentro de la colección. Permite encontrarla y evita crear otra ficha con el mismo código.', 'MEPN-INV-12345', 'Obligatorio. Hasta 100 caracteres.'],
            'taxonId' => ['Taxón', 'Selecciona el nombre y el rango de un taxón activo del catálogo. Puedes elegir un género cuando la identificación aún no llega a especie.', 'Dolichoderus (género)', 'Obligatorio. Selecciona una opción del catálogo.'],
            'fechaColecta' => ['Fecha de Colecta', 'Día en que se recolectó el material, según la etiqueta o el cuaderno de campo. Es distinta de la fecha en que se ingresa la ficha.', '2026-11-17', 'Obligatorio en el alta. Fecha completa con año, mes y día.'],
            'colector' => ['Colector', 'Persona o equipo que recolectó el material. Transcribe el nombre registrado en la fuente.', 'Ana Pérez; Luis Gómez', 'Obligatorio en el alta. Hasta 255 caracteres.'],
            'occurrenceId' => ['Occurrence ID', 'Identificador persistente de la ocurrencia biológica. Conserva el identificador de origen si existe; es independiente del código interno de esta ficha.', 'urn:uuid:3fc0b339-4214-40c6-8391-f9e15c280b97', 'Opcional. Hasta 120 caracteres.'],
            'catalogNumber' => ['Catalog Number', 'Número con el que la colección o la fuente identifica el material. Conserva su escritura original, incluidos prefijos y ceros.', 'MEPN-INV-12345', 'Opcional. Hasta 120 caracteres.'],
            'oldCode' => ['Código anterior (oldCode)', 'Código histórico que tenía el material antes de su identificación actual. Permite relacionarlo con etiquetas y archivos anteriores.', 'INV-ANT-0042', 'Opcional. Hasta 120 caracteres.'],
            'cardexLiquidCollectionCode' => ['Cardex líquido', 'Código del cardex o inventario de la colección conservada en líquido, cuando corresponda.', 'CL-0042', 'Opcional. Hasta 120 caracteres.'],
            'localidad' => ['Localidad · reserva, parque o área protegida', 'Nombre de la reserva, parque u otra área de colecta. Conserva aquí el área; el cantón y el sector tienen sus propios campos.', 'Parque Nacional Yasuní', 'Opcional. Hasta 255 caracteres.'],
            'localidad2' => ['Localidad 2 · cantón o parroquia', 'Cantón o parroquia que consta en la información de colecta. No reemplaza el nombre del área protegida.', 'Quito', 'Opcional. Hasta 255 caracteres.'],
            'localidad3' => ['Localidad 3 · localidad, sector, ruta o vía', 'Descripción específica del sitio dentro del área de colecta: sector, sendero, ruta, vía o punto de muestreo.', 'Sendero norte, punto 3', 'Opcional. Hasta 500 caracteres.'],
            'country' => ['País', 'País donde se recolectó el material. Escribe el país real; una ciudad o un sector no debe ocupar este campo.', 'Ecuador', 'Opcional. Hasta 120 caracteres.'],
            'stateProvince' => ['Provincia', 'Provincia o división administrativa equivalente del sitio de colecta.', 'Pichincha', 'Opcional. Hasta 120 caracteres.'],
            'municipality' => ['Municipio', 'Municipio o cantón consignado como división administrativa en la fuente Darwin Core. Se conserva además del desglose de localidades.', 'Quito', 'Opcional. Hasta 120 caracteres.'],
            'localityName' => ['Nombre de localidad (DwC)', 'Nombre o descripción del sitio en la fuente Darwin Core. Las comas pueden separar detalles del lugar; no convierten una ciudad en país. El texto completo se conserva como localidad original.', 'Quito, sector norte', 'Opcional. Hasta 500 caracteres.'],
            'decimalLatitude' => ['Latitud', 'Coordenada norte-sur en grados decimales. Al sur del ecuador usa un valor negativo. No escribas grados, minutos ni letras en este campo.', '-0.180000', 'Opcional. Número entre -90 y 90; usa punto decimal.'],
            'decimalLongitude' => ['Longitud', 'Coordenada este-oeste en grados decimales. En Ecuador normalmente es negativa. Si desconoces la coordenada, deja el campo vacío.', '-78.490000', 'Opcional. Número entre -180 y 180; usa punto decimal.'],
            'geodeticDatum' => ['Datum', 'Sistema de referencia usado para medir las coordenadas. Transcribe el de la fuente; no asumas uno diferente.', 'WGS84', 'Opcional. Hasta 60 caracteres.'],
            'elevationMinM' => ['Elevación mínima (m)', 'Elevación mínima del sitio de colecta expresada en metros respecto del nivel del mar. Escribe únicamente el número.', '2850', 'Opcional. Número; usa punto si contiene decimales.'],
            'individualCount' => ['Individuos', 'Cantidad de individuos representados por esta ficha. Un cero es un valor real; si la cantidad se desconoce, deja el campo vacío.', '2', 'Opcional. Número entero entre 0 y 2147483647.'],
            'preparations' => ['Preparación', 'Forma de preparación o conservación del material descrita en la fuente.', 'ethanol 80%; alfiler entomológico', 'Opcional. Hasta 120 caracteres.'],
            'disposition' => ['Disposición', 'Situación o destino del material según la fuente. Este texto no sustituye los estados de custodia del sistema.', 'in collection', 'Opcional. Hasta 120 caracteres.'],
            'occurrenceStatus' => ['Estado de ocurrencia (DwC)', 'Presencia o ausencia registrada para la ocurrencia biológica. No es el estado físico ni la disponibilidad de un préstamo.', 'present', 'Opcional. Hasta 120 caracteres.'],
            'biome' => ['Bioma', 'Gran unidad ecológica a la que pertenece el sitio de colecta, cuando está documentada.', 'Bosque montano', 'Opcional. Hasta 120 caracteres.'],
            'habitat' => ['Hábitat', 'Ambiente específico en el que se encontró el material.', 'Hojarasca del bosque', 'Opcional. Hasta 255 caracteres.'],
            'specimenNotes' => ['Notas', 'Observaciones adicionales del espécimen o lote que ayuden a interpretar la ficha y su fuente.', 'Material conservado en dos frascos.', 'Opcional. Conserva las observaciones pertinentes al registro.'],
            'entidadDepositanteId' => ['Entidad depositante (opcional)', 'Persona o institución depositante del material. Si no consta, conserva la opción sin entidad; no elijas una institución por semejanza de nombre.', 'Seleccionar una entidad registrada', 'Opcional. Selecciona una opción del directorio.'],
        ];
    }

    public static function campo(string $modelo): array
    {
        $edicion = str_starts_with($modelo, 'edit');
        $clave = $edicion ? lcfirst(substr($modelo, 4)) : $modelo;
        $datos = self::campos()[$clave] ?? throw new \InvalidArgumentException('Campo de captura sin ayuda: '.$modelo);
        $maximos = ['codigoCatalogo'=>100, 'occurrenceId'=>120, 'catalogNumber'=>120, 'oldCode'=>120,
            'cardexLiquidCollectionCode'=>120, 'localidad'=>255, 'localidad2'=>255, 'localidad3'=>500,
            'country'=>120, 'stateProvince'=>120, 'municipality'=>120, 'localityName'=>500, 'colector'=>255,
            'preparations'=>120, 'disposition'=>120, 'occurrenceStatus'=>120, 'geodeticDatum'=>60,
            'biome'=>120, 'habitat'=>255];
        return ['titulo'=>$datos[0], 'descripcion'=>$datos[1], 'ejemplo'=>$datos[2],
            'regla'=>$edicion && in_array($clave, ['fechaColecta','colector'], true)
                ? 'Opcional al editar una ficha histórica. No es necesario inventar un dato ausente.' : $datos[3],
            'obligatorio'=>!$edicion && in_array($clave, ['codigoCatalogo','taxonId','fechaColecta','colector'], true),
            'tipo'=>$clave === 'fechaColecta' ? 'date' : ($clave === 'specimenNotes' ? 'textarea' : (in_array($clave, ['taxonId','entidadDepositanteId'], true) ? 'select' : 'text')),
            'maxlength'=>$edicion && $clave === 'specimenNotes' ? 5000 : ($maximos[$clave] ?? null),
            'inputmode'=>$clave === 'individualCount' ? 'numeric' : (in_array($clave, ['decimalLatitude','decimalLongitude','elevationMinM'], true) ? 'decimal' : null)];
    }

    public static function atributosValidacion(): array
    {
        $salida = [];
        foreach (self::campos() as $campo=>$datos) {
            $salida[$campo] = $datos[0];
            $salida['edit'.ucfirst($campo)] = $datos[0];
        }
        return $salida;
    }

    public static function importacion(string $campo): array
    {
        $datos = match ($campo) {
            'archivo'=>['Matriz Excel o CSV', 'Selecciona el archivo con una fila de encabezados y un registro por fila. Valida la matriz, revisa el resumen y después importa. La validación por sí sola no guarda especímenes.', 'Excel .xlsx o .xls; CSV o TXT en UTF-8.', 'Máximo 20 MB. Usa occurrenceID o catalogNumber para identificar cada ficha.'],
            'modo'=>['Cómo incorporar los datos', 'Completar llena datos vacíos y conserva los valores existentes. Actualizar aplica los valores proporcionados en la matriz. Las columnas omitidas no deben borrar el contexto existente. Revisa siempre la vista previa.', 'Actualizar: corregir una cantidad. Completar: añadir una provincia que faltaba.', 'Cambiar el modo invalida la vista previa y exige volver a validar.'],
            'delimitador'=>['Separador CSV', 'Elige el carácter que separa las columnas de tu archivo de texto. No es el separador decimal. Si toda la cabecera se interpreta como una sola columna, revisa esta opción.', 'occurrenceID;individualCount usa punto y coma.', 'Coma, punto y coma o tabulación. Excel no necesita esta opción.'],
            'observaciones'=>['Observaciones de importación', 'Las fechas históricas parciales y otros valores no interpretables se conservan como texto original; no se inventan fechas, cantidades o coordenadas normalizadas. Revisa los avisos antes de importar. Una cantidad negativa o fuera del rango permitido bloquea la carga.', 'Una coordenada fuera de rango se conserva en el texto original, sin publicarla como coordenada válida.', 'La identificación a nivel de género puede quedar como provisional sin crear una especie confirmada.'],
            default=>throw new \InvalidArgumentException('Ayuda de importación desconocida.'),
        };
        return ['titulo'=>$datos[0], 'descripcion'=>$datos[1], 'ejemplo'=>$datos[2], 'regla'=>$datos[3]];
    }
}
