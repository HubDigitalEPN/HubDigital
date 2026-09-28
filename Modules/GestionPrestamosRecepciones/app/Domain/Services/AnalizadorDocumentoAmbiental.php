<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Domain\Services;

/**
 * Clasifica y estructura documentos ambientales ecuatorianos por su contenido.
 *
 * Las reglas son deterministas, versionadas y se aplican al texto extraído localmente.
 * El nombre y la disposición visual del archivo no participan en la clasificación.
 * Ningún campo se considera autocompletable si no conserva evidencia textual propia.
 */
final class AnalizadorDocumentoAmbiental
{
    public const AUTORIZACION_RECOLECCION = 'autorizacion_recoleccion';

    public const GUIA_MOVILIZACION = 'guia_movilizacion';

    public const DESCONOCIDO = 'desconocido';

    public const VERSION_REGLAS = 'ec-autoridad-ambiental-2026.09.1';

    private const UMBRAL_CLASIFICACION = 0.58;

    private const MARGEN_MINIMO = 0.14;

    private const UMBRAL_CAMPO_PROPUESTO = 0.78;

    /** @return array<string, mixed> */
    public function analizar(string $texto): array
    {
        $texto = $this->normalizarOcr($texto);
        $seccionMuestras = $this->seccionMuestras($texto);
        $detallesPuntaje = [
            self::AUTORIZACION_RECOLECCION => $this->puntaje($texto, [
                ['aut-v3-titulo-diversidad', '/(?:autorizaci[oó0]n|permiso)\s+(?:para\s+|de\s+)?recolecci[oó0]n\s+de\s+espec[ií1]menes\s+de\s+especies\s+de\s+la\s+diversidad\s+biol[oó0]gica/iu', 0.38, 'titulo'],
                ['aut-v2-titulo-silvestre', '/autorizaci[oó0]n\s+de\s+recolecci[oó0]n\s+de\s+(?:espec[ií1]menes\s+de\s+)?vida\s+silvestre/iu', 0.38, 'titulo'],
                ['aut-v1-permiso-conjunto', '/autorizaci[oó0]n\s+de\s+(?:permisos?\s+de\s+)?recolecci[oó0]n(?:\s+y\s+movilizaci[oó0]n)?\s+de\s+espec[ií1]menes/iu', 0.34, 'titulo'],
                ['aut-v1-recolecta', '/autorizaci[oó0]n\s+de\s+recolecta\s+de\s+espec[ií1]menes/iu', 0.34, 'titulo'],
                ['aut-acto-autoriza', '/(?:autoriza|otorga|concede)(?:\s+la)?\s+(?:presente\s+)?autorizaci[oó0]n(?:\s+de\s+recolecci[oó0]n(?:\s+de\s+vida\s+silvestre)?)?(?:\s+a|\s+n[º°]|\s+nro)/iu', 0.20, 'acto'],
                ['aut-vigencia', '/vigencia\s+de\s+la\s+autorizaci[oó0]n\s+de\s+recolecci[oó0]n/iu', 0.15, 'vigencia'],
                ['aut-duracion-suia', '/duraci[oó0]n\s+del\s+proyecto[\s\S]{0,80}?fecha\s+inicio[\s\S]{0,40}?fecha\s+fin/iu', 0.15, 'vigencia'],
                ['aut-area', '/(?:[aá]rea|rea)\s+geogr[aá]fica\s+que\s+cubre\s+la\s+recolecci[oó0]n/iu', 0.12, 'alcance'],
                ['aut-metodo', '/metodolog[ií1]a\s+para\s+recolectar|m[eé]todos?\s+de\s+preservaci[oó0]n\s+y\s+movilizaci[oó0]n/iu', 0.10, 'alcance'],
                ['aut-grupo', '/grupo\s+biol[oó0]gico\s+a\s+estudiar/iu', 0.10, 'alcance'],
                ['aut-deposito', '/centro\s+de\s+documentaci[oó0]n\s+de\s+la\s+biodiversidad|depositar\s+los\s+espec[ií1]menes/iu', 0.08, 'obligaciones'],
                ['aut-obligacion-guia', '/solicitar\s+oportunamente\s+la\s+gu[ií1]a\s+de\s+movilizaci[oó0]n/iu', 0.08, 'obligaciones'],
                ['aut-oficio', '/oficio\s+(?:nro|no|n[úu]mero)?\.?\s*(?:MAATE|MAAE|MAE|MATE)-/iu', 0.08, 'codigo'],
                ['aut-autoridad', $this->patronAutoridad(), 0.08, 'autoridad'],
            ]),
            self::GUIA_MOVILIZACION => $this->puntaje($texto, [
                ['mov-v3-titulo', '/gu[ií1]a\s+de\s+movilizaci[oó0]n(?:\s+de|\s+para)?\s+(?:muestras?|espec[ií1]menes|vida\s+silvestre|flora|fauna)/iu', 0.44, 'titulo'],
                ['mov-v2-permiso', '/permiso\s+de\s+movilizaci[oó0]n\s+(?:de|para)\s+(?:muestras?|espec[ií1]menes|vida\s+silvestre)/iu', 0.40, 'titulo'],
                ['mov-v1-traslado', '/autorizaci[oó0]n\s+para\s+(?:la\s+)?movilizaci[oó0]n\s+de\s+(?:muestras?|espec[ií1]menes)/iu', 0.36, 'titulo'],
                ['mov-suia-autorizacion', '/autorizaci[oó0]n\s+de\s+movilizaci[oó0]n\s+de\s+espec[ií1]menes(?:\s+de\s+especies\s+de\s+la\s+diversidad\s+biol[oó0]gica)?/iu', 0.42, 'titulo'],
                ['mov-fecha', '/fecha\s+(?:prevista\s+)?de\s+(?:movilizaci[oó0]n|traslado|salida)/iu', 0.14, 'vigencia'],
                ['mov-vigencia', '/v[aá]lido\s+hasta|fecha\s+(?:de\s+)?(?:fin|vencimiento)/iu', 0.10, 'vigencia'],
                ['mov-ruta', '/(?:lugar|punto)\s+de\s+(?:origen|partida)|(?:lugar|punto)\s+de\s+destino|\bdesde\s+(?!(?:el\s+)?(?:d[ií1]a\s+)?[0-3]?\d(?:\s+de|[.\/-]))[\s\S]{4,180}\bhasta\s+(?!(?:el\s+)?(?:d[ií1]a\s+)?[0-3]?\d(?:\s+de|[.\/-]))/iu', 0.12, 'ruta'],
                ['mov-transporte', '/responsable\s+de\s+la\s+movilizaci[oó0]n|medio\s+de\s+transporte|tipo\s+de\s+transporte|placa\s*[:#-]/iu', 0.12, 'transporte'],
                ['mov-muestras', '/datos\s+de\s+las\s+muestras|detalle\s+de\s+(?:muestras|espec[ií1]menes)|(?:material\s+biol[oó0]gico|espec[ií1]menes)\s+a\s+movilizar|c[oó0]digo\s+campo\s+nombre\s+com[uú]n/iu', 0.12, 'muestras'],
                ['mov-autorizacion-base', '/(?:autorizaci[oó0]n|permiso)\s+(?:para\s+|de\s+)?recolecci[oó0]n/iu', 0.08, 'relacion'],
                ['mov-autoridad', $this->patronAutoridad(), 0.06, 'autoridad'],
            ]),
        ];

        $puntajes = array_map(
            static fn (array $detalle): float => (float) $detalle['puntaje'],
            $detallesPuntaje,
        );
        arsort($puntajes);
        $tipoCandidato = (string) array_key_first($puntajes);
        $confianza = (float) reset($puntajes);
        $segundo = (float) (array_values($puntajes)[1] ?? 0.0);
        $margen = $confianza - $segundo;
        $estructuraEsencial = $this->estructuraEsencial($tipoCandidato, $detallesPuntaje[$tipoCandidato]);
        $tipo = $confianza >= self::UMBRAL_CLASIFICACION
            && $margen >= self::MARGEN_MINIMO
            && $estructuraEsencial
                ? $tipoCandidato
                : self::DESCONOCIDO;

        $oficio = $this->resolverCodigo($texto, [
            ['oficio-quipux-v2', '/oficio\s+(?:nro|no|n[úu]mero)?\.?\s*[:#-]?\s*((?:MAATE|MAAE|MAE|MATE)\s*-\s*[A-Z0-9]{2,16}\s*-\s*(?:19|20)\d{2}\s*-\s*\d{2,8}\s*-\s*(?:O|OF))\b/iu', 0.98],
            ['oficio-generico-v1', '/oficio\s+(?:nro|no|n[úu]mero)?\.?\s*[:#-]?\s*([A-Z]{2,12}(?:\s*[-\/]\s*[A-Z0-9]{1,18}){3,10})\b/iu', 0.88],
        ]);
        $autorizacion = $this->resolverCodigo($texto, [
            ['aut-codigo-anio-inicial-v3', '/(?:autorizaci[oó0]n|permiso)\s+(?:para\s+|de\s+)?recolecci[oó0]n(?:\s+de\s+(?:espec[ií1]menes\s+de\s+especies\s+de\s+la\s+diversidad\s+biol[oó0]gica|espec[ií1]menes|vida\s+silvestre))?[\s\S]{0,100}?(?:n(?:ro|o|[úu]mero)\.?|n[º°])?\s*[:#-]?\s*((?:[0-9]\s*){1,4}-\s*(?:19|20)\d{2}(?:\s+|\s*[-\/]\s*)[A-Z]{1,12}(?:\s*[-\/]+\s*[A-Z0-9]{1,16}){1,10})/iu', 0.98],
            ['aut-codigo-anio-final-v2', '/(?:autorizaci[oó0]n|permiso)\s+(?:para\s+|de\s+)?recolecci[oó0]n[\s\S]{0,100}?(?:n(?:ro|o|[úu]mero)\.?|n[º°])?\s*[:#-]?\s*([0-9]{1,4}(?:\s*[-\/]+\s*[A-Z0-9]{2,16}){2,10}\s*[-\/]+\s*(?:19|20)\d{2})/iu', 0.96],
            ['aut-codigo-suia-v1', '/(?:autorizaci[oó0]n|permiso)\s+(?:para\s+|de\s+)?recolecci[oó0]n[\s\S]{0,80}?(?:n(?:ro|o|[úu]mero)\.?|n[º°])?\s*[:#-]?\s*((?:MAATE|MAAE|MAE)-SUIA(?:\s*[-\/]+\s*[A-Z0-9]{1,18}){1,8})/iu', 0.94],
            ['aut-codigo-suia-etiqueta-v2', '/(?:autorizaci[oó0]n\s+de\s+recolecta[\s\S]{0,120}?)?c[oó0]digo\s*[:#-]?\s*((?:MAATE|MAAE|MAE)(?:\s*[-\/]\s*[A-Z0-9]{1,18}){2,10})/iu', 0.96],
            ['aut-referencia-guia-v2', '/n[º°]\s*autorizaci[oó0]n\s*[:#-]?\s*(?:n[º°]\s*)?([0-9]{1,4}(?:\s*[-\/]\s*[A-Z0-9]{1,18}){2,12})/iu', 0.96],
        ]);
        $guia = $this->resolverCodigo($texto, [
            ['mov-codigo-estructurado-v3', '/(?:gu[ií1]a|permiso)\s+de\s+movilizaci[oó0]n(?:\s+de\s+(?:muestras?|espec[ií1]menes|vida\s+silvestre|flora|fauna))?[\s\S]{0,90}?(?:n(?:ro|o|[úu]mero)\.?|n[º°])\s*[:#-]?\s*([0-9]{1,4}(?:\s*[-\/]+\s*[A-Z0-9]{1,18}){2,12})/iu', 0.98],
            ['mov-codigo-anio-v2', '/(?:gu[ií1]a|permiso)\s+de\s+movilizaci[oó0]n[\s\S]{0,80}?\b([0-9]{1,4}\s*-\s*(?:19|20)\d{2}(?:\s*[-\/]\s*[A-Z0-9]{1,18}){1,10})\b/iu', 0.95],
            ['mov-codigo-maate-v2', '/gu[ií1]a\s+de\s+movilizaci[oó0]n[\s\S]{0,90}?(?:n(?:ro|o|[úu]mero)\.?|n[º°])\s*[:#-]?\s*((?:MAATE|MAAE|MAE)(?:\s*[-\/]+\s*[A-Z0-9]{1,18}){2,10})\b/iu', 0.98],
            ['mov-suia-numero-v1', '/autorizaci[oó0]n\s+de\s+movilizaci[oó0]n[\s\S]{0,160}?gu[ií1]a\s+n[º°]\.?\s*[:#-]?\s*(\d{1,6})\b/iu', 0.92],
            ['mov-numero-simple-v1', '/gu[ií1]a\s+de\s+movilizaci[oó0]n[\s\S]{0,50}?(?:n(?:ro|o|[úu]mero)?\.?|n[º°])\s*[:#-]?\s*(\d{1,5})\b/iu', 0.72],
        ]);

        $numeroDocumentoHallazgo = match ($tipo) {
            self::GUIA_MOVILIZACION => $guia,
            self::AUTORIZACION_RECOLECCION => $oficio['valor'] !== null ? $oficio : $autorizacion,
            default => $this->hallazgoVacio(),
        };

        $rangoAutorizacion = $this->rangoFechas($texto, [
            ['aut-vigencia-rango-v3', '/vigencia\s+de\s+la\s+autorizaci[oó0]n(?:\s+de\s+recolecci[oó0]n)?\s*[:#-]?\s*(?:desde\s+)?('.$this->patronFecha().')\s*(?:hasta|al)\s*('.$this->patronFecha().')/iu', 0.97],
            ['aut-periodo-rango-v1', '/per[ií1]odo\s+autorizado\s*[:#-]?\s*(?:desde\s+)?('.$this->patronFecha().')\s*(?:hasta|al)\s*('.$this->patronFecha().')/iu', 0.93],
            ['aut-duracion-suia-v1', '/duraci[oó0]n\s+del\s+proyecto[\s\S]{0,100}?fecha\s+inicio[\s\S]{0,40}?fecha\s+fin\s+('.$this->patronFecha().')\s+('.$this->patronFecha().')/iu', 0.92],
        ]);
        $emision = $this->resolverFecha($texto, [
            ['fecha-emision-v3', '/fecha\s+(?:de\s+)?(?:emisi[oó0]n|expedici[oó0]n|suscripci[oó0]n)\s*[:#-]?\s*('.$this->patronFecha().')/iu', 0.96],
            ['fecha-ciudad-quipux-v2', '/\b(?:Quito|Tena|Loja|Machala|Guayaquil|Cuenca|Zamora|Puyo|Ibarra)\s*,\s*(?:a\s+)?('.$this->patronFecha().')/iu', 0.84],
        ]);
        $inicioMovilizacion = $this->resolverFecha($texto, [
            ['mov-fecha-salida-v3', '/fecha\s+(?:prevista\s+)?de\s+(?:movilizaci[oó0]n|traslado|salida)\s*[:#-]?\s*(?:desde\s*[:#-]?\s*)?('.$this->patronFecha().')/iu', 0.97],
            ['mov-desde-v1', '/vigencia\s+(?:de\s+la\s+gu[ií1]a)?\s*[:#-]?\s*desde\s+('.$this->patronFecha().')/iu', 0.90],
        ]);
        $finMovilizacion = $this->resolverFecha($texto, [
            ['mov-valido-hasta-v3', '/(?:v[aá]lido\s+hasta|fecha\s+(?:de\s+)?(?:fin|vencimiento))\s*[:#-]?\s*('.$this->patronFecha().')/iu', 0.97],
            ['mov-vigencia-hasta-v1', '/vigencia\s+(?:de\s+la\s+gu[ií1]a)?[\s\S]{0,70}?\bhasta\s+('.$this->patronFecha().')/iu', 0.90],
            ['mov-fecha-rango-hasta-v2', '/fecha\s+(?:prevista\s+)?de\s+(?:movilizaci[oó0]n|traslado)[\s\S]{0,80}?\bhasta\s*[:#-]?\s*('.$this->patronFecha().')/iu', 0.95],
        ]);

        $titular = $this->resolverTexto($texto, [
            ['titular-solicitud-v3', '/solicitud\s+de\s*:\s*(?:Ing\.?|Lic\.?|Dr\.?|Dra\.?)?\s*([^,\r\n]{5,120})/iu', 0.94],
            ['titular-etiqueta-v2', '/(?:titular|beneficiari[oa]|solicitante|responsable\s+t[eé]cnico)\s*[:#-]\s*([\p{L}][\p{L}\s.\'-]{4,120})/iu', 0.91],
            ['titular-autoriza-v1', '/autoriza\s+a\s*:\s*([^,\r\n]{5,140})/iu', 0.88],
            ['titular-oficio-v1', '/se[ñn]or\s+(?:ingeniero\s+|licenciado\s+)?([\p{L}][\p{L}\s.\'-]{4,120})\s+gerente\s+general/iu', 0.88],
        ]);
        $organizacion = $this->resolverTexto($texto, [
            ['org-representante-v3', '/representante\s+legal\s+de\s+([^,\r\n]{3,140})/iu', 0.96],
            ['org-otorgamiento-v2', '/(?:otorga|concede)\s+(?:la\s+)?autorizaci[oó0]n\s+a\s+([^,\r\n]{3,140}?)(?=\s+para\s+(?:el|la)\s+(?:proyecto|estudio)|[,.\r\n])/iu', 0.90],
            ['org-auspicio-v1', '/(?:consultora\s+ambiental|auspicio\s+de|instituci[oó0]n)\s*[:#-]?\s*([^,\r\n]{3,140})/iu', 0.84],
            ['org-gerente-v1', '/gerente\s+general\s+(?:de\s+)?([A-Z][A-Z0-9 ._-]{2,100})(?=\s+En\s+su\s+Despacho|\s*,|\r?\n)/u', 0.86],
        ]);
        $ruc = $this->resolverTexto($texto, [
            ['ruc-v2', '/\bR\.?\s*U\.?\s*C\.?\s*[:#-]?\s*(\d(?:[\s.-]?\d){12})\b/iu', 0.98],
        ], true);
        if ($ruc['valor'] !== null) {
            $ruc['valor'] = preg_replace('/\D/', '', (string) $ruc['valor']) ?: null;
        }
        $proyecto = $this->resolverTexto($texto, [
            ['proyecto-comillas-v3', '/(?:proyecto|estudio)(?:\s+denominado)?\s*[:#-]?\s*[“"]([^”"]{8,300})[”"]/iu', 0.95],
            ['proyecto-linea-v2', '/(?:para\s+realizar\s+(?:en\s+)?el\s+estudio|proyecto|estudio)\s*[:#-]\s*([^\r\n]{8,300})/iu', 0.84],
        ]);
        $origen = $this->resolverTexto($texto, [
            ['ruta-origen-etiqueta-v3', '/(?:(?:lugar|punto)\s+de\s+(?:origen|partida)|procedencia)\s*[:#-]\s*([^\r\n]{3,180})/iu', 0.92],
            ['ruta-origen-suia-v1', '/\borigen\s+(?:provincia\s+)?([\p{L}]{3,40})(?=\s+tipo\s+de\s+transporte|\r?\n)/iu', 0.82],
            ['ruta-desde-hasta-v3', '/\bdesde\s+(?!(?:el\s+)?(?:d[ií1]a\s+)?[0-3]?\d(?:\s+de|[.\/-]))(?:el\s+)?(?:sector\s+)?(.{4,220}?)\s*,?\s+hasta\s+(?!(?:el\s+)?(?:d[ií1]a\s+)?[0-3]?\d(?:\s+de|[.\/-]))/isu', 0.86],
        ]);
        $destino = $this->resolverTexto($texto, [
            ['ruta-destino-etiqueta-v3', '/(?:lugar|punto)\s+de\s+destino\s*[:#-]\s*([^\r\n]{3,180})/iu', 0.92],
            ['ruta-hasta-v3', '/\bhasta\s+(?!(?:el\s+)?(?:d[ií1]a\s+)?[0-3]?\d(?:\s+de|[.\/-]))(?:el\s+)?(.{4,220}?)(?=\.|\r?\n\s*(?:datos|detalle)\s+de\s+(?:las\s+)?(?:muestras|espec[ií1]menes))/isu', 0.86],
        ]);

        $validoDesde = $tipo === self::AUTORIZACION_RECOLECCION
            ? $rangoAutorizacion['desde']
            : $inicioMovilizacion;
        $validoHasta = $tipo === self::AUTORIZACION_RECOLECCION
            ? $rangoAutorizacion['hasta']
            : $finMovilizacion;
        $emitidoEn = $emision;

        $campos = [
            'numero_documento' => $numeroDocumentoHallazgo,
            'numero_autorizacion' => $autorizacion,
            'titular' => $titular,
            'organizacion' => $organizacion,
            'ruc' => $ruc,
            'proyecto' => $proyecto,
            'emitido_en' => $emitidoEn,
            'valido_desde' => $validoDesde,
            'valido_hasta' => $validoHasta,
            'origen' => $origen,
            'destino' => $destino,
        ];
        $evidenciasCampos = [];
        $confianzasCampos = [];
        foreach ($campos as $campo => $hallazgo) {
            if (($hallazgo['valor'] ?? null) !== null && ($hallazgo['evidencia'] ?? null) !== null) {
                $evidenciasCampos[$campo] = [
                    'fragmento' => $hallazgo['evidencia'],
                    'patron' => $hallazgo['patron'],
                    'version_reglas' => self::VERSION_REGLAS,
                ];
                $confianzasCampos[$campo] = $hallazgo['confianza'];
            }
        }

        $codigosMuestra = $this->codigosMuestra($seccionMuestras);
        $gruposBiologicos = $this->gruposBiologicos($texto);
        $numeroIndividuos = $this->cantidadConEvidencia($seccionMuestras !== '' ? $seccionMuestras : $texto, ['individuos', 'individuo']);
        $numeroMorfoespecies = $this->cantidadDeclarada($texto, ['morfoespecies', 'morfoespecie']);
        $numeroLotes = $this->cantidadConEvidencia($seccionMuestras !== '' ? $seccionMuestras : $texto, ['lotes', 'lote']);
        if ($gruposBiologicos !== []) {
            $evidenciasCampos['grupos_biologicos'] = [
                'fragmento' => $gruposBiologicos[0]['evidencia'],
                'patron' => 'catalogo-biologico-controlado-v1',
                'version_reglas' => self::VERSION_REGLAS,
            ];
            $confianzasCampos['grupos_biologicos'] = min(array_column($gruposBiologicos, 'confianza'));
        }
        foreach ([
            'numero_individuos' => $numeroIndividuos,
            'numero_morfoespecies' => $numeroMorfoespecies,
            'numero_lotes' => $numeroLotes,
        ] as $campo => $cantidad) {
            if (($cantidad['valor'] ?? null) !== null && ($cantidad['evidencia'] ?? null) !== null) {
                $evidenciasCampos[$campo] = [
                    'fragmento' => $cantidad['evidencia'],
                    'patron' => 'cantidad-declarada-o-suma-v1',
                    'version_reglas' => self::VERSION_REGLAS,
                ];
                $confianzasCampos[$campo] = $cantidad['confianza'];
            }
        }

        $errores = [];
        $advertencias = [];
        if ($tipo === self::DESCONOCIDO) {
            $errores[] = 'El contenido no permite identificar inequívocamente una autorización de recolección ni una guía de movilización.';
        }
        if ($tipo !== self::DESCONOCIDO && $numeroDocumentoHallazgo['valor'] === null) {
            $advertencias[] = 'No se pudo identificar el número oficial del documento con evidencia suficiente; requiere revisión humana.';
        }
        if ($tipo !== self::DESCONOCIDO && $autorizacion['valor'] === null) {
            $advertencias[] = 'No se pudo identificar el número de la autorización de recolección relacionada con evidencia suficiente; requiere revisión humana.';
        }
        if (($oficio['ambiguo'] ?? false) || ($autorizacion['ambiguo'] ?? false) || ($guia['ambiguo'] ?? false)) {
            $advertencias[] = 'Se detectaron varios candidatos para un código oficial; requiere revisión humana y no se autocompletará ese campo.';
        }
        if (preg_match($this->patronAutoridad(), $texto) !== 1) {
            $advertencias[] = 'No se reconoció inequívocamente a la autoridad ambiental nacional como entidad emisora.';
        }
        if ($tipo === self::GUIA_MOVILIZACION && (($inicioMovilizacion['valor'] ?? null) === null || ($finMovilizacion['valor'] ?? null) === null)) {
            $advertencias[] = 'No fue posible reconstruir todo el período autorizado de movilización.';
        }
        if ($tipo === self::AUTORIZACION_RECOLECCION && (($rangoAutorizacion['desde']['valor'] ?? null) === null || ($rangoAutorizacion['hasta']['valor'] ?? null) === null)) {
            $advertencias[] = 'No fue posible reconstruir toda la vigencia de la autorización.';
        }
        if (($validoDesde['valor'] ?? null) !== null && ($validoHasta['valor'] ?? null) !== null && $validoDesde['valor'] > $validoHasta['valor']) {
            $errores[] = 'La fecha inicial es posterior a la fecha límite de validez.';
        }
        if (($ruc['valor'] ?? null) !== null && ! $this->rucPlausible((string) $ruc['valor'])) {
            $advertencias[] = 'El RUC extraído no cumple la estructura ecuatoriana esperada; no debe autocompletarse.';
            unset($evidenciasCampos['ruc'], $confianzasCampos['ruc']);
            $ruc['valor'] = null;
        }
        $estado = $errores !== [] ? 'rechazado' : 'revision';
        $autocompletado = $estado !== 'rechazado';

        return [
            'version_reglas' => self::VERSION_REGLAS,
            'tipo_detectado' => $tipo,
            'confianza' => round($confianza, 4),
            'margen_clasificacion' => round($margen, 4),
            'estructura_esencial' => $estructuraEsencial,
            'requiere_confirmacion_humana' => true,
            'decision_automatica' => $estado === 'rechazado' ? 'RECHAZADO' : 'REVISION_HUMANA',
            'autocompletado_habilitado' => $autocompletado,
            'puntajes' => $detallesPuntaje,
            'numero_documento' => $numeroDocumentoHallazgo['valor'],
            'numero_autorizacion' => $autorizacion['valor'],
            'titular' => $titular['valor'],
            'organizacion' => $organizacion['valor'],
            'ruc' => $ruc['valor'],
            'proyecto' => $proyecto['valor'],
            'emitido_en' => $emitidoEn['valor'],
            'valido_desde' => $validoDesde['valor'],
            'valido_hasta' => $validoHasta['valor'],
            'origen' => $origen['valor'],
            'destino' => $destino['valor'],
            'codigos_muestra' => array_column($codigosMuestra, 'valor'),
            'evidencias_codigos_muestra' => $codigosMuestra,
            'grupos_biologicos' => array_column($gruposBiologicos, 'valor'),
            'evidencias_grupos_biologicos' => $gruposBiologicos,
            'numero_individuos' => $numeroIndividuos['valor'],
            'numero_morfoespecies' => $numeroMorfoespecies['valor'],
            'numero_lotes' => $numeroLotes['valor'],
            'evidencias_cantidades' => array_filter([
                'numero_individuos' => $numeroIndividuos['evidencia'] ?? null,
                'numero_morfoespecies' => $numeroMorfoespecies['evidencia'] ?? null,
                'numero_lotes' => $numeroLotes['evidencia'] ?? null,
            ]),
            'evidencias_campos' => $evidenciasCampos,
            'confianzas_campos' => $confianzasCampos,
            'candidatos_ambiguos' => array_filter([
                'numero_documento' => $numeroDocumentoHallazgo['ambiguo'] ? $numeroDocumentoHallazgo['candidatos'] : null,
                'numero_autorizacion' => $autorizacion['ambiguo'] ? $autorizacion['candidatos'] : null,
            ]),
            'texto_sha256' => hash('sha256', $texto),
            'firma_declarada' => preg_match('/(?:documento\s+)?firmado\s+electr[oó0]nicamente/iu', $texto) === 1,
            'errores' => array_values(array_unique($errores)),
            'advertencias' => array_values(array_unique($advertencias)),
            'estado' => $estado,
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $documentos Clave = tipo esperado.
     * @return array{estado: string, decision_automatica: string, autocompletado_habilitado: bool, errores: list<string>, advertencias: list<string>}
     */
    public function validarExpediente(array $documentos, ?array $tiposRequeridos = null): array
    {
        $errores = [];
        $advertencias = [];

        if ($documentos !== []) {
            foreach ($tiposRequeridos ?? [self::AUTORIZACION_RECOLECCION, self::GUIA_MOVILIZACION] as $requerido) {
                if (! isset($documentos[$requerido])) {
                    $errores[] = 'Falta la '.$this->etiqueta($requerido).' requerida para contrastar el expediente.';
                }
            }
        }

        foreach ($documentos as $esperado => $documento) {
            $detectado = $documento['tipo_detectado'] ?? self::DESCONOCIDO;
            if ($detectado !== $esperado) {
                $errores[] = sprintf(
                    'El archivo cargado como %s fue identificado por su contenido como %s.',
                    $this->etiqueta($esperado),
                    $this->etiqueta((string) $detectado),
                );
            }
            foreach (($documento['errores'] ?? []) as $error) {
                $errores[] = $this->etiqueta($esperado).': '.$error;
            }
            foreach (($documento['advertencias'] ?? []) as $advertencia) {
                $advertencias[] = $this->etiqueta($esperado).': '.$advertencia;
            }
        }

        $autorizacion = $documentos[self::AUTORIZACION_RECOLECCION] ?? null;
        $guia = $documentos[self::GUIA_MOVILIZACION] ?? null;
        if (is_array($autorizacion) && is_array($guia)) {
            $codigoAutorizacion = $this->normalizarCodigo($autorizacion['numero_autorizacion'] ?? null);
            $codigoGuia = $this->normalizarCodigo($guia['numero_autorizacion'] ?? null);
            if ($codigoAutorizacion === null || $codigoGuia === null) {
                $advertencias[] = 'No fue posible contrastar el vínculo entre la guía y la autorización; requiere revisión humana.';
            } elseif ($codigoAutorizacion !== $codigoGuia) {
                $errores[] = sprintf(
                    'La guía cita la autorización %s, pero el oficio aportado concede la %s.',
                    (string) $guia['numero_autorizacion'],
                    (string) $autorizacion['numero_autorizacion'],
                );
            }

            $rucAutorizacion = preg_replace('/\D/', '', (string) ($autorizacion['ruc'] ?? ''));
            $rucGuia = preg_replace('/\D/', '', (string) ($guia['ruc'] ?? ''));
            if ($rucAutorizacion !== '' && $rucGuia !== '' && $rucAutorizacion !== $rucGuia) {
                $errores[] = 'El RUC titular no coincide entre la autorización y la guía de movilización.';
            }

            foreach ([
                ['organizacion', 0.42, 'La organización titular no coincide entre la autorización y la guía de movilización.'],
                ['proyecto', 0.28, 'El proyecto descrito en la guía no coincide con el proyecto autorizado.'],
            ] as [$campo, $umbral, $mensaje]) {
                $valorAutorizacion = (string) ($autorizacion[$campo] ?? '');
                $valorGuia = (string) ($guia[$campo] ?? '');
                if ($valorAutorizacion !== '' && $valorGuia !== '' && ! $this->textosRelacionados($valorAutorizacion, $valorGuia, $umbral)) {
                    $errores[] = $mensaje;
                }
            }

            $emisionAutorizacion = $autorizacion['emitido_en'] ?? null;
            $inicioAutorizacion = $autorizacion['valido_desde'] ?? null;
            $finAutorizacion = $autorizacion['valido_hasta'] ?? null;
            $movilizacion = $guia['valido_desde'] ?? null;
            $finMovilizacion = $guia['valido_hasta'] ?? null;
            if (is_string($emisionAutorizacion) && is_string($movilizacion) && $movilizacion < $emisionAutorizacion) {
                $errores[] = 'La movilización consta con una fecha anterior a la emisión de la autorización.';
            }
            if (is_string($inicioAutorizacion) && is_string($movilizacion) && $movilizacion < $inicioAutorizacion) {
                $errores[] = 'La movilización inicia antes del período autorizado para la recolección.';
            }
            if (is_string($finAutorizacion) && is_string($movilizacion) && $movilizacion > $finAutorizacion) {
                $errores[] = 'La movilización inicia después del vencimiento de la autorización de recolección.';
            }
            if (is_string($finAutorizacion) && is_string($finMovilizacion) && $finMovilizacion > $finAutorizacion) {
                $errores[] = 'La guía de movilización vence después de la autorización de recolección relacionada.';
            }
        }

        $errores = array_values(array_unique($errores));
        $advertencias = array_values(array_unique($advertencias));
        $estado = $errores !== [] ? 'rechazado' : 'revision';

        return [
            'estado' => $estado,
            'decision_automatica' => $estado === 'rechazado' ? 'RECHAZADO' : 'REVISION_HUMANA',
            'autocompletado_habilitado' => $estado !== 'rechazado',
            'errores' => $errores,
            'advertencias' => $advertencias,
        ];
    }

    public function tipoEsperadoParaNombre(string $nombre): ?string
    {
        return match ($nombre) {
            'Copia de la autorización de recolección (MAE)' => self::AUTORIZACION_RECOLECCION,
            'Copia del permiso de movilización' => self::GUIA_MOVILIZACION,
            default => null,
        };
    }

    public function campoTieneEvidenciaSuficiente(array $analisis, string $campo): bool
    {
        if (($analisis['autocompletado_habilitado'] ?? false) !== true) {
            return false;
        }

        return isset($analisis['evidencias_campos'][$campo])
            && (float) ($analisis['confianzas_campos'][$campo] ?? 0.0) >= self::UMBRAL_CAMPO_PROPUESTO;
    }

    /** @param list<array{0: string, 1: string, 2: float, 3: string}> $reglas */
    private function puntaje(string $texto, array $reglas): array
    {
        $puntaje = 0.0;
        $senales = [];
        $categorias = [];
        $evidencias = [];
        foreach ($reglas as [$id, $patron, $peso, $categoria]) {
            if (preg_match($patron, $texto, $coincidencia, PREG_OFFSET_CAPTURE) === 1) {
                $puntaje += $peso;
                $senales[] = $id;
                $categorias[] = $categoria;
                $evidencias[] = [
                    'senal' => $id,
                    'categoria' => $categoria,
                    'peso' => $peso,
                    'fragmento' => $this->fragmentoEnOffset($texto, (int) $coincidencia[0][1]),
                ];
            }
        }

        return [
            'puntaje' => min(1.0, $puntaje),
            'señales' => $senales,
            'categorias' => array_values(array_unique($categorias)),
            'evidencias' => $evidencias,
            'version_reglas' => self::VERSION_REGLAS,
        ];
    }

    private function estructuraEsencial(string $tipo, array $detalle): bool
    {
        $categorias = $detalle['categorias'] ?? [];
        if (! in_array('titulo', $categorias, true)) {
            return false;
        }

        return match ($tipo) {
            self::AUTORIZACION_RECOLECCION => count(array_intersect($categorias, ['acto', 'vigencia', 'alcance', 'obligaciones'])) >= 1,
            self::GUIA_MOVILIZACION => count(array_intersect($categorias, ['vigencia', 'ruta', 'transporte', 'muestras'])) >= 2,
            default => false,
        };
    }

    /** @param list<array{0: string, 1: string, 2: float}> $reglas */
    private function resolverCodigo(string $texto, array $reglas): array
    {
        $hallazgos = [];
        foreach ($reglas as [$id, $patron, $confianza]) {
            if (preg_match_all($patron, $texto, $coincidencias, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) < 1) {
                continue;
            }
            foreach ($coincidencias as $coincidencia) {
                $crudo = (string) ($coincidencia[1][0] ?? '');
                $valor = $this->normalizarCodigoPresentacion($crudo);
                if ($valor === null || ! $this->codigoPlausible($valor)) {
                    continue;
                }
                $clave = $this->normalizarCodigo($valor);
                if ($clave === null) {
                    continue;
                }
                $hallazgos[$clave] ??= [
                    'valor' => $valor,
                    'evidencia' => $this->fragmentoEnOffset($texto, (int) ($coincidencia[0][1] ?? 0)),
                    'confianza' => $confianza,
                    'patron' => $id,
                ];
            }
        }

        if ($hallazgos === []) {
            return $this->hallazgoVacio();
        }
        $confianzaMaxima = max(array_column($hallazgos, 'confianza'));
        $hallazgos = array_filter(
            $hallazgos,
            static fn (array $hallazgo): bool => (float) $hallazgo['confianza'] >= ($confianzaMaxima - 0.03),
        );
        if (count($hallazgos) > 1) {
            return [
                ...$this->hallazgoVacio(),
                'ambiguo' => true,
                'candidatos' => array_values(array_column($hallazgos, 'valor')),
            ];
        }

        $hallazgo = array_values($hallazgos)[0];

        return [...$hallazgo, 'ambiguo' => false, 'candidatos' => [$hallazgo['valor']]];
    }

    /** @param list<array{0: string, 1: string, 2: float}> $reglas */
    private function resolverTexto(string $texto, array $reglas, bool $permitirNumerico = false): array
    {
        foreach ($reglas as [$id, $patron, $confianza]) {
            if (preg_match($patron, $texto, $coincidencia, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }
            $crudo = (string) ($coincidencia[1][0] ?? '');
            $valor = trim(preg_replace('/\s+/u', ' ', $crudo) ?? $crudo, " \t\n\r\0\x0B.,;:");
            if ($valor === '' || (! $permitirNumerico && mb_strlen($valor) < 3)) {
                continue;
            }

            return [
                'valor' => $valor,
                'evidencia' => $this->fragmentoEnOffset($texto, (int) ($coincidencia[0][1] ?? 0)),
                'confianza' => $confianza,
                'patron' => $id,
                'ambiguo' => false,
                'candidatos' => [$valor],
            ];
        }

        return $this->hallazgoVacio();
    }

    /** @param list<array{0: string, 1: string, 2: float}> $reglas */
    private function resolverFecha(string $texto, array $reglas): array
    {
        foreach ($reglas as [$id, $patron, $confianza]) {
            if (preg_match($patron, $texto, $coincidencia, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }
            $fecha = $this->normalizarFecha((string) ($coincidencia[1][0] ?? ''));
            if ($fecha === null) {
                continue;
            }

            return [
                'valor' => $fecha,
                'evidencia' => $this->fragmentoEnOffset($texto, (int) ($coincidencia[0][1] ?? 0)),
                'confianza' => $confianza,
                'patron' => $id,
                'ambiguo' => false,
                'candidatos' => [$fecha],
            ];
        }

        return $this->hallazgoVacio();
    }

    /** @param list<array{0: string, 1: string, 2: float}> $reglas */
    private function rangoFechas(string $texto, array $reglas): array
    {
        foreach ($reglas as [$id, $patron, $confianza]) {
            if (preg_match($patron, $texto, $coincidencia, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }
            $desde = $this->normalizarFecha((string) ($coincidencia[1][0] ?? ''));
            $hasta = $this->normalizarFecha((string) ($coincidencia[2][0] ?? ''));
            if ($desde === null || $hasta === null) {
                continue;
            }
            $evidencia = $this->fragmentoEnOffset($texto, (int) ($coincidencia[0][1] ?? 0));

            return [
                'desde' => ['valor' => $desde, 'evidencia' => $evidencia, 'confianza' => $confianza, 'patron' => $id, 'ambiguo' => false, 'candidatos' => [$desde]],
                'hasta' => ['valor' => $hasta, 'evidencia' => $evidencia, 'confianza' => $confianza, 'patron' => $id, 'ambiguo' => false, 'candidatos' => [$hasta]],
            ];
        }

        return ['desde' => $this->hallazgoVacio(), 'hasta' => $this->hallazgoVacio()];
    }

    private function patronAutoridad(): string
    {
        return '/(?:ministerio\s+(?:del\s+|de\s+)?ambiente(?:\s*,?\s*agua(?:\s+y\s+transici[oó0]n\s+ecol[oó0]gica)?|\s+y\s+energ[ií1]a)?|\bMAATE\b|\bMAAE\b)/iu';
    }

    private function patronFecha(): string
    {
        return '(?:[0-3]?\d\s+(?:de\s+)?[\p{L}]{3,12}\s+(?:(?:de|del)\s+)?(?:19|20)\d{2}|[0-3]?\d[.\/-][01]?\d[.\/-](?:19|20)\d{2}|(?:19|20)\d{2}[.\/-][01]?\d[.\/-][0-3]?\d)';
    }

    private function normalizarFecha(string $fecha): ?string
    {
        $fecha = trim(preg_replace('/\s+/u', ' ', $fecha) ?? $fecha);
        if (preg_match('/^(\d{4})[.\/-](\d{1,2})[.\/-](\d{1,2})$/', $fecha, $partes) === 1) {
            return $this->fechaValida((int) $partes[1], (int) $partes[2], (int) $partes[3]);
        }
        if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/', $fecha, $partes) === 1) {
            return $this->fechaValida((int) $partes[3], (int) $partes[2], (int) $partes[1]);
        }
        if (preg_match('/(\d{1,2})\s+(?:de\s+)?([\p{L}]+)\s+(?:(?:de|del)\s+)?(\d{4})/iu', $fecha, $partes) !== 1) {
            return null;
        }
        $meses = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
        ];
        $mes = $meses[mb_strtolower($partes[2])] ?? null;

        return $mes === null ? null : $this->fechaValida((int) $partes[3], $mes, (int) $partes[1]);
    }

    private function fechaValida(int $anio, int $mes, int $dia): ?string
    {
        return checkdate($mes, $dia, $anio) ? sprintf('%04d-%02d-%02d', $anio, $mes, $dia) : null;
    }

    private function seccionMuestras(string $texto): string
    {
        if (preg_match('/(?:(?:datos|detalle)\s+de\s+(?:las\s+)?(?:muestras|espec[ií1]menes)|c[oó0]digo\s+campo\s+nombre\s+com[uú]n|material\s+biol[oó0]gico\s+a\s+movilizar)\s*([\s\S]{1,20000})/iu', $texto, $coincidencia) !== 1) {
            return '';
        }
        $partes = preg_split(
            '/\r?\n\s*(?:observaciones|documento\s+firmado|firma(?:\s+electr[oó0]nica)?|responsable\s+de\s+la\s+emisi[oó0]n)\b/iu',
            (string) $coincidencia[1],
            2,
        );

        return trim((string) ($partes[0] ?? ''));
    }

    /** @return list<array{valor: string, evidencia: string, confianza: float}> */
    private function codigosMuestra(string $seccion): array
    {
        if ($seccion === '' || preg_match_all('/\b([A-Z][A-Z0-9]{1,9}(?:-[A-Z0-9]{1,12}){1,4}|[A-Z]{1,5}\d{1,6})\b/u', $seccion, $coincidencias, PREG_OFFSET_CAPTURE) < 1) {
            return [];
        }
        $excluidos = ['ABC-1234', 'RUC-000'];
        $resultados = [];
        foreach ($coincidencias[1] as [$codigo, $offset]) {
            $codigo = mb_strtoupper((string) $codigo);
            $prefijo = mb_strtolower(mb_strcut($seccion, max(0, (int) $offset - 40), 40, 'UTF-8'));
            if (str_contains($prefijo, 'placa') || in_array($codigo, $excluidos, true)) {
                continue;
            }
            $resultados[$codigo] = [
                'valor' => $codigo,
                'evidencia' => $this->fragmentoEnOffset($seccion, (int) $offset),
                'confianza' => 0.88,
            ];
        }

        return array_values($resultados);
    }

    /** @return list<array{valor: string, evidencia: string, confianza: float}> */
    private function gruposBiologicos(string $texto): array
    {
        $catalogo = [
            'Macroinvertebrados acuáticos', 'Macroinvertebrados', 'Entomofauna', 'Ictiofauna',
            'Herpetofauna', 'Mastofauna', 'Avifauna', 'Coleópteros', 'Ephemeroptera',
            'Coleoptera', 'Trichoptera', 'Odonata', 'Anfibios', 'Arácnidos', 'Crustáceos',
            'Moluscos', 'Anélidos', 'Insectos', 'Flora', 'Fauna',
        ];
        $hallados = [];
        foreach ($catalogo as $grupo) {
            if (preg_match('/\b'.preg_quote($grupo, '/').'\b/iu', $texto, $coincidencia, PREG_OFFSET_CAPTURE) === 1) {
                $hallados[mb_strtolower($grupo)] = [
                    'valor' => $grupo,
                    'evidencia' => $this->fragmentoEnOffset($texto, (int) $coincidencia[0][1]),
                    'confianza' => 0.84,
                ];
            }
        }

        return array_values($hallados);
    }

    /** @param list<string> $etiquetas */
    private function cantidadDeclarada(string $texto, array $etiquetas): array
    {
        foreach ($etiquetas as $etiqueta) {
            $patron = '/(?:n(?:ro|o|[úu]mero)?\.?|n[º°]|total)\s+(?:de\s+)?'.preg_quote($etiqueta, '/').'\s*[:#-]?\s*(\d{1,7})\b/iu';
            if (preg_match($patron, $texto, $m, PREG_OFFSET_CAPTURE) === 1) {
                return [
                    'valor' => (int) $m[1][0],
                    'evidencia' => $this->fragmentoEnOffset($texto, (int) $m[0][1]),
                    'confianza' => 0.94,
                ];
            }
        }

        return ['valor' => null, 'evidencia' => null, 'confianza' => 0.0];
    }

    /** @param list<string> $etiquetas */
    private function cantidadConEvidencia(string $texto, array $etiquetas): array
    {
        $declarada = $this->cantidadDeclarada($texto, $etiquetas);
        if ($declarada['valor'] !== null) {
            return $declarada;
        }
        $singular = preg_quote($etiquetas[1] ?? $etiquetas[0], '/');
        if (preg_match_all('/\b(\d{1,7})\s+'. $singular .'s?\b/iu', $texto, $m, PREG_OFFSET_CAPTURE) < 1) {
            return ['valor' => null, 'evidencia' => null, 'confianza' => 0.0];
        }
        $total = array_sum(array_map(static fn (array $item): int => (int) $item[0], $m[1]));

        return [
            'valor' => $total,
            'evidencia' => $this->fragmentoEnOffset($texto, (int) $m[0][0][1]),
            'confianza' => 0.82,
        ];
    }

    private function fragmentoEnOffset(string $texto, int $offset): string
    {
        $inicio = max(0, $offset - 90);
        $fragmento = mb_strcut($texto, $inicio, 300, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $fragmento) ?? $fragmento);
    }

    private function normalizarCodigoPresentacion(?string $codigo): ?string
    {
        if ($codigo === null) {
            return null;
        }
        $codigo = mb_strtoupper($codigo);
        $codigo = preg_replace('/\s*([\/-])\s*/u', '$1', $codigo) ?? $codigo;
        $codigo = preg_replace('/(?<=\d)\s+(?=\d)/u', '', $codigo) ?? $codigo;
        $codigo = trim(preg_replace('/\s+/u', ' ', $codigo) ?? $codigo, " \t\n\r\0\x0B.,;:");

        return $codigo !== '' ? $codigo : null;
    }

    private function normalizarOcr(string $texto): string
    {
        $texto = str_replace(["\0", "\u{00A0}", '–', '—', '−', '‐', '‑', '／'], ['', ' ', '-', '-', '-', '-', '-', '/'], $texto);
        $texto = preg_replace('/(?<=[A-Z0-9\/])-\s*\R\s*(?=[A-Z0-9])/u', '-', $texto) ?? $texto;
        $texto = preg_replace('/(?<=[A-Z0-9])\/\s*\R\s*(?=[A-Z0-9])/u', '/', $texto) ?? $texto;
        $texto = preg_replace_callback(
            '/\b(AUTORIZACI[OÓ0]N|RECOLECCI[OÓ0]N|MOVILIZACI[OÓ0]N|EMISI[OÓ0]N|ECOL[OÓ0]GICA|BIOL[OÓ0]GICA|ESPEC[IÍ1]MENES)\b/iu',
            static fn (array $m): string => strtr(mb_strtoupper($m[1]), ['0' => 'O', '1' => 'I']),
            $texto,
        ) ?? $texto;
        $texto = preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\R{3,}/u', "\n\n", $texto) ?? $texto;

        return trim($texto);
    }

    private function codigoPlausible(string $codigo): bool
    {
        $compacto = $this->normalizarCodigo($codigo);
        if ($compacto === null || strlen($compacto) < 2 || strlen($compacto) > 100 || preg_match('/\d/', $compacto) !== 1) {
            return false;
        }

        return ctype_digit($compacto) || substr_count($codigo, '-') + substr_count($codigo, '/') >= 2;
    }

    private function rucPlausible(string $ruc): bool
    {
        return preg_match('/^\d{13}$/', $ruc) === 1 && str_ends_with($ruc, '001');
    }

    private function normalizarCodigo(mixed $codigo): ?string
    {
        if (! is_string($codigo) || trim($codigo) === '') {
            return null;
        }

        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($codigo)) ?: null;
    }

    private function textosRelacionados(string $a, string $b, float $umbral): bool
    {
        $tokensA = $this->tokens($a);
        $tokensB = $this->tokens($b);
        if ($tokensA === [] || $tokensB === []) {
            return false;
        }
        $interseccion = array_intersect($tokensA, $tokensB);
        $union = array_unique([...$tokensA, ...$tokensB]);

        return count($interseccion) / count($union) >= $umbral;
    }

    /** @return list<string> */
    private function tokens(string $texto): array
    {
        $texto = mb_strtolower($texto);
        $texto = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $texto) ?? $texto;
        $stop = ['de', 'del', 'la', 'el', 'para', 'por', 'y', 'en', 'los', 'las', 'proyecto', 'estudio', 'cia', 'ltda', 'sas'];

        return array_values(array_unique(array_filter(
            preg_split('/\s+/u', trim($texto)) ?: [],
            fn (string $token): bool => mb_strlen($token) >= 3 && ! in_array($token, $stop, true),
        )));
    }

    private function hallazgoVacio(): array
    {
        return ['valor' => null, 'evidencia' => null, 'confianza' => 0.0, 'patron' => null, 'ambiguo' => false, 'candidatos' => []];
    }

    private function etiqueta(string $tipo): string
    {
        return match ($tipo) {
            self::AUTORIZACION_RECOLECCION => 'autorización de recolección',
            self::GUIA_MOVILIZACION => 'guía de movilización',
            default => 'documento no reconocido',
        };
    }
}
