<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

use Illuminate\Support\Str;

/** Ayuda local versionada. Resuelve acción y objeto antes de extraer filtros. */
final class AyudaContextualPortal
{
    public const VERSION_GLOSARIO = '2026-10-05';

    public function __construct(private readonly DetectorEntidadesChat $detector, private readonly ConsultaCatalogoPublico $consulta) {}

    public function responder(string $pregunta, array $parametros): ?array
    {
        $normal = Str::lower(Str::ascii($pregunta));
        $normal = preg_replace('/[^a-z0-9\s]/', ' ', $normal) ?? '';
        $normal = trim(preg_replace('/\s+/', ' ', $normal) ?? '');
        $enlace = static fn (string $vista): string => route('portal.catalogo', array_replace($parametros, ['vista' => $vista]));
        $salida = static fn (string $texto, string $intencion, array $opciones = []): array => [
            'texto' => $texto, 'fuente' => 'portal', 'intent' => $intencion, 'opciones' => $opciones,
        ];

        if (preg_match('/\b(?:prestamos?|prestan|prestame|pedir (?:material|especimenes)|solicitante)\b/', $normal)
            && ! preg_match('/\b(?:disposicion|condicion de tipo|significa|glosario)\b/', $normal)) {
            return $salida("1. Consulta con curaduría del Laboratorio la disponibilidad del material y las condiciones del préstamo. La consulta del catálogo no constituye una solicitud ni una aprobación.\n2. Inicia sesión con una cuenta verificada. La solicitud de préstamo requiere el rol autorizado Prestamista; si no tienes acceso, consulta al Laboratorio.\n3. Abre Mis solicitudes de préstamo y Nueva solicitud; describe el material y completa los datos y documentos que pida el formulario.\n4. Consulta el seguimiento y espera la revisión e instrucciones de curaduría antes de retirar material. No puedo asegurar disponibilidad, aprobación, requisitos específicos ni plazos para tu caso.", 'portal.prestamo_pasos', [
                ['label' => 'Información y contacto del Laboratorio', 'url' => url('/').'#contacto'],
                ['label' => 'Iniciar sesión', 'url' => route('login')],
                ['label' => 'Solicitudes de préstamo (requiere acceso)', 'url' => route('prestamos.investigador.mis-solicitudes')],
            ]);
        }
        if (preg_match('/\bcf\b/', $normal) && preg_match('/significa|calificador|nombre cientifico/', $normal)) {
            return $salida('cf. (confer, «comparar con») es un calificador de identificación: expresa que el material se compara con ese taxón, con incertidumbre sobre su determinación. No equivale a una identificación confirmada ni forma parte del binomio. El portal conserva el texto fuente; no elimina el calificador para convertirlo en un nombre aceptado. Darwin Core permite expresar esta duda en identificationQualifier.', 'portal.calificador', [
                ['label' => 'Fuente: Darwin Core · identificationQualifier', 'url' => 'https://dwc.tdwg.org/terms/#dwc:identificationQualifier'],
            ]);
        }
        if (preg_match('/\b(?:quien escribio|autor de|autoria de)\b/', $normal) && str_contains($normal, 'origen de las especies')) {
            return $salida('Charles Darwin escribió El origen de las especies, publicado por primera vez en 1859. Es una referencia de historia de la ciencia; no es una consulta de ejemplares de la colección.', 'general.autoria', [
                ['label' => 'Fuente: Darwin Online · primera edición (1859)', 'url' => 'https://darwin-online.org.uk/content/contentblock?basepage=1&hitpage=4&itemID=PC-Virginia-Francis-F373&viewtype=text'],
            ]);
        }

        if (preg_match('/\bnoticias?\b|\bactualidad\b|\bultima hora\b/', $normal)) {
            return $salida('No puedo verificar noticias ni acontecimientos de hoy en tiempo real. Consulta una fuente de actualidad con fecha de publicación; una referencia histórica no responde a esa pregunta.', 'general.sin_actualidad');
        }
        if (preg_match('/(?:que puedo hacer|que puedes hacer|que haces|en que (?:me )?puedes ayudar|como (?:me )?ayudas)/', $normal)) {
            return $salida('¡Hola! Puedo consultar registros públicos, contar registros, especies, géneros y familias de la selección aplicada, explicar filtros y coordenadas, orientar descargas CSV/XLSX y los trámites de depósito o préstamo. La consulta pública no requiere cuenta; los trámites sí requieren autenticación y un rol autorizado.', 'portal.capacidades', [
                ['label' => 'Abrir selección actual', 'url' => $enlace('registros')],
                ['label' => 'Depósitos', 'url' => route('depositos.portal')],
            ]);
        }
        if ((preg_match('/\b(?:tipo nomenclatural|condicion de tipo|disposicion|incertidumbre|precision|localidad inec|estado de ocurrencia)\b/', $normal)
            && preg_match('/que (?:es|significa)|son (?:lo mismo|exactas)|es lo mismo|diferencia|explica|exactitud|exactas/', $normal)
            ) || preg_match('/coordenadas?.*(?:exactas?|exactitud|recuperadas?)|(?:exactas?|exactitud).*coordenadas?|coordenada recuperada|ubicacion exacta/', $normal)) {
            return $salida('Condición de tipo y disposición del material son campos distintos. Condición de tipo (typeStatus) indica el papel nomenclatural del ejemplar, como holotipo o paratipo. Disposición (disposition) indica la situación del material, por ejemplo en la colección o en préstamo. Estado de ocurrencia (occurrenceStatus) expresa detección o no detección durante la colecta. El XLSX traduce present a detected y absent a notDetected; conserva el estado original en occurrenceStatusVerbatim. Un valor como destroyed o loaned no demuestra detección ni ausencia y deja occurrenceStatus vacío; disposición y notas se conservan por separado. Una celda vacía significa no informado, no «sin tipo». La localidad original conserva el texto de colecta; la localidad INEC es una referencia administrativa separada y no demuestra el sitio exacto. Precisión e incertidumbre no se deducen del número de decimales: una coordenada recuperada del Excel o aproximada conserva sus advertencias, y la incertidumbre desconocida permanece vacía, nunca cero. Glosario '.$this::VERSION_GLOSARIO.'.', 'portal.glosario', [
                ['label' => 'Diccionario CSV y XLSX', 'url' => route('portal.diccionario-exportacion')],
                ['label' => 'Ver registros de la selección', 'url' => $enlace('registros')],
            ]);
        }
        if (preg_match('/\b(?:depositar|deposito|depositos|donar|donacion)\b/', $normal)
            && preg_match('/pasos|paso a paso|instrucciones|como|necesito|quiero|requisitos|documentos/', $normal)
            && ! preg_match('/estado de|seguimiento|mi solicitud|correccion|rechaz|firma (?:invalida|rechazada)/', $normal)) {
            return $salida("1. Abre Depósitos y elige la modalidad de depósito o donación que corresponda; no todas las solicitudes son depósitos temporales.\n2. Inicia sesión o crea una cuenta con propósito Depositante.\n3. Completa los datos de depósito material MEPN y el detalle biológico en los formularios guiados. Prepara la solicitud generada y firmada, evidencia de procedencia lícita, cesión o justificación institucional, y autorización de recolección y guía de movilización cuando correspondan a tu caso. El formulario determina los documentos exigibles.\n4. Revisa y envía la solicitud; consulta el seguimiento. Curaduría revisa la admisión de la modalidad solicitada. La recepción física no implica aceptación automática ni transferencia de propiedad. No envíes ni traslades material hasta recibir instrucciones del equipo curatorial.", 'portal.deposito_pasos', [
                ['label' => 'Abrir Depósitos', 'url' => route('depositos.portal')],
                ['label' => 'Crear cuenta como Depositante', 'url' => route('register', ['rol' => 'DEPOSITANTE'])],
                ['label' => 'Iniciar sesión', 'url' => route('login')],
            ]);
        }
        // Una consulta de varios pasos se resuelve completa, antes de tratar Excel como formato.
        if (preg_match('/pasos|paso a paso/', $normal) && preg_match('/\b(?:fuera de|excepto|excluye|excluir|no son de)\b/', $normal)) {
            return $salida('La exclusión de provincias ya no está disponible. Selecciona Provincia para incluir sus registros. No he preparado filtros parciales para este procedimiento compuesto.', 'catalogo.aclaracion');
        }
        if (preg_match('/pasos|paso a paso/', $normal) && preg_match('/buscar|consultar|filtrar/', $normal) && preg_match('/descarg|export/', $normal)) {
            $consultaTexto = preg_split('/[.;]|\b(?:despues|después|expl[ií]came|no quiero)\b/iu', $pregunta)[0];
            $entidades = $this->detector->extraer($consultaTexto);
            if (isset($entidades['error_consulta'])) return $salida($entidades['error_consulta'].' No he preparado filtros parciales.', 'catalogo.aclaracion');
            $criterios = [];
            foreach (['taxon' => 'Taxón', 'provincia' => 'Provincia', 'localidad' => 'Localidad', 'mes' => 'Mes de colecta', 'desde' => 'Desde', 'hasta' => 'Hasta'] as $clave => $etiqueta) {
                if (isset($entidades[$clave])) $criterios[] = $etiqueta.': '.$entidades[$clave];
            }
            return $salida("1. Abre Colección Biológica y Filtros de investigación; Limpiar Filtros inicia una consulta global.\n2. Configura ".($criterios === [] ? 'los criterios que necesitas' : implode('; ', $criterios)).". Los filtros se aplican al cambiar cada dato.\n3. En Mapa y análisis, las barras de Cobertura por década y Mes de colecta agregan criterios; el resumen de filtros activos permite retirarlos individualmente.\n4. Alterna Mapa y análisis y Registros para comparar la misma selección.\n5. En Registros pulsa Descargar resultados CSV; incluye todas las páginas. Para XLSX entra en las tarjetas de una especie y pulsa Descargar datos XLSX. Revisa Precisión, Referencia INEC y las advertencias geográficas de la ficha; XLSX conserva georeferenceRemarks. La consulta y las descargas públicas no requieren cuenta.", 'portal.consulta_pasos', [
                ['label' => 'Abrir consulta propuesta', 'url' => route('portal.catalogo', array_replace($this->consulta->parametros($entidades), ['vista' => 'mapa']))],
                ['label' => 'Diccionario de descargas', 'url' => route('portal.diccionario-exportacion')],
            ]);
        }
        if (preg_match('/como.*filtr|pasos.*filtr/', $normal) && preg_match('/provincia/', $normal) && preg_match('/metodo/', $normal)) {
            return $salida('Abre Filtros de investigación y elige Provincia. Expande Ejemplar y colecta y marca el Método de recolección que necesitas. Los filtros se aplican al cambiar cada dato y ambos criterios se combinan. El resumen muestra los criterios aplicados y permite retirar cada uno; cambiar de vista conserva la selección.', 'portal.filtros', [
                ['label' => 'Abrir selección aplicada', 'url' => $enlace('registros')],
            ]);
        }
        if (preg_match('/\b(?:descarg\w*|export\w*|xlsx|csv)\b/', $normal) || preg_match('/\bexcel\b/', $normal) && preg_match('/datos|registros|quiero/', $normal)) {
            $xlsx = preg_match('/\b(?:xlsx|excel)\b/', $normal);
            $tarjetas = $xlsx ? $this->consulta->parametrosTarjetasEspecie($parametros) : $parametros;
            return $salida($xlsx
                ? 'Para descargar XLSX, cambia a Tarjetas de la especie seleccionada y pulsa Descargar datos XLSX. Se exportan todos sus registros filtrados, incluidas otras páginas y las advertencias de coordenadas. Si la selección abarca varias especies o rangos superiores, usa Registros → Descargar resultados CSV, o abre una especie antes de descargar su XLSX. El enlace conserva la selección aplicada; no necesitas cuenta.'
                : 'Cambia a Registros y pulsa Descargar resultados CSV. La descarga incluye toda la selección filtrada, incluidas otras páginas; el enlace conserva los criterios aplicados. No necesitas cuenta. Consulta el diccionario para distinguir los campos de CSV y XLSX.',
                $xlsx ? 'portal.xlsx' : 'portal.csv', [
                    ['label' => $xlsx ? 'Abrir tarjetas de la selección' : 'Abrir registros filtrados', 'url' => $xlsx ? route('portal.catalogo', array_replace($tarjetas, ['vista' => 'tarjetas'])) : $enlace('registros')],
                    ['label' => 'Diccionario CSV y XLSX', 'url' => route('portal.diccionario-exportacion')],
                ]);
        }
        if (preg_match('/(?:quitar|limpiar|restablecer|reiniciar)\s+(?:todos\s+)?(?:los\s+)?filtros/', $normal)
            && ! preg_match('/\b(?:sin|no(?: (?:quiero|deseo|hay que))?)\s+(?:quitar|limpiar|restablecer|reiniciar)\b/', $normal)) {
            return $salida('Pulsa Limpiar Filtros en Filtros de investigación para restablecer la consulta global. Esto retira todos los criterios y la selección espacial. El enlace siguiente abre esa consulta global.', 'portal.limpiar', [
                ['label' => 'Abrir catálogo sin filtros', 'url' => route('portal.catalogo', ['vista' => 'mapa'])],
            ]);
        }
        if (preg_match('/(?:volver|vuelve|abrir|abre|regresar|regresa|cambiar|ver|pasar).*(?:mapa|tabla|registros|tarjetas)/', $normal)
            && preg_match('/\b(?:filtros?|solo|volver|vuelve|regresar|regresa)\b/', $normal)) {
            $vista = str_contains($normal, 'mapa') ? 'mapa' : (str_contains($normal, 'tarjetas') ? 'tarjetas' : 'registros');
            return $salida('Usa los botones Tarjetas, Registros y Mapa y análisis. Cambiar de vista conserva todos los filtros aplicados, incluida la caja espacial; el enlace mantiene esa misma selección. No pulses Limpiar si deseas conservarla.', 'portal.cambiar_vista', [
                ['label' => 'Abrir vista de la selección', 'url' => $enlace($vista)],
            ]);
        }
        if (preg_match('/(?:no (?:salgan|salen|hay)|sin|cero) resultados/', $normal)) {
            return $salida('Comprueba el resumen de filtros activos y retira un criterio por vez: taxón, provincia, periodo, mes, elevación o caja espacial. Un resultado vacío no demuestra ausencia de ejemplares no divulgados. Si usaste un estadio visible como Adulto, también puedes escribir adult. Limpiar abre una consulta global; no lo uses si deseas conservar otros criterios.', 'portal.resultados_vacios', [
                ['label' => 'Revisar selección aplicada', 'url' => $enlace('registros')],
            ]);
        }
        return null;
    }
}
