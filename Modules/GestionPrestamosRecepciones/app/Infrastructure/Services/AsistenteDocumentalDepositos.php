<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Services;

use Illuminate\Support\Str;

/** Orientación local por intención, conversación y estado real del expediente. */
final class AsistenteDocumentalDepositos
{
    /** @param list<string> $requeridos @param list<array{rol:string,texto:string}> $historial */
    public function responder(string $pregunta, array $requeridos, array $historial = [], array $contexto = []): string
    {
        $normal = $this->normalizar($pregunta);
        $requeridos = array_values(array_filter($requeridos, 'is_string'));
        $lista = $this->lista($requeridos);
        $cartaRequerida = collect($requeridos)->contains(fn (string $nombre): bool => str_contains($this->normalizar($nombre), 'justific'));

        if (preg_match('/ignora|salta.*valid|omite.*valid|aprobar sin|acepta todo|burlar/', $normal)) {
            return 'La orientación del chat no aprueba archivos ni omite sus controles. Adjunta el documento solicitado; su contenido, integridad y firma se comprueban en el flujo de carga y validación.';
        }
        if (preg_match('/virus|malware|antivirus|javascript|\bjs\b|\.exe|ejecutable|renombr|extension/', $normal)) {
            return 'Cambiar la extensión a .pdf no convierte un archivo en PDF. Debe ser un PDF real de hasta 20 MB. La carga comprueba su cabecera, estructura y contenido activo; rechaza JavaScript, acciones activas y ejecutables renombrados. Esas comprobaciones no equivalen a un antivirus. Un PDF seguro también debe corresponder a la casilla y superar la validación documental.';
        }
        if (preg_match('/corrupt|danad|roto|incompleto|no abre|ilegible|borros|escane|foto|ocr/', $normal)) {
            return 'Adjunta el PDF completo, con todas sus páginas y texto legible. Si está dañado, vuelve a obtenerlo de su emisor o exporta nuevamente el original. Para un escaneo, revisa que no falten páginas y que pueda leerse con claridad; si la lectura queda incompleta, el sistema no puede confirmar su contenido.';
        }
        if (preg_match('/megab|\bmb\b|tamano|peso|limite|formato|\bword\b|\.doc|imagen|jpg|png/', $normal)) {
            return 'Cada archivo debe ser un PDF real de hasta 20 MB. Exporta el documento completo a PDF; renombrar Word, una imagen o un ejecutable no sirve. Puedes elegirlo o arrastrarlo a la casilla correspondiente. Su nombre no determina el tipo: se revisa el contenido.';
        }
        if (preg_match('/por que.*rechaz|motivo.*rechaz|error|fallo|no.*sube|no.*carga/', $normal)) {
            $rechazos = array_filter($contexto['rechazos'] ?? [], 'is_array');
            if ($rechazos !== []) {
                $ultimo = end($rechazos);
                return 'La última carga fue rechazada: '.Str::limit((string) ($ultimo['mensaje'] ?? 'No superó los controles de carga.'), 500).' Reemplaza ese archivo por el PDF correcto y completo.';
            }
            return 'Comprueba que el PDF sea completo, legible, de hasta 20 MB y del tipo solicitado. Si aparece un aviso de rechazo, dime qué indica para orientarte. Todavía no consta un motivo de rechazo en esta conversación.';
        }
        if (preg_match('/no (?:tengo|cuento|dispongo|poseo)[^?!.]{0,40}(?:permis|document|autoriz|papel)|sin (permisos?|documentos)|carezco.*(?:permis|document)|ningun documento/', $normal)) {
            if ($cartaRequerida) {
                return 'En la opción que elegiste se solicita una carta de justificación, institucional o personal. Explica de dónde provienen los especímenes y por qué no dispones de los permisos, identifica al titular y el proyecto e incluye fecha y firma. Adjunta esa carta en su casilla; la revisión documental decidirá si la explicación es suficiente.';
            }
            if (preg_match('/no (?:tengo|cuento|dispongo|poseo).*permis|sin permisos|carezco.*permis/', $normal)) {
                return 'Si no tienes permisos del MAE, vuelve al paso 3 Origen y revisa la situación regulatoria. Si corresponde a tu caso, selecciona «Sin permisos del MAE»; el paso 4 solicitará una carta de justificación, institucional o personal, con titular, proyecto, procedencia, explicación, fecha y firma digital. La carta explica tu situación y queda sujeta a revisión; no equivale a un permiso. La selección actual todavía exige autorización y movilización.';
            }
            return "Para este expediente se requieren:\n{$lista}\nSi no dispones de un documento, este chat puede explicar su función, pero no eliminar el requisito ni aprobar su ausencia.";
        }
        if (preg_match('/mismo expediente|coincid|correspond|relacion|otro proyecto|otra persona|otro titular|nombre.*difer|proyecto.*difer/', $normal)) {
            return 'Cada archivo se revisa por separado según su casilla: la autorización debe autorizar la recolección y el permiso o guía debe respaldar la movilización. No se comparan estos dos documentos entre sí. El nombre del archivo no determina su tipo; primero se comprueba su contenido y, si corresponde, después su firma digital.';
        }
        if (preg_match('/firma|certificado|\.p12|\.pfx|token|clave|contrasen/', $normal)) {
            $prefijo = ($contexto['solicitud_firmada'] ?? false) ? 'Tu solicitud inicial ya figura firmada. ' : '';
            return $prefijo.'Cada archivo externo se comprueba por separado. Una carta sin firma digital puede cargarse para mostrar su resultado, pero no permite superar el control de firmas. Solicita el PDF firmado a su emisor o firma tu propia carta mediante un mecanismo autorizado. Adjunta el archivo completo; editarlo o imprimirlo de nuevo puede invalidar su firma electrónica. El chat no recibe certificados, claves ni PIN.';
        }
        if (preg_match('/estado|ya (subi|cargue|adjunte)|que.*falt|pendiente|guardad|revisaste|verificaste|esta.*validado/', $normal)) {
            $cargados = array_values(array_intersect($requeridos, $contexto['cargados'] ?? []));
            $faltantes = array_values(array_diff($requeridos, $cargados));
            if ($faltantes !== []) return count($cargados).' de '.count($requeridos)." archivos cargados. Aún falta:\n".$this->lista($faltantes);
            return $requeridos === [] ? 'En este paso no figuran archivos externos requeridos.' : 'Todos los archivos requeridos figuran cargados. La carga por sí sola no acredita que sus firmas y contenido hayan sido aprobados. Consulta el resultado de cada casilla y pulsa «Validar documentos» cuando esté habilitado.';
        }

        $tema = $normal;
        if (preg_match('/^(y\b|como (la|lo|las|los)\b|que debe (incluir|decir)|que (significa|es) eso)/', $normal)) {
            foreach (array_reverse(array_slice($historial, -6)) as $mensaje) {
                if (($mensaje['rol'] ?? '') !== 'usuario' || !is_string($mensaje['texto'] ?? null)) continue;
                $tema .= ' '.$this->normalizar($mensaje['texto']);
                break;
            }
        }
        if (preg_match('/justific|motivos|redact|como.*(la hago|escribo)|que debe (incluir|decir)/', $tema)
            || ($cartaRequerida && preg_match('/carta|no tengo|sin permiso/', $tema))) {
            return "La carta de justificación explica el origen del material y la ausencia de permisos. Puedes organizarla así:\n• Fecha y destinatario.\n• Titular e identificación, institución si corresponde y proyecto.\n• Procedencia de los especímenes y explicación concreta.\n• Documentos o antecedentes que respaldan lo indicado.\n• Nombre y firma de quien justifica.\nExporta el documento completo a PDF y súbelo en la casilla de justificación. No inventes permisos ni hechos.";
        }
        if (preg_match('/autoriz|recolecci|mae|maate/', $tema)) {
            return 'La autorización de recolección identifica al titular, proyecto, número y vigencia. Debe subirse completa en la casilla de autorización cuando sea requerida. '.($cartaRequerida ? 'En tu selección actual se requiere una carta de justificación; la autorización no sustituye esa carta.' : 'Su contenido y firma se verifican individualmente.');
        }
        if (preg_match('/moviliz|guia|traslado/', $tema)) {
            return 'La guía o permiso de movilización respalda el traslado e identifica origen, destino, fechas y autorización relacionada. Súbelo completo en su casilla si figura entre los archivos requeridos. '.($cartaRequerida ? 'Tu selección actual pide una carta de justificación.' : 'Su contenido y firma se verifican individualmente.');
        }
        if (preg_match('/cesion|donacion/', $tema)) return 'La carta de cesión identifica a quien dona, el material, su origen y la voluntad de cederlo a la colección; incluye fecha y firma. Se adjunta cuando esa casilla figure en los documentos requeridos de una donación.';
        if (preg_match('/procedencia|origen/', $tema)) return 'El documento de procedencia explica de dónde proviene el material y quién lo entrega, con referencia al proyecto y antecedentes disponibles. Adjunta el PDF completo en la casilla que pide procedencia; una guía o autorización es un documento distinto.';
        if (preg_match('/delegaci|tercero|representante/', $tema)) return 'La carta de delegación identifica a quien delega y a la persona autorizada, explica para qué trámite se delega e incluye fecha y firma. Se adjunta únicamente cuando el expediente la requiera.';
        if (preg_match('/^gracias[!. ]*$/', $normal)) return 'Con gusto. Puedes seguir preguntándome por los documentos de este expediente.';

        return $requeridos !== []
            ? "En este paso debes adjuntar:\n{$lista}\nPuedo explicarte qué debe contener, cómo preparar el PDF, las firmas o un aviso de rechazo. ¿Cuál es tu duda?"
            : 'No figuran archivos externos requeridos en este paso. Puedo orientarte sobre formato PDF, firmas y correspondencia de los documentos con el expediente.';
    }

    private function normalizar(string $texto): string
    {
        $normal = Str::lower(Str::ascii($texto));
        return strtr($normal, ['ocificio' => 'oficio', 'justifcacion' => 'justificacion', 'movilziacion' => 'movilizacion', 'autorisacion' => 'autorizacion', 'firna' => 'firma', 'documetos' => 'documentos']);
    }

    /** @param list<string> $nombres */
    private function lista(array $nombres): string
    {
        return implode("\n", array_map(static fn (string $nombre): string => '• '.$nombre, array_slice($nombres, 0, 8)));
    }
}
