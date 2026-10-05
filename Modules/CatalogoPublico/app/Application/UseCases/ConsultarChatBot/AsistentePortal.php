<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Illuminate\Support\Str;
use Modules\CatalogoPublico\Domain\ValueObjects\ChatBotMensajes;

final class AsistentePortal
{
    public function __construct(
        private readonly ModeloLocalPequeno $modeloLocal,
        private readonly ConocimientoPortal $conocimiento,
        private readonly ConsultaCatalogoPublico $consultaCatalogo,
        private readonly ConversacionBasica $conversacion,
    ) {}

    /** @return array{texto:string, opciones:array, node_id?:int, variant_id?:int|null} */
    public function responder(string $pregunta, ConsultarChatBotHandler $catalogo, ?int $nodoAnterior = null, array $variantesRecientes = [], array $contextoCatalogo = [], ?array $seleccionPortal = null): array
    {
        $normal = preg_replace('/^[\s\x{00bf}?]+/u', '', Str::lower(Str::ascii(trim($pregunta)))) ?? '';
        $opciones = $this->opcionesBase();

        // Resolver el acceso público antes de interpretar menciones negadas
        // de trámites o reutilizar las entidades de una consulta anterior.
        if (preg_match('/\b(?:catalogo|coleccion)\s+public[oa]\b/', $normal)
            && preg_match('/cuenta|registrar(?:me)?|registro|iniciar sesion|acceso|consultar/', $normal)
            && ! preg_match('/\bcuant[oa]s?\b|\bbusca\b|\b(?:descarg\w*|export\w*)\b|paso a paso|pasos/', $normal)) {
            return ['texto' => 'No necesitas crear una cuenta ni iniciar sesión para consultar el catálogo público, aplicar filtros o descargar sus resultados. La cuenta se utiliza para los trámites y las funciones que requieren un rol autorizado.',
                'fuente' => 'portal', 'intent' => 'portal.acceso_publico',
                'opciones' => [['label' => 'Abrir catálogo público', 'url' => route('portal.catalogo')]]];
        }

        $parametrosAyuda = $seleccionPortal === null
            ? $this->consultaCatalogo->parametros($contextoCatalogo)
            : EnlaceSeleccionCatalogo::limpiar($seleccionPortal);
        if (($compuesta = app(SolicitudCompuestaPortal::class)->responder($pregunta, $contextoCatalogo, $seleccionPortal)) !== null) return $compuesta;
        if (($ayudaLocal = app(AyudaContextualPortal::class)->responder($pregunta, $parametrosAyuda)) !== null) return $ayudaLocal;
        // La pertenencia científica no expresa cesión o donación del material.
        if (! preg_match('/\b(?:cuant[oa]s?|cantidad|numero|total|buscar|busca|muestrame)\b/', $normal)
            && (preg_match('/\b(?:a que familia|familia de|clasificacion de)\b/', $normal)
                || (preg_match('/\bpertenece(?:n)?\b/', $normal) && preg_match('/\bfamilia\b|\bgenero\b|\borden\b|\bfilo\b|\bclase\b|\btaxon\b|\ba [a-z]+idae\b/', $normal)))) {
            return $this->consultaCatalogo->clasificacionPublica($pregunta)
                ?? ['texto' => 'No tengo un linaje público suficiente para resolver esa clasificación. Indica el nombre científico y consulta la autoridad de la ficha; no puedo decidir una identificación ni una familia sin evidencia.',
                    'fuente' => 'portal', 'intent' => 'catalogo.clasificacion_sin_evidencia', 'opciones' => []];
        }
        $preguntaBiologica = (bool) preg_match('/^(que (?:son|es|hacen|funcion)|para que sirven|por que|como viven|cual es la funcion)/', $normal)
            && (bool) preg_match('/artropod|insect|invertebr|hormig|maripos|abej|avisp|escarabaj|crustace|molusc|nudibranquio|aracnid|aran/', $normal)
            && ! preg_match('/catalog|colecci|registr|deposit|prestam|localidad|provincia|cuant|nombre cientifico/', $normal);
        if ($preguntaBiologica && ($respuestaBiologica = $this->biologiaLocal($normal)) !== null) return $respuestaBiologica;
        if (preg_match('/\b(?:quien escribio|autor de|autoria de)\b/', $normal)) return app(FuentesPublicasChat::class)->responder($pregunta);
        if (preg_match('/^(?:y\s+)?cuant[oa]s?\s+hay[?\s]*$/', $normal)) {
            return ['texto' => '¿Qué deseas contar: registros, especies, géneros o familias? Indica también si te refieres a la selección aplicada o a la consulta anterior.',
                'fuente' => 'aclaracion', 'intent' => 'catalogo.aclaracion', 'opciones' => $opciones];
        }
        if (preg_match('/^que (?:es|son)\b/', $normal)
            && ($clasificacion = $this->consultaCatalogo->clasificacionPublica($pregunta)) !== null) return $clasificacion;

        if (($social = $this->conversacion->responder($pregunta)) !== null) {
            return $social;
        }
        if (preg_match('/^(menu|ayuda|que puedo hacer|que necesitas)/', $normal)) {
            return $this->menuPrincipal();
        }
        if (preg_match('/\bcsv\b|(?:descarg|export).*resultad/', $normal)) {
            return ['texto' => 'Aplica los filtros en el catálogo, cambia a Registros y pulsa Descargar resultados CSV. La descarga conserva toda la selección filtrada, incluidas las filas de otras páginas. No necesitas una cuenta.',
                'fuente' => 'portal', 'intent' => 'portal.csv', 'entidades' => $contextoCatalogo,
                'opciones' => [['label' => 'Abrir registros filtrados', 'url' => $this->enlaceSeleccion($contextoCatalogo, $seleccionPortal, 'registros')]]];
        }
        if (preg_match('/\bregistros\b.*\bespecies\b.*\bdistintas\b|\bdiferencia\b.*\bregistros\b|\b(?:lo mismo|iguales)\b.*\b(?:registros|especies)\b/', $normal)) {
            return ['texto' => 'No son lo mismo. Un registro corresponde a una entrada del catálogo; varios registros pueden pertenecer a la misma especie. La riqueza cuenta cada entidad a rango especie de la jerarquía interna publicada una vez. Esa cifra no certifica nombres aceptados por una autoridad externa ni identificaciones físicas: se conservan calificadores y determinaciones abiertas. Taxón es cualquier nivel taxonómico con un nombre científico, como Arthropoda o Formicidae. El tamaño de los puntos del mapa expresa cantidad de registros, no abundancia natural.',
                'fuente' => 'portal', 'intent' => 'portal.conteos', 'opciones' => $opciones];
        }
        if (preg_match('/\bmapa\b/', $normal) && preg_match('/no aparecen|no (?:veo|se ven|se muestran)\s+(?:los\s+)?puntos|sin puntos|faltan puntos/', $normal)) {
            return $this->ayudaPuntosMapa($contextoCatalogo, $seleccionPortal);
        }
        // Atiende ambas partes sin enviar la instrucción de uso al detector de entidades.
        if (preg_match('/^(?<conteo>.+?)(?:\s+(?:y|adem[aá]s)\s+|;\s*)(?<ayuda>¿?c[oó]mo\b.+\bmapa\b.*)$/iu', trim($pregunta), $partes)
            && preg_match('/\b(?:cu[aá]nt[oa]s?|cantidad|n[uú]mero|total)\b/iu', $partes['conteo'])) {
            $publica = $this->consultaCatalogo->responder(trim($partes['conteo']), $contextoCatalogo, $seleccionPortal);
            if ($publica !== null) {
                $publica['texto'] .= "\n\n".$this->instruccionesMapa();
                if (($publica['fuente'] ?? '') === 'catalogo') {
                    $referenciaSeleccion = preg_match('/\b(?:(?:esta|esa|mi)\s+seleccion|seleccion\s+(?:actual|aplicada))\b/', Str::lower(Str::ascii($partes['conteo'])));
                    $url = $referenciaSeleccion
                        ? $this->enlaceSeleccion($contextoCatalogo, $seleccionPortal, 'mapa')
                        : route('portal.catalogo', array_replace($this->consultaCatalogo->parametros($publica['entidades'] ?? []), ['vista' => 'mapa']));
                    $publica['opciones'][] = ['label' => 'Abrir mapa de esta consulta', 'url' => $url];
                }
                return $publica;
            }
        }
        if (preg_match('/^buscar (?:un )?especimen(?:es)?[?.]*$/', $normal)) {
            return ['texto' => '¿Qué dato tienes para buscar en los registros publicados?', 'opciones' => [
                ['label' => 'Código de catálogo', 'pregunta' => 'Tengo el código'],
                ['label' => 'Género o especie', 'pregunta' => 'Sé el nombre científico'],
                ['label' => 'Localidad', 'pregunta' => 'Buscar por localidad'],
            ]];
        }
        if (preg_match('/^(tengo el codigo|se el nombre cientifico)/', $normal)) {
            return ['texto' => 'Escribe el código o el nombre científico en tu próxima pregunta. Consultaré solo registros publicados y pediré que confirmes cualquier nombre parecido.', 'opciones' => [
                ['label' => 'Abrir catálogo', 'url' => route('portal.catalogo')],
            ]];
        }
        if (preg_match('/^buscar por localidad/', $normal)) {
            return ['texto' => 'Abre el catálogo y usa el filtro de localidad para consultar los especímenes publicados.', 'opciones' => [
                ['label' => 'Abrir catálogo', 'url' => route('portal.catalogo')],
            ]];
        }
        if (preg_match('/^(consultar la coleccion|consultar coleccion)/', $normal)) {
            return ['texto' => '¿Qué deseas conocer de la colección publicada?', 'opciones' => [
                ['label' => 'Cantidad de especies', 'pregunta' => '¿Cuántas especies hay en la colección?'],
                ['label' => 'Familias con más registros', 'pregunta' => '¿Qué familias tienen más registros?'],
                ['label' => 'Localidades de un taxón', 'pregunta' => 'Sé el nombre científico'],
            ]];
        }
        if (preg_match('/^(usar el portal|aprender a usar el portal)/', $normal)) {
            return ['texto' => 'Elige la tarea que quieres completar.', 'opciones' => [
                ['label' => 'Buscar y filtrar', 'pregunta' => 'Buscar un espécimen'],
                ['label' => 'Mi cuenta y roles', 'pregunta' => '¿Cómo configuro mi cuenta?'],
                ['label' => 'Trámites', 'pregunta' => 'Depósitos y préstamos'],
            ]];
        }
        if (preg_match('/^(depositos y prestamos|tramites)/', $normal)) {
            return ['texto' => '¿Qué trámite necesitas?', 'opciones' => [
                ['label' => 'Depositar o donar', 'pregunta' => '¿Cómo hago un depósito?'],
                ['label' => 'Solicitar préstamo', 'pregunta' => '¿Cómo solicito un préstamo?'],
            ]];
        }

        if (preg_match('/^(?:cuanto es|calcula|cuanto da)\s+-?\d|^-?\d+\s*[+*\/-]/', $normal)) {
            return app(FuentesPublicasChat::class)->responder($pregunta);
        }

        if (preg_match('/guia de moviliz|permiso de moviliz/', $normal)) {
            return ['texto' => 'En el trámite de depósito, revisa la sección Documentos. Allí puedes adjuntar la autorización de recolección y la guía de movilización cuando correspondan; el formulario indica los requisitos de tu caso. La guía es un documento del traslado del material.',
                'fuente' => 'portal', 'intent' => 'documentos_permisos',
                'opciones' => [['label' => 'Consultar depósitos', 'url' => route('depositos.portal')]]];
        }
        if (preg_match('/paso a paso|(?:indica|dame).*pasos|como (?:busco|buscar|vusco|filtro)|donde puedo consultar.*(?:ejemplares|coleccion)/', $normal)) {
            return $this->ayudaFiltros($pregunta, $contextoCatalogo, $seleccionPortal);
        }

        if (preg_match('/^(catalogo|coleccion|filtrar catalogo|usar catalogo)$/', $normal)) {
            return [
                'texto' => 'En el catálogo puedes buscar por código, nombre científico y localidad. Escríbeme el dato que tienes o abre los filtros.',
                'opciones' => [
                    ['label' => 'Abrir catálogo', 'url' => route('portal.catalogo')],
                    ['label' => 'Consultar la colección', 'pregunta' => 'Consultar la colección'],
                ],
            ];
        }

        $operacionContexto = ($contextoCatalogo !== [] && (bool) preg_match('/^(?:y|solo|dame solo|quita|quitar|elimina)\s+/', $normal))
            || (bool) preg_match('/^(?:y\s+)?(?:quita(?:r)?|elimina(?:r)?|sin)\s+(?:el\s+)?filtro\b/', $normal);
        if (! $operacionContexto && preg_match('/filtro|filtrar|leyenda|mapa|dashboard|indice|indicador|exportar|geojson/i', $normal)
            && ! preg_match('/cuant|registros de|especies de/', $normal)) {
            return $this->ayudaFiltros($pregunta, $contextoCatalogo, $seleccionPortal);
        }
        $consultaCientifica = (bool) preg_match('/\b(tienen|cuant[oa]s?|busca|buscar|existe|registros|especies|familias|generos|ejemplares|especimenes|catalogo)\b/', $normal)
            || (bool) preg_match('/^[\p{L}][\p{L}\d_.:-]*(?:\s+[\p{L}][\p{L}.-]*)?$/u', trim($pregunta))
            || (bool) preg_match('/\b[A-Za-z]+-[A-Za-z0-9-]*\d\b/', $pregunta)
            || (bool) preg_match('/^(?:perdon|corrijo|quise decir|queria decir|no\s+).*\b(?:quiero|sino|pero|decir)\b/', $normal)
            || $operacionContexto
            || ($contextoCatalogo !== [] && (bool) preg_match('/^(?:y\s+de\s+|y\s+)?cuantos?|^y\s+|^(?:perdon|corrijo|quise decir|queria decir|no\s+)|^donde\s+los\s+encontraron/i', $normal));
        $consultaCientifica = $consultaCientifica || (bool) preg_match('/\bdonde\b.*\b(?:recolect|colect|encontr)/', $normal);
        $consultaCientifica = $consultaCientifica || (bool) preg_match('/\b(?:hormigas?|mariposas|escarabajos)\b/', $normal);
        $consultaCientifica = $consultaCientifica || app(OperadorProvinciaChat::class)->solicitado($pregunta);
        if ($consultaCientifica && ($publica = $this->consultaCatalogo->responder($pregunta, $contextoCatalogo, $seleccionPortal)) !== null) {
            return $publica;
        }
        if (($compuesta = $this->conocimiento->responderCompuesta($pregunta)) !== null) {
            return $compuesta;
        }
        $conocida = $this->conocimiento->responder($pregunta, $nodoAnterior, $variantesRecientes);
        if ($conocida !== null) {
            return $conocida;
        }

        if (! $preguntaBiologica && preg_match('/catalog|colecci|buscar|busca|tienen|cuant|existe|especimen|registro|taxon|familia|genero|especie|invertebr|distribuci|provincia|[A-Z]{2,8}-\d+/i', Str::ascii($pregunta))) {
            try {
                $salida = $catalogo->handle(new ConsultarChatBotInput($pregunta));
                if ($salida->dentroDeDominio) {
                    if ($salida->respuesta === ChatBotMensajes::SIN_RESULTADOS) {
                        return ['texto' => 'No encontré registros públicos que coincidan con esa búsqueda. Esto no confirma la inexistencia del taxón ni de ejemplares no divulgados.',
                            'opciones' => $opciones, 'fuente' => 'catalogo', 'intent' => 'catalogo.none',
                            'confianza' => 'HIGH', 'confianza_valor' => 1.0];
                    }
                    return ['texto' => $salida->respuesta, 'opciones' => $opciones];
                }
            } catch (\Throwable $error) {
                report($error);
            }
        }

        if (preg_match('/portal|catalogo|coleccion|ejemplares|coordenadas|catalgo|espesimenes/', $normal)) return $this->ayudaFiltros($pregunta, $contextoCatalogo, $seleccionPortal);
        return app(FuentesPublicasChat::class)->responder($pregunta);
    }

    private function enlaceSeleccion(array $contextoCatalogo, ?array $seleccionPortal, string $vista): string
    {
        $parametros = $seleccionPortal === null
            ? $this->consultaCatalogo->parametros($contextoCatalogo)
            : EnlaceSeleccionCatalogo::limpiar($seleccionPortal);

        return route('portal.catalogo', array_replace($parametros, ['vista' => $vista]));
    }

    private function instruccionesMapa(): string
    {
        return 'Cómo usar el mapa: abre Mapa y análisis. Al alejar, los clústeres reúnen varias ubicaciones y muestran su número; púlsalos para acercar. Cada punto original reúne registros con el mismo par de coordenadas. Su tamaño expresa registros y sus colores, filos. Abre un punto para consultar el árbol taxonómico y seis ejemplares por página. Las coordenadas aproximadas conservan sus advertencias; los conteos no equivalen a abundancia natural.';
    }

    private function ayudaPuntosMapa(array $contextoCatalogo, ?array $seleccionPortal): array
    {
        $entrada = $seleccionPortal ?? ($contextoCatalogo !== [] ? $this->consultaCatalogo->parametros($contextoCatalogo) : null);
        $entidades = $seleccionPortal === null ? $contextoCatalogo : [];
        $texto = 'El mapa solo representa registros con latitud y longitud públicas y válidas. ';
        $datos = null;
        if ($entrada === null) {
            $texto .= 'No tengo una selección aplicada ni una consulta pública anterior. Abre el catálogo y comprueba los filtros y el número de registros con coordenadas. Si ese número es mayor que cero y siguen sin verse, comparte la URL y cualquier mensaje de error para revisar la carga del mapa.';
        } else {
            $datos = $this->consultaCatalogo->diagnosticoMapa($entrada);
            if ($datos === null) {
                return ['texto' => 'La selección recibida contiene criterios inválidos. Revisa los filtros del catálogo; no he calculado un diagnóstico parcial.',
                    'fuente' => 'aclaracion', 'intent' => 'catalogo.aclaracion', 'entidades' => $entidades,
                    'opciones' => [['label' => 'Abrir catálogo', 'url' => route('portal.catalogo')]]];
            }
            $poblacion = $seleccionPortal === null ? 'la consulta pública anterior' : 'la selección aplicada de la página';
            if ($datos['total'] === 0) {
                $texto .= 'No hay registros publicados en '.$poblacion.'. Por eso no hay puntos que mostrar; revisa los filtros, incluida la selección espacial si la aplicaste.';
            } elseif ($datos['con_coordenadas'] === 0) {
                $texto .= 'Hay '.$datos['total'].' '.($datos['total'] === 1 ? 'registro publicado' : 'registros publicados').' en '.$poblacion.', pero ninguno tiene ambas coordenadas públicas y válidas. Por eso no aparecen puntos. Puedes consultar los registros sin cambiar la selección.';
            } else {
                $texto .= 'Hay '.$datos['total'].' '.($datos['total'] === 1 ? 'registro publicado' : 'registros publicados').' en '.$poblacion.' y '.$datos['con_coordenadas'].' con coordenadas públicas y válidas. Si los puntos siguen sin verse, vuelve a abrir el mapa y comparte la URL y cualquier mensaje de error para revisar su carga.';
            }
            $texto .= ' La selección aplicada se conserva en el enlace al mapa.';
        }

        return ['texto' => $texto, 'fuente' => 'portal', 'intent' => 'portal.mapa_ayuda', 'entidades' => $entidades,
            'datos' => $datos,
            'opciones' => [['label' => 'Abrir mapa de la selección', 'url' => $this->enlaceSeleccion($contextoCatalogo, $seleccionPortal, 'mapa')]]];
    }

    private function ayudaFiltros(string $pregunta, array $contextoCatalogo, ?array $seleccionPortal): array
    {
        if (preg_match('/\b(?:fuera de|excepto|excluye|excluir|no son de)\b/', Str::lower(Str::ascii($pregunta)))) {
            return ['texto' => 'Usa Excluir provincia en Filtros de investigación para retirar una provincia del conjunto; no la marques como Provincia incluida. No he preparado filtros parciales. También puedes preguntar por registros de un taxón fuera de una única provincia.',
                'fuente' => 'aclaracion', 'intent' => 'catalogo.aclaracion', 'opciones' => []];
        }
        $entidades = app(DetectorEntidadesChat::class)->extraer($pregunta);
        $criterios = [];
        if (isset($entidades['error_consulta'])) {
            return ['texto' => $entidades['error_consulta'].' No he preparado una consulta parcial.', 'fuente' => 'aclaracion',
                'intent' => 'catalogo.aclaracion', 'opciones' => [['label' => 'Abrir catálogo', 'url' => route('portal.catalogo')]]];
        }
        foreach (['codigo' => 'N.º de catálogo', 'taxon' => 'Taxón', 'provincia' => 'Provincia', 'localidad' => 'Localidad', 'pais' => 'País', 'desde' => 'Desde', 'hasta' => 'Hasta', 'mes' => 'Mes de colecta', 'ubicacion' => 'Solo coordenadas públicas', 'identificacion' => 'Identificación', 'elev_desde' => 'Elevación desde (m)', 'elev_hasta' => 'Elevación hasta (m)'] as $clave => $etiqueta) {
            if (isset($entidades[$clave])) $criterios[] = $etiqueta.' = '.($clave === 'ubicacion' ? 'sí' : $entidades[$clave]);
        }
        $normal = Str::lower(Str::ascii($pregunta));
        // Una referencia a la selección actual conserva la página aplicada. Los
        // criterios escritos para una consulta nueva no heredan esa selección.
        $usarSeleccionActual = $entidades === [] && ($seleccionPortal !== null || $contextoCatalogo !== []);
        $haySeleccionActual = $usarSeleccionActual && ($seleccionPortal !== null || $contextoCatalogo !== []);
        $texto = "1. Abre Colección Biológica y Filtros de investigación. No necesitas cuenta.\n2. ".($haySeleccionActual
            ? 'La selección aplicada se conserva al abrir su mapa. Cambia los filtros solo si deseas preparar otra consulta.'
            : ($criterios === [] ? 'Elige los criterios de tu consulta.' : 'Configura: '.implode('; ', $criterios).'.'));
        if (isset($entidades['localidad_preferida'])) $texto .= ' Localidad = '.$entidades['localidad_preferida'].' es opcional según tu preferencia; agrégala si quieres restringir los resultados a ese sitio.';
        $texto .= $haySeleccionActual
            ? "\n3. Abre el enlace al mapa de esta selección."
            : "\n3. Pulsa Aplicar filtros. Si un intervalo es inválido, corrígelo: se conserva la selección anterior.";
        $texto .= "\n4. Usa los botones Tarjetas, Registros y Mapa y análisis: todos conservan la misma selección.\n5. En Registros puedes descargar el CSV; en los tres puntos de cada panel, Indicador explica el cálculo. Limpiar restablece toda la selección.";
        if (preg_match('/\bmapa\b/', $normal)) $texto .= "\n\n".$this->instruccionesMapa();
        return ['texto' => $texto, 'fuente' => 'portal', 'intent' => 'portal.filtros',
            'entidades' => $usarSeleccionActual && $seleccionPortal === null ? $contextoCatalogo : $entidades,
            'opciones' => [['label' => 'Abrir consulta en el mapa', 'url' => $usarSeleccionActual
                ? $this->enlaceSeleccion($contextoCatalogo, $seleccionPortal, 'mapa')
                : route('portal.catalogo', array_replace($this->consultaCatalogo->parametros($entidades), ['vista' => 'mapa']))]]];
    }

    /** @return array{texto:string,opciones:array} */
    private function menuPrincipal(?string $pregunta = null): array
    {
        $inDomain = $pregunta !== null && (bool) preg_match(
            '/depos|dona|document|papel|requis|entreg|solicitud|curadur|laboratorio|muestra|catalog|registro|cuenta|contact|especimen|animal|taxon/i',
            Str::ascii($pregunta));
        return [
            'texto' => $pregunta !== null && ! $inDomain
                ? 'Puedo ayudarte con el Laboratorio, los depósitos y el catálogo público. ¿Cuál de esos temas necesitas?'
                : 'Puedo orientarte sobre depósitos, donaciones, requisitos, el estado de una solicitud o el catálogo público. ¿Sobre cuál necesitas información?',
            'fuente' => 'unknown', 'intent' => $pregunta === null ? 'menu'
                : ($inDomain ? 'UNKNOWN_IN_DOMAIN' : 'UNKNOWN_OUT_OF_DOMAIN'),
            'confianza' => 'UNKNOWN', 'confianza_valor' => 0.0,
            'opciones' => [
                ['label' => 'Buscar espécimen', 'pregunta' => 'Buscar un espécimen'],
                ['label' => 'Consultar colección', 'pregunta' => 'Consultar la colección'],
                ['label' => 'Usar el portal', 'pregunta' => 'Usar el portal'],
                ['label' => 'Depósitos y préstamos', 'pregunta' => 'Depósitos y préstamos'],
            ],
        ];
    }

    /** @return array{texto:string,opciones:array}|null */
    private function biologiaLocal(string $normal): ?array
    {
        $temas = [
            'artropod' => [
                'Los artrópodos son invertebrados con exoesqueleto, cuerpo segmentado y apéndices articulados. Incluyen insectos, arácnidos y crustáceos.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/the-cambrian-period.html',
            ],
            'insect' => [
                'Los insectos son artrópodos. En su etapa adulta tienen seis patas y el cuerpo dividido en cabeza, tórax y abdomen.',
                'Smithsonian', 'https://naturalhistory.si.edu/education/teaching-resources/life-science/what-insect',
            ],
            'invertebr' => [
                'Los invertebrados son animales sin columna vertebral. Incluyen, entre otros, artrópodos y moluscos; no todos son insectos.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/molluscs.html',
            ],
            'molusc' => [
                'Los moluscos son invertebrados; el grupo incluye caracoles, almejas y pulpos.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/molluscs.html',
            ],
            'aracnid' => [
                'Los arácnidos son artrópodos distintos de los insectos. Entre ellos están las arañas, los escorpiones y las garrapatas.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/the-cambrian-period.html',
            ],
            'hormig' => [
                'Las hormigas son insectos sociales. En los bosques, muchas depredan otros invertebrados y forman parte de las redes alimentarias del suelo. Su función depende de la especie y del ambiente; esta explicación general no demuestra abundancia ni comportamiento de los ejemplares de la colección.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/life-in-soil.html',
            ],
            'maripos' => [
                'Las mariposas son insectos del orden Lepidoptera. Muchas visitan flores y pueden transportar polen, aunque su aporte cambia según la especie y el hábitat.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/insect-pollination.html',
            ],
            'abej' => [
                'Muchas abejas transportan polen al visitar flores. La polinización favorece la reproducción de numerosas plantas; no todas las especies de abejas viven en colonias.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/insect-pollination.html',
            ],
            'escarabaj' => [
                'Los escarabajos son insectos del orden Coleoptera. Algunas especies visitan flores y transportan polen; sus funciones ecológicas varían mucho entre grupos.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/insect-pollination.html',
            ],
            'crustace' => [
                'Los crustáceos son artrópodos; entre ellos hay cangrejos, camarones y langostas. Sus formas y hábitats son diversos.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/the-cambrian-period.html',
            ],
            'nudibranquio' => [
                'Los nudibranquios son moluscos marinos del grupo de los gasterópodos. No son insectos ni crustáceos.',
                'Natural History Museum', 'https://www.nhm.ac.uk/discover/molluscs.html',
            ],

        ];
        if (str_contains($normal, 'aran')) {
            $normal .= ' aracnid';
        }
        foreach ($temas as $palabra => [$texto, $fuente, $url]) {
            if (str_contains($normal, $palabra)) {
                return ['texto' => $texto, 'opciones' => [
                    ['label' => 'Fuente: '.$fuente, 'url' => $url],
                    ['label' => 'Explorar catálogo', 'url' => route('portal.catalogo')],
                ]];
            }
        }

        return null;
    }

    /** @return list<array{label:string,url:string}> */
    public function opcionesBase(): array
    {
        return [
            ['label' => 'Catalogo de especimenes', 'url' => route('portal.catalogo')],
            ['label' => 'Depositos', 'url' => route('depositos.portal')],
            ['label' => 'Acceder', 'url' => route('login')],
        ];
    }

}
