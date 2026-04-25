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
        Entonces la solicitud queda en estado "<estado_solicitud>"
        Y el investigador es notificado con el mensaje "<mensaje_alerta>"

        Ejemplos:
            | tipo_tramite | solicitudes_previas | estado_solicitud | mensaje_alerta                         |
            | Depósito     | 2                   | En Borrador      | Ninguno                                |
            | Depósito     | 3                   | Rechazada        | Límite anual de depósitos alcanzado    |
            | Donación     | 3                   | En Borrador      | Ninguno                                |
            | Donación     | 10                  | En Borrador      | Ninguno                                |

    Esquema del escenario: Validación de Permiso de Movilización por provincia de origen
        Dado que el investigador declara que las muestras provienen de la provincia de "<provincia>"
        Y el documento "Copia del Permiso de Movilización" se encuentra "<estado_adjunto>"
        Cuando el investigador envía la documentación inicial
        Entonces el estado documental de la solicitud es "<estado_documental>"
        Ejemplos:
            | provincia | estado_adjunto | estado_documental   |
            | Pichincha | No Adjuntado   | Válido              |
            | Pichincha | Adjuntado      | Válido              |
            | Guayas    | Adjuntado      | Válido              |
            | Guayas    | No Adjuntado   | Requiere Corrección |

    @deposito
    Escenario: Integración de datos a partir de documentación oficial para Depósitos
        Dado que el investigador seleccionó el trámite de "Depósito"
        Cuando el investigador carga los siguientes documentos:
            | Documento Oficial                               |
            | Formato Solicitud Depósito                      |
            | Copia de la Autorización de Recolección (MAATE) |
            | Copia del Permiso de Movilización               |
        Entonces la solicitud incorpora automáticamente la siguiente información:
            | Información requerida    | Extraída de                                     |
            | N.º Permiso Recolección  | Copia de la Autorización de Recolección (MAATE) |
            | N.º Permiso Movilización | Copia del Permiso de Movilización               |
            | Grupo Animal             | Copia del Permiso de Movilización               |
            | Provincia                | Copia del Permiso de Movilización               |
            | Localidad                | Copia del Permiso de Movilización               |

    @donacion
    Escenario: Carga de documentación oficial para Donaciones
        Dado que el investigador seleccionó el trámite de "Donación"
        Cuando el investigador carga los siguientes documentos:
            | Documento Oficial                               |
            | Formato Solicitud Donación                      |
            | Carta de Cesión de Derechos / Origen Lícito     |
        Entonces la solicitud registra el origen de la donación
        Y pasa a estar "Pendiente de Revisión por Curaduría"

    Escenario: Completitud de datos obligatorios faltantes en la documentación
        Dado que la documentación oficial no contiene el "Grupo Animal"
        Cuando el investigador provee esta información faltante
        Entonces la solicitud se registra exitosamente
        Y pasa a estar "Pendiente de Revisión por Curaduría"

    Esquema del escenario: Validación de identidad mediante el Formato de Solicitud
        Dado que el investigador ha cargado el "Formato Solicitud Depósito"
        Y su perfil de usuario está registrado como "<nombre_perfil>"
        Cuando se compara el perfil del investigador con el nombre "<nombre_en_documento>" del formulario        Entonces el resultado de la validación es "<resultado>"
        Y se habilita la acción: "<accion_permitida>"

        Ejemplos:
            | nombre_perfil | nombre_en_documento | resultado                 | accion_permitida                                |
            | Juan Pérez    | Juan Pérez          | Conforme                  | Continuar trámite                               |
            | Juan Pérez    | Juan Peres          | Discrepancia (Tipográfica) | Corregir nombre en Perfil                       |
            | Juan Pérez    | María Gómez         | Discrepancia (Tercero)     | Adjuntar Justificación / Carta de Delegación    |
