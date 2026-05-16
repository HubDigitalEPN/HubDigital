#language: es
Característica: Gestión del acta de préstamo
    Como curador responsable del proceso de préstamo
    Quiero gestionar el ciclo del acta de préstamo
    Para formalizar el préstamo con la firma del investigador.

    Escenario: Enviar el acta de préstamo al investigador para su firma
        Dado que existe un acta en estado pendiente de envío
        Cuando el curador envía el acta al investigador
        Entonces el acta queda en estado pendiente de firma
        Y el curador confirma que la notificación con el acta fue enviada al investigador

    Escenario: Validar el acta firmada por el investigador
        Dado que existe un acta en estado pendiente de validación
        Cuando el curador valida el acta firmada
        Entonces el acta queda en estado validada
        Y se crea un préstamo en estado activo

    Escenario: Devolver el acta por firma inválida
        Dado que existe un acta en estado pendiente de validación
        Cuando el curador devuelve el acta por motivos de firma con un comentario
        Entonces el acta queda en estado pendiente de firma
        Y el curador confirma que el investigador fue notificado con el motivo de la devolución
