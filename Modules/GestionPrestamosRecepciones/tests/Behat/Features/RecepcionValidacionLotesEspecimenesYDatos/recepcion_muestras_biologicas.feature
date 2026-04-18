# language: es
Característica: Recepción de muestras biológicas
    Como curador
    Quiero recibir lotes de especímenes entomológicos
    Para tener material que ayude en la investigación y la conservación

    # TODO: Otorgar al curador la posibilidad de decidir que campos son obligatorios en Darwin Core.

    Antecedentes:
        Dado que un investigador cuenta con la documentación oficial requerida:
            | Documento                                         |
            | Formato de Solicitud de Depósito                  |
            | Copia de la Autorización de Colecta (MAATE)       |
            | Copia del Permiso de Movilización (Opcional)      |
        #Fuera de la Provincia de Pinchincha
        Y ha preparado una matriz Darwin Core con los datos de las muestras

    # --- FASE 1: Extracción de Documentos y Llenado Inicial ---

    Escenario: Extracción automática de datos desde los documentos
        Cuando el investigador inicia el trámite subiendo la documentación
        Entonces se extraen automáticamente los siguientes datos:
            | Documento Origen                               | Dato a Extraer                                                               |
            | Copia de la Autorización de Recolección (MAATE)| N.º Permiso Recolección                                                      |
            | Copia del Permiso de Movilización              | N.º Permiso Movilización, Grupo Animal, Provincia, Localidad, N.º Lotes      |
        Y se autocompletan los campos correspondientes en la solicitud de depósito

    Escenario: Completar manualmente datos obligatorios no detectados
        Dado que la extracción automática no obtiene toda la información obligatoria
        Cuando el investigador ingresa los datos faltantes
        Entonces la solicitud se registra exitosamente
        Y se marca para revisión detallada por parte de un curador

    # --- FASE 2: Validaciones de Identidad y Taxonomía en la Matriz ---

    Escenario: Validación de Identidad Cruzada
        Cuando el investigador envía su Solicitud de Depósito
        Entonces el Nombre del Representante y la Empresa detectados en los documentos deben coincidir con su perfil de usuario registrado

    Escenario: Autorizar envío de solicitud cuando el nombre no coincide exactamente
        Dado que en los documentos aparece un nombre que no coincide con la cuenta del usuario
        Cuando el investigador fuerza el envío del trámite asumiendo la responsabilidad
        Entonces la solicitud es recibida
        Y se resalta como prioritaria para la verificación manual de identidad por un curador

        #TODO: Cuando no coincide algun dato de identidad, el depositante debe actualizar el archivo o actualizar el perfil.
        #TODO: Limite de deposito anuales maximo 3.
        #TODO: Donación de especimenes, no existe limite. (Suele ser una Universidad) (Permiso de Movilización y Colecto)

    Esquema del escenario: Corrección de errores taxonómicos sugeridos
        Dado que el investigador ha subido una matriz Darwin Core
        Y la matriz contiene la especie "<especie_ingresada>" con posibles errores tipográficos
        Y el sistema sugiere la corrección a "<especie_sugerida>" según GBIF
        Cuando el investigador acepta la sugerencia de corrección
        Entonces los registros se marcan como validados
        Y el proceso de recepción continúa normalmente

        Ejemplos:
            | especie_ingresada | especie_sugerida |
            | Apis melifera     | Apis mellifera   |
            | Panthera oncae    | Panthera onca    |

    Esquema del escenario: Justificación de especie nueva o no listada
        Dado que el investigador ha subido una matriz Darwin Core
        Y la matriz contiene la especie desconocida "<especie>"
        Cuando el investigador selecciona el motivo "<motivo_investigador>" para justificar el hallazgo
        Entonces el registro se marca para validación manual

        Ejemplos:
            | especie           | motivo_investigador    |
            | Morpho sp. nov.   | Es una Especie Nueva   |
            | Insecta incognita | No listada en catálogo |

    # --- FASE 3: Aprobación Documental / Técnica Virtual ---

    Escenario: Revisión manual de una solicitud con alertas y generación de QR
        Dado que un curador evalúa una solicitud marcada como prioritaria
        Cuando el curador confirma que los documentos son válidos, la identidad es correcta y aprueba el trámite
        Entonces la solicitud queda lista para la recepción física
        Y se generan las etiquetas QR para que el investigador pueda realizar el depósito

    Escenario: Aprobación automatizada sin alertas y generación de QR
        Cuando el investigador envía la documentación y la matriz sin presentar alertas ni inconsistencias
        Entonces el trámite se aprueba automáticamente de forma exitosa
        Y la solicitud queda lista para la recepción física
        Y se generan las etiquetas QR para que el investigador pueda realizar el depósito

    # --- FASE 4: Entrega Física del Material ---

    Escenario: Recepción física exitosa sin fallos de integridad
        Dado que el curador recibe el lote físico transportado por el investigador
        Y escanea la etiqueta QR de las muestras para su revisión en el portal
        Cuando el curador verifica que la integridad física de las muestras es correcta
        Entonces el lote se marca como verificado físicamente
        Y el proceso avanza a la generación del acta de entrega

    Esquema del escenario: Integridad comprometida durante la recepción física
        Dado que el curador recibe el lote físico transportado por el investigador
        Y escanea la etiqueta QR de las muestras para su revisión en el portal
        Cuando el curador registra un fallo de integridad por "<causa_fallo>" en la muestra
        Entonces se detiene el flujo de recepción
        Y se inicia el protocolo de corrección pertinente

        Ejemplos:
            | causa_fallo                   |
            | Nivel de alcohol insuficiente |
            | Frascos rotos                 |
            | Inconsistencia en conteo      |

    # --- FASE 5: Generación de Acta de Entrega e Ingreso a "Colección Temporal" ---

    Escenario: Aprobación final y asignación de estado temporal
        Dado que un lote físico ha sido evaluado y se encuentra en estado conforme
        Cuando el curador aprueba el proceso de recepción física
        Entonces el sistema genera un acta digital de recepción
        Y a todos los especímenes de este lote se les asigna el estado "TEMPORAL"
        Y se envía una notificación de recepción finalizada al investigador
