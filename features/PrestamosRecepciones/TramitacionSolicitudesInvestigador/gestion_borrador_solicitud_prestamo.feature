#language: es

Característica: Gestion de borrador de solicitud de prestamo
    Como investigador que desea solicitar un prestamo
    quiero registrar y editar una solicitud en estado de borrador,
    para reducir observaciones durante la evaluación.

    Escenario: Guardar una solicitud como borrador
        Dado que el investigador ha ingresado información en una solicitud
        Cuando guarda la solicitud
        Entonces la solicitud se almacenara en estado de borrador

    Escenario: Editar una solicitud en estado de borrador
        Dado que existe una solicitud en estado de borrador
        Y el investigador accede a dicha solicitud
        Cuando modifica su información y guarda los cambios
        Entonces la informacion de la solicitud sera actualizada manteniéndola en estado de borrador
