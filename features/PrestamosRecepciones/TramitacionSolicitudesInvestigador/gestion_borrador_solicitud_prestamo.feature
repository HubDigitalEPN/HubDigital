# languague: es

Feature: Gestion de borrador de solicitud de prestamo
    Como investigador que desea solicitar un prestamo
    quiero registrar y editar una solicitud en estado de borrador,
    para reducir observaciones durante la evaluación.

    Scenario: Guardar una solicitud como borrador
        Given que el investigador ha ingresado información en una solicitud
        When guarda la solicitud
        Then la solicitud se almacenara en estado de borrador

    Scenario: Editar una solicitud en estado de borrador
        Given que existe una solicitud en estado de borrador
        And el investigador accede a dicha solicitud
        When modifica su información y guarda los cambios
        Then la informacion de la solicitud sera actualizada manteniéndola en estado de borrador
