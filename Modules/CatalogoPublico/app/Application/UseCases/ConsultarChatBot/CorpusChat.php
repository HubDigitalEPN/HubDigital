<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Application\UseCases\ConsultarChatBot;

/** Preguntas de referencia para observar confusiones después de editar conocimiento o pesos. */
final class CorpusChat
{
    /** Desarrollo: se permite ajustar léxico con estas frases. La evaluación final usa TEST una sola vez. */
    private const TRAIN = <<<'DATA'
saludo|hola
saludo|buenos dias
saludo|buenas tardes
saludo|buenas noches
saludo|buenas
saludo|que tal
saludo|hola buenas
saludo|en que me puedes ayudar
conversacion|gracias
conversacion|muchas gracias
conversacion|ok
conversacion|entendido
conversacion|perfecto
conversacion|quien eres
conversacion|adios
conversacion|hasta luego
deposito|quiero hacer un depósito
deposito|quiero depositar especímenes
deposito|tengo muestras para dejar
deposito|quisiera dejar ejemplares
deposito|cómo inicio un depósito temporal
deposito|necesito registrar material en custodia
deposito|puedo dejar bichos en el laboratorio
deposito|no quiero donar, quiero depositar
deposito|quiero depositar no donar
donacion|quiero donar especímenes
donacion|deseo hacer una donación
donacion|regalar muestras al laboratorio
donacion|cómo entrego material como donación
donacion|voy a transferir una colección
donacion|quiero ceder los ejemplares
donacion|no quiero depositar sino donar
donacion|puedo donar los bichos
donacion|qué hago para donar
documentos|qué documentos necesito
documentos|que papeles piden
documentos|q documentos necesito
documentos|documentos para el depósito
documentos_permisos|necesito adjuntar permisos
documentos|qué archivos tengo que cargar
documentos|guía de movilización y autorización
documentos_firma|qué pdf debo firmar
documentos|me piden documentos?
revision|estado de mi solicitud
revision|ya envié la solicitud qué sigue
revision|dónde reviso mi expediente
revision|curaduría ya revisó mis papeles
revision|cómo saber si aprobaron el trámite
revision|quiero ver el avance del depósito
revision|me solicitaron una corrección
revision|cómo va mi solicitud
entrega|cuándo entrego las muestras
entrega|dónde llevo los bichos
entrega|puedo entregar mañana
entrega|en qué lugar hago la entrega física
entrega|ya puedo trasladar especímenes
entrega|no necesito requisitos, quiero saber dónde entrego
entrega|cómo se coordina la recepción
entrega|a quién llevo el lote
acceso_registro|cómo creo una cuenta
acceso|iniciar sesión
acceso|olvidé mi contraseña
acceso|no puedo ingresar al portal
acceso|cómo activo mi usuario
acceso_registro|registrarme como depositante
acceso|acceso al sistema
acceso_registro|necesito registrarme
contacto|correo del laboratorio
contacto|cómo contacto con curaduría
contacto|necesito hablar con alguien
contacto|dónde escribo para pedir ayuda
contacto|tienen correo para consultas
contacto|contactar al equipo
catalogo|¿tienen Dynastes?
catalogo|¿cuántos registros de Megasoma tienen?
catalogo|¿qué familias tienen?
catalogo|¿hay especies de Scarabaeidae?
catalogo|¿tienen Inexistentius?
catalogo|buscar MEPN-CHAT-001
catalogo|¿cuántos ejemplares hay?
catalogo|¿qué géneros hay en Scarabaeidae?
UNKNOWN|cómo está el clima
UNKNOWN|quién ganó el partido
UNKNOWN|hazme una receta
UNKNOWN|cuánto es 2 más 2
UNKNOWN|precio del petróleo hoy
UNKNOWN|cómo reparar una lavadora
UNKNOWN|escribe un poema de amor
UNKNOWN|traduce esto al francés
UNKNOWN|qué película me recomiendas
ambiguous|qué requisitos hay
ambiguous|requisitos para qué
deposito_requisitos|qué requisitos hay|deposito
donacion_requisitos|qué requisitos hay|donacion
DATA;

    private const CALIBRATION = <<<'DATA'
saludo|saludos desde Quito
saludo|muy buenos días estimados
saludo|hola, me ayudan
conversacion|gracias por la información
conversacion|ok perfecto
deposito|deseo dejar unas muestras en custodia
deposito|quiero dejar bichos
deposito|necesito abrir un trámite de depósito
donacion|quisiera regalar material al museo
donacion|será posible donar esta colección
donacion|mi intención es ceder los ejemplares
documentos|q papeles debo subir
documentos|cuáles son los pdf requeridos
documentos|documentos, documentos, ¿cuáles?
revision|ya mandé todo, y luego qué
revision|quiero confirmar el estado de mi expediente
entrega|después de la aprobación dónde entrego
entrega|podría llevar los animales esta semana
acceso|se me olvidó la clave para entrar
acceso_registro|quiero abrir una cuenta nueva
contacto|cuál es la dirección de correo para escribirles
catalogo|¿Tienen Dynastes hercules?
catalogo|¿Qué familias aparecen en el catálogo?
catalogo|buscar EPN-1187
UNKNOWN|qué hora es en Tokio
UNKNOWN|dime el resultado del fútbol
UNKNOWN|enséñame a cocinar arroz
UNKNOWN|recomiéndame un teléfono
ambiguous|qué necesito para eso
donacion_requisitos|y qué requisitos hay|donacion
deposito_requisitos|y qué requisitos hay|deposito
DATA;

    private const TEST = <<<'DATA'
saludo|buen día, ¿están por aquí?
saludo|hola hola
conversacion|muchísimas gracias
conversacion|listo, entendido
deposito|me gustaría dejar mis ejemplares temporalmente
deposito|no donaré, quiero un depósito
deposito|dónde empiezo si voy a depositar
donacion|quiero transferir gratuitamente mis muestras
donacion|no es depósito, es donación
donacion|voy a donar insectos al laboratorio
documentos|qué respaldo en pdf debo presentar
documentos|papeles para adjuntar al expediente
documentos|q documetos me piden
revision|cómo veo si ya revisaron mi solicitud
revision|mandé el expediente, ¿y ahora?
entrega|no quiero saber requisitos, quiero llevar las muestras
entrega|se pueden entregar los ejemplares el lunes
acceso|cómo recupero mi acceso
acceso_registro|primero tengo que crear usuario?
contacto|con quién puedo comunicarme
catalogo|¿Cuántos Dynastes hay de Pichincha?
catalogo|¿Tienen registros de Inexistentius?
catalogo|¿Qué familias tienen ejemplares publicados?
catalogo|buscar MEPN-XYZ-22
UNKNOWN|cuál es la capital de Mongolia
UNKNOWN|haz una lista de compras
UNKNOWN|quiero una receta de pan
UNKNOWN|quién será presidente mañana
ambiguous|qué requisitos necesito
donacion_requisitos|y los requisitos?|donacion
deposito_requisitos|y los requisitos?|deposito
DATA;

    /** Familias nuevas de desarrollo; no contienen ejemplos de TEST ni TEST_FINAL. */
    private const TRAIN_MORE = <<<'DATA'
deposito|el museo puede custodiar una caja de insectos durante un tiempo
deposito|busco iniciar el ingreso temporal de mi colección
deposito|necesito registrar un lote que sigue siendo mío
deposito|quiero dejar el material bajo custodia institucional
deposito|cómo empiezo el trámite si conservo la propiedad
deposito|pueden recibir mis frascos en depósito
deposito|mis ejemplares necesitan resguardo temporal
deposito|se puede abrir una solicitud de custodia
donacion|quiero ceder definitivamente mi colección
donacion|el material será un obsequio para el laboratorio
donacion|no deseo recuperar los ejemplares después
donacion|quisiera transferir la propiedad del lote
donacion|tengo una colección para regalar a la EPN
donacion|cómo formalizo una cesión de insectos
donacion|aceptan material donado por investigadores
donacion|es una entrega definitiva sin retorno
documentos|qué soportes hay que presentar en el formulario
documentos|lista de archivos para la solicitud
documentos|qué anexos debo cargar al expediente
documentos|necesito saber los pdf solicitados
documentos|los respaldos se suben en qué etapa
documentos|q papeles mando con el trámite
documentos|en dónde adjunto los archivos
documentos|qué documentación va en el registro
documentos_permisos|cuándo piden la guía de movilización
documentos_permisos|debo anexar autorización de colecta
documentos_firma|quién comprueba la firma del pdf
documentos_firma|el documento firmado se valida al subirlo
procedencia|cómo justifico el origen legal del lote
procedencia|piden información sobre procedencia
deposito_requisitos|qué condiciones aplican para la custodia temporal
deposito_requisitos|criterios para admitir un depósito
deposito_requisitos|qué se exige antes de solicitar depósito
donacion_requisitos|condiciones de ingreso para una donación
donacion_requisitos|qué debo cumplir para donar material
donacion_requisitos|criterios para aceptar material cedido
revision|curaduría responderá después de que envíe el formulario
revision|qué ocurre luego de remitir el expediente
revision|mi solicitud fue enviada y necesita revisión
revision|cómo se evalúa la documentación remitida
revision|me pidieron subsanar el trámite
revision|quién revisa las condiciones de custodia
revision_estado|en qué fase está mi expediente
revision_estado|consultar el progreso de la solicitud
entrega|en qué momento se coordina la recepción física
entrega|llevo el lote antes o después de la revisión
entrega|a dónde transporto los frascos
entrega|puedo acercarme con los ejemplares hoy
entrega|cuándo se hace el traslado del material
entrega|quién recibe físicamente las muestras
acceso|no recuerdo mi clave de ingreso
acceso|tengo problemas para iniciar sesión
acceso|mi usuario no me deja entrar
acceso|recuperar contraseña del portal
acceso|cuál es el enlace para entrar a mi cuenta
acceso_registro|cómo me doy de alta como depositante
acceso_registro|abrir usuario para una nueva solicitud
acceso_registro|tengo que registrarme antes de empezar
contacto|necesito comunicarme con un curador
contacto|tienen dirección de correo electrónico
contacto|quiero escribirle al equipo del museo
contacto|a quién le pregunto por este caso
contacto|cómo contacto a una persona del laboratorio
contacto|con quién hablo para orientación institucional
catalogo|busquen registros de escarabajos publicados
catalogo|dónde consulto las especies divulgadas
catalogo|quiero consultar taxones del inventario público
catalogo|existe un buscador de ejemplares
UNKNOWN|cómo tramito mi licencia de conducir
UNKNOWN|dime una receta de sopa
UNKNOWN|cuál fue el marcador del campeonato
UNKNOWN|puedes reservarme un vuelo
UNKNOWN|redacta una carta comercial
UNKNOWN|predice el precio del dólar
ambiguous|tengo una consulta sobre un trámite
ambiguous|quiero dejar algo pero no sé bajo qué modalidad
DATA;

    private const CALIBRATION_MORE = <<<'DATA'
deposito|me interesa que la EPN guarde los especímenes sin transferirlos
deposito|cómo abro el expediente de custodia
donacion|mi colección pasará a pertenecer al museo
donacion|les regalaría los invertebrados de mi estudio
documentos|qué anexos acompañan el registro digital
documentos|cuáles respaldos electrónicos hacen falta
ambiguous|papeles?
documentos_permisos|la guía de transporte se adjunta en el portal
documentos_firma|hay que verificar el documento con firma digital
deposito_requisitos|qué condiciones rigen si solo presto custodia
donacion_requisitos|qué condiciones se aplican a una cesión definitiva
revision|una vez enviado, quién evalúa el expediente
revision|envié el trámite; qué hace curaduría ahora
revision_estado|se puede mirar el avance de mi caso
entrega|me acerco al laboratorio con la caja o espero
entrega|cuándo se programa la recepción de los frascos
acceso|no logro autenticarme con mi usuario
acceso_registro|cómo obtengo un usuario nuevo
contacto|hay alguien del laboratorio a quien escribir
contacto|necesito el correo para pedir orientación
catalogo|en qué página busco ejemplares divulgados
UNKNOWN|me recomiendas un restaurante
UNKNOWN|qué temperatura hace en Guayaquil
ambiguous|necesito ayuda con unos animales
deposito_requisitos|y las condiciones?|deposito
donacion_requisitos|y las condiciones?|donacion
DATA;

    /** Holdout nuevo: se ejecuta una sola vez después de fijar el clasificador. */
    private const TEST_FINAL = <<<'DATA'
saludo|buen día equipo del museo
saludo|hola, hay alguien que me oriente
saludo|muy buenas, recién entro al portal
conversacion|te agradezco la explicación
conversacion|vale, quedó claro
conversacion|hasta la próxima consulta
deposito|poseo frascos que deseo poner bajo custodia por unos meses
deposito|cómo registro especímenes sin cederlos a la institución
deposito|el material seguirá siendo mío, pero quisiera resguardarlo allí
deposito|me interesa iniciar el ingreso de un lote temporal
deposito|necesito dejar una caja en el museo y retirarla después
donacion|quiero que la universidad se quede con esta colección
donacion|puedo obsequiar mis ejemplares al laboratorio
donacion|la propiedad del material pasaría definitivamente a la EPN
donacion|tengo invertebrados para entregar como aporte permanente
donacion|no es un préstamo ni custodia, deseo ceder el lote
documentos|qué evidencias debo anexar a la solicitud electrónica
documentos|necesito la lista de respaldos para completar el expediente
documentos|en la plataforma, qué archivos me pedirán cargar
documentos|cuáles documentos van junto al formulario
documentos|tengo los papeles pero no sé cuáles adjuntar
documentos_permisos|es obligatoria la autorización de colecta para mi caso
documentos_permisos|qué hago con la guía que autoriza mover el lote
documentos_firma|se comprueba la firma electrónica del archivo adjunto
documentos_firma|debo cargar un pdf ya firmado digitalmente
deposito_requisitos|qué condiciones evalúan al aceptar custodia temporal
deposito_requisitos|criterios de admisión si el lote no cambia de dueño
donacion_requisitos|qué exigen para recibir una cesión permanente
donacion_requisitos|cuáles son las condiciones para regalar la colección
revision|el equipo curatorial ya recibió mi expediente, qué ocurre
revision|cómo puedo saber si me pidieron correcciones
revision|quién analiza los datos después de enviar el formulario
revision|dónde consulto el seguimiento de mi solicitud
revision|mi expediente está en evaluación, cómo lo reviso
entrega|en qué fase puedo llevar físicamente los frascos
entrega|necesito coordinar el traslado al laboratorio
entrega|se fija una cita para recibir los ejemplares
entrega|puedo acercarme con el lote sin esperar instrucciones
entrega|dónde se hace la recepción material
acceso|olvidé los datos para autenticarme
acceso|la página no me permite entrar con mi clave
acceso|cómo recupero la contraseña de la cuenta
acceso_registro|todavía no tengo usuario, cómo lo creo
acceso_registro|cuál es el proceso de alta de depositantes
acceso_registro|necesito una cuenta nueva para empezar
contacto|puedo escribir directamente a una persona de curaduría
contacto|cuál es el email de atención del museo
contacto|quiero hablar con el responsable de depósitos
contacto|dónde pido orientación al laboratorio
catalogo|quiero explorar los taxones que sí son públicos
catalogo|dónde veo el inventario divulgado de invertebrados
catalogo|puedo filtrar la colección por género y provincia
catalogo|hay registros publicados de Megasoma
catalogo|busca el código EPN-9080 en el catálogo
UNKNOWN|resuelve esta integral definida
UNKNOWN|puedes redactar mi currículo
UNKNOWN|cuánto cuesta una laptop nueva
UNKNOWN|qué equipo ganó la final de baloncesto
UNKNOWN|dame un pronóstico bursátil
UNKNOWN|cómo preparar chocolate caliente
UNKNOWN|cuál es la hora en Madrid
ambiguous|documentos
ambiguous|requisitos
ambiguous|no sé si debo dejarlo o regalarlo
ambiguous|quiero entregar algo al museo
ambiguous|necesito orientación sobre un trámite
deposito_requisitos|y las exigencias del proceso?|deposito
donacion_requisitos|qué condiciones se aplican entonces?|donacion
DATA;

    /** Robustez, sin participación en el ajuste de pesos. */
    private const CHALLENGE = <<<'DATA'
short|ambiguous|papeles
short|ambiguous|requisitos
short|deposito|deposito
short|donacion|donacion
short|contacto|contacto
short|acceso|acceso
short|revision|estado
typos|documentos|nesesito documetos para el tramite
typos|donacion|quisiera aser una donasion
typos|deposito|como abro un deposiito
typos|deposito_requisitos|requicitos de custodia temporal
context|documentos|y los papeles?|deposito
context|donacion_requisitos|y las condiciones?|donacion
context|entrega|y dónde llevo el lote?|deposito
negation|donacion|no es depósito, es donación
negation|deposito|no quiero donar sino depositar
negation|entrega|no necesito requisitos, quiero saber dónde entrego
correction|catalogo|perdón, quería decir Megasoma
correction|catalogo|no Pichincha, Quito
ambiguity|ambiguous|necesito hacer un trámite
ambiguity|ambiguous|quiero entregar algo
ambiguity|ambiguous|documentos
ood|UNKNOWN|cómo está el clima en Lima
ood|UNKNOWN|hazme una receta con arroz
ood|UNKNOWN|quién ganó ayer la carrera
catalog|catalogo|tienen Dynastes de Ecuador
catalog|catalogo|cuántos Megasoma están publicados
catalog|catalogo|buscar por código MEPN-0008
DATA;

    /** @return list<array{question:string,expected:string,context:?string}> */
    public static function particion(string $name): array
    {
        if ($name === 'challenge') return self::desafios();
        $text = match ($name) {
            'train' => self::TRAIN."\n".self::TRAIN_MORE,
            'calibration' => self::CALIBRATION."\n".self::CALIBRATION_MORE,
            'test' => self::TEST,
            'test_final' => self::TEST_FINAL,
            'train_more' => self::TRAIN_MORE,
            'calibration_more' => self::CALIBRATION_MORE,
            default => throw new \InvalidArgumentException('Partición de corpus desconocida.'),
        };
        return array_map(static function (string $line): array {
            [$expected, $question, $context] = array_pad(explode('|', $line, 3), 3, null);
            return compact('question', 'expected', 'context');
        }, explode("\n", trim($text)));
    }

    public static function tamanos(): array
    {
        return ['train' => count(self::particion('train')), 'calibration' => count(self::particion('calibration')),
            'test' => count(self::particion('test')), 'test_final' => count(self::particion('test_final')),
            'challenge' => count(self::desafios())];
    }

    public static function desafios(): array
    {
        return array_map(static function (string $line): array {
            [$tag, $expected, $question, $context] = array_pad(explode('|', $line, 4), 4, null);
            return compact('tag', 'expected', 'question', 'context');
        }, explode("\n", trim(self::CHALLENGE)));
    }

    public static function casos(): array
    {
        return [
            ['hola', 'saludo'], ['buenas tardes', 'saludo'],
            ['quiero hacer un depósito', 'deposito'], ['quiero depositar', 'deposito'],
            ['tengo muestras y quiero entregarlas', 'ambiguous'],
            ['deseo donar especímenes', 'donacion'], ['quiero donar', 'donacion'],
            ['regalar muestras al laboratorio', 'donacion'],
            ['qué documentos necesito', 'documentos'], ['que papeles tengo que mandar', 'documentos'],
            ['q papeles necesito para dejar unas muestras', 'documentos'],
            ['que documetos nesecito para el deposito', 'documentos'],
            ['requisitos', 'ambiguous'], ['qué requisitos hay', 'ambiguous'],
            ['ya mandé mis documentos, ¿qué sigue?', 'revision'],
            ['estado de mi solicitud', 'revision'], ['puedo llevarlos mañana', 'entrega'],
            ['donde llevo los bichos', 'entrega'], ['buscar especimenes', 'catalogo'],
            ['consultar catálogo', 'catalogo'], ['iniciar sesión', 'acceso'],
            ['correo del laboratorio', 'contacto'],
            // Parafraseos no sembrados como aliases: miden generalización del matching.
            ['quisiera dejar unos ejemplares', 'deposito'],
            ['me gustaría donar muestras', 'donacion'],
            ['qué archivos debo adjuntar', 'documentos'],
            ['ya envié la solicitud qué ocurre', 'revision'],
            ['dónde reviso mi expediente', 'revision'],
            ['me puedo acercar mañana con las muestras', 'entrega'],
            ['necesito registrarme', 'acceso_registro'],
        ];
    }
}
