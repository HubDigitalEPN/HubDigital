# language: es
Característica: Registro de solicitud de depósito
    Como investigador
    Quiero registrar una nueva solicitud de depósito con la documentación oficial
    Para iniciar el trámite de entrega de especímenes entomológicos a la colección

    Antecedentes:
        Dado que el investigador tiene una cuenta activa en el sistema
        Y ha iniciado una nueva solicitud de depósito

    Esquema del escenario: Aplicación de límite anual por tipo de trámite
        Dado que el investigador tiene <solicitudes_previas> solicitudes de tipo "<tipo_tramite>" registradas este año
        Cuando el investigador intenta crear una nueva solicitud de tipo "<tipo_tramite>"
        Entonces el sistema establece el estado de la solicitud en "<estado_solicitud>"
        Y el sistema emite el mensaje "<mensaje_alerta>"

        Ejemplos:
            | tipo_tramite | solicitudes_previas | estado_solicitud | mensaje_alerta                         |
            | Depósito     | 2                   | En Borrador      | Ninguno                                |
            | Depósito     | 3                   | Rechazada        | Límite anual de depósitos alcanzado    |
            | Donación     | 3                   | En Borrador      | Ninguno                                |
            | Donación     | 10                  | En Borrador      | Ninguno                                |

    Esquema del escenario: Validación de Permiso de Movilización por provincia de origen
        Dado que el investigador declara que las muestras provienen de la provincia de "<provincia>"
        Y el investigador "<estado_adjunto>" el documento "Copia del Permiso de Movilización"
        Cuando el investigador envía la documentación inicial
        Entonces el sistema establece el estado documental en "<estado_documental>"

        Ejemplos:
            | provincia | estado_adjunto | estado_documental   |
            | Pichincha | no adjunta     | Válido              |
            | Pichincha | adjunta        | Válido              |
            | Guayas    | adjunta        | Válido              |
            | Guayas    | no adjunta     | Requiere Corrección |

    Escenario: Extracción automática de datos de los documentos oficiales
        Dado que el investigador adjunta los documentos oficiales requeridos
        Cuando el sistema procesa los documentos subidos
        Entonces se extraen y autocompletan automáticamente los siguientes campos en la solicitud:
            | Campo en Solicitud       | Documento Origen                                |
            | N.º Permiso Recolección  | Copia de la Autorización de Recolección (MAATE) |
            | N.º Permiso Movilización | Copia del Permiso de Movilización               |
            | Grupo Animal             | Copia del Permiso de Movilización               |
            | Provincia                | Copia del Permiso de Movilización               |
            | Localidad                | Copia del Permiso de Movilización               |

    Escenario: Completitud manual de datos obligatorios no extraídos automáticamente
        Dado que el sistema procesa los documentos subidos
        Pero no logra extraer el campo obligatorio "N.º Lotes"
        Cuando el investigador ingresa manualmente el valor en el campo "N.º Lotes"
        Y envía la solicitud
        Entonces el sistema registra la solicitud exitosamente
        Y el sistema establece el estado de la solicitud en "Pendiente de Revisión por Curaduría"

    Escenario: Validación automática de identidad del solicitante
        Cuando el sistema compara el nombre en los documentos subidos con el perfil del usuario
        Entonces el sistema genera una "Alerta de Discrepancia de Identidad" si los datos no coinciden
        Y permite al investigador adjuntar una justificación o corregir el perfil
