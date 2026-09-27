<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
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
    public function responder(string $pregunta, ConsultarChatBotHandler $catalogo, ?int $nodoAnterior = null, array $variantesRecientes = [], array $contextoCatalogo = []): array
    {
        $normal = preg_replace('/^[\s\x{00bf}?]+/u', '', Str::lower(Str::ascii(trim($pregunta)))) ?? '';
        $opciones = $this->opcionesBase();

        if (($social = $this->conversacion->responder($pregunta)) !== null) {
            return $social;
        }
        $consultaCientifica = (bool) preg_match('/\b(tienen|cuantos?|busca|buscar|existe|registros|especies|familias|generos|ejemplares|especimenes|catalogo)\b/', $normal)
            || ($contextoCatalogo !== [] && (bool) preg_match('/^(?:y\s+de\s+|y\s+)?cuantos?|^y\s+de\s+|^(?:perdon|corrijo|quise decir|queria decir|no\s+)|^donde\s+los\s+encontraron/i', $normal));
        if ($consultaCientifica && ($publica = $this->consultaCatalogo->responder($pregunta, $contextoCatalogo)) !== null) {
            return $publica;
        }
        if (($compuesta = $this->conocimiento->responderCompuesta($pregunta)) !== null) {
            return $compuesta;
        }
        $conocida = $this->conocimiento->responder($pregunta, $nodoAnterior, $variantesRecientes);
        if ($conocida !== null) {
            return $conocida;
        }

        if (preg_match('/^(menu|ayuda|que puedo hacer|que necesitas)/', $normal)) {
            return $this->menuPrincipal();
        }
        if (preg_match('/^(buscar un especimen|buscar especimen)$/', $normal)) {
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

        if (preg_match('/prestam|solicitante|pedir especimen/', $normal)) {
            return [
                'texto' => 'Para solicitar especimenes en prestamo, entra con tu cuenta y activa el rol Solicitante desde Configuracion. Luego abre Mis solicitudes y registra el material que necesitas.',
                'opciones' => [
                    ['label' => 'Iniciar sesion', 'url' => route('login')],
                    ['label' => 'Explorar catalogo', 'url' => route('portal.catalogo')],
                ],
            ];
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

        $preguntaBiologica = (bool) preg_match('/^(que (son|es|hacen|funcion)|para que sirven|por que|como viven|cual es la funcion)/', $normal)
            && (bool) preg_match('/(artr[oó]pod|insect|invertebr|hormig|maripos|abej|avisp|escarabaj|crustace|molusc|nudibranquio|aracnid|aran)/', $normal)
            && ! preg_match('/(catalog|colecci|registr|deposit|prestam|localidad|provincia|cuant|nombre cientifico)/', $normal);
        if ($preguntaBiologica && ($respuestaBiologica = $this->biologiaLocal($normal)) !== null) {
            return $respuestaBiologica;
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

        if (! preg_match('/\b(artr[oó]pod|insect|invertebr|hormig|maripos|ara[nñ]|escarabaj|crust[aá]ce|molusc|biodivers|ecolog|taxonom|animal|especie|abej|avisp|cole[oó]pter|lepid[oó]pter)\w*/iu', $pregunta)
            && ! preg_match('/\b[A-Z][a-z]{2,}\s+[a-z]{3,}\b/u', $pregunta)) {
            return $this->menuPrincipal($pregunta);
        }

        $biologiaLocal = $this->biologiaLocal($normal);
        if ($biologiaLocal !== null) {
            return $biologiaLocal;
        }

        if (! config('chatbot.use_external_biology', false)) {
            return ['texto' => 'Puedo responder sobre grupos comunes de invertebrados y consultar los registros publicados. Indica el grupo o un nombre científico para precisar la respuesta.', 'opciones' => [
                ['label' => 'Artrópodos', 'pregunta' => '¿Qué son los artrópodos?'],
                ['label' => 'Insectos', 'pregunta' => '¿Qué son los insectos?'],
                ['label' => 'Buscar espécimen', 'pregunta' => 'Buscar un espécimen'],
            ]];
        }

        $libre = $this->consultarWikipedia($pregunta);
        if ($libre !== null) {
            $respuesta = $this->modeloLocal->resumir($pregunta, $libre['extracto']) ?? $libre['extracto'];

            return ['texto' => $respuesta.' Fuente externa: '.$libre['url'], 'opciones' => $opciones];
        }

        return $this->menuPrincipal($pregunta);
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
                'Las hormigas son insectos sociales. Su función depende de la especie: algunas depredan otros invertebrados, otras dispersan semillas y las cortadoras cultivan hongos.',
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

    /** @return array{extracto:string,url:string}|null */
    private function consultarWikipedia(string $pregunta): ?array
    {
        $tema = preg_replace('/^[\\s\\x{00bf}\\?]*(?:qu[e\\x{00e9}]|cu[a\\x{00e1}]l(?:es)?|c[o\\x{00f3}]mo|d[o\\x{00f3}]nde)\\s+(?:son|es|se|viven|hacen)?\\s*(?:los|las|el|la|un|una)?\\s*/iu', '', trim($pregunta));
        $tema = trim((string) $tema, " \\t\\n\\r\\0\\x0B?\\x{00bf}");

        $clave = 'chatbot:fuente:'.hash('sha256', Str::lower($tema));
        $enCache = Cache::get($clave);
        if (is_array($enCache) && isset($enCache['extracto'], $enCache['url'])) {
            return $enCache;
        }

        try {
            $resultado = Http::withHeaders([
                'User-Agent' => 'HubDigital/1.0 (consulta educativa; contacto: hubdigital.epn@kintiflow.com)',
            ])->timeout(4)->get('https://es.wikipedia.org/w/api.php', [
                'action' => 'query',
                'generator' => 'search',
                'gsrsearch' => Str::limit($tema !== '' ? $tema : trim($pregunta), 160, ''),
                'gsrlimit' => 5,
                'prop' => 'extracts|info',
                'exintro' => 1,
                'explaintext' => 1,
                'exchars' => 800,
                'inprop' => 'url',
                'format' => 'json',
                'formatversion' => 2,
            ]);

            if (! $resultado->successful()) {
                return null;
            }

            $paginas = (array) $resultado->json('query.pages', []);
            $raiz = mb_substr(Str::lower(Str::ascii($tema)), 0, 5);
            $pagina = collect($paginas)->first(static fn (mixed $item): bool => is_array($item)
                && $raiz !== '' && str_starts_with(Str::lower(Str::ascii((string) ($item['title'] ?? ''))), $raiz))
                ?? ($paginas[0] ?? []);
            $resumen = trim((string) ($pagina['extract'] ?? ''));
            $enlace = (string) ($pagina['fullurl'] ?? '');
            if ($resumen === '' || ! str_starts_with($enlace, 'https://es.wikipedia.org/')) {
                return null;
            }

            $fuente = ['extracto' => Str::limit($resumen, 760), 'url' => $enlace];
            Cache::put($clave, $fuente, now()->addDay());

            return $fuente;
        } catch (\Throwable) {
            return null;
        }
    }
}
