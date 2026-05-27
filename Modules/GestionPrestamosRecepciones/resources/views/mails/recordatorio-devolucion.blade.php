<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aviso de préstamo</title>
</head>
<body style="font-family: Inter, sans-serif; color: #212121; background: #F5F7FA; padding: 32px;">
    <div style="max-width: 560px; margin: 0 auto; background: #FFFFFF; border-radius: 8px; padding: 32px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2 style="color: #1B365D; margin-top: 0;">
            @if ($estadoRecordatorio === 'vencido')
                Su préstamo ha vencido
            @else
                Su préstamo está próximo a vencer
            @endif
        </h2>

        <p>Estimado investigador,</p>

        <p>
            @if ($estadoRecordatorio === 'vencido')
                Le informamos que el plazo de devolución de su préstamo ha <strong>vencido</strong>.
                Por favor, proceda a realizar la devolución a la brevedad posible.
            @else
                Le recordamos que el plazo de devolución de su préstamo está <strong>próximo a vencer</strong>.
                Por favor, coordine la devolución con anticipación.
            @endif
        </p>

        <p style="color: #757575; font-size: 0.875rem;">
            Número de préstamo: <strong>{{ $prestamoId }}</strong>
        </p>

        <hr style="border: none; border-top: 1px solid #E0E0E0; margin: 24px 0;">

        <p style="font-size: 0.75rem; color: #757575;">
            Este es un mensaje automático del sistema Hub Digital EPN.
            Por favor no responda a este correo.
        </p>
    </div>
</body>
</html>
