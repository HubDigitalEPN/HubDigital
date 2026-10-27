<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Documentos;

use App\Support\PortalCopy;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Modules\GestionPrestamosRecepciones\Application\Ports\GeneradorActaRecepcionPort;
use Modules\GestionPrestamosRecepciones\Application\Ports\UsuarioNombrePort;
use Modules\GestionPrestamosRecepciones\Application\UseCases\ConsultarDetalleRecepcion\ConsultarDetalleRecepcionOutput;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\RecepcionLoteEloquentModel;
use Modules\GestionPrestamosRecepciones\Presentation\Support\FechaEcuador;

final class DomPdfGeneradorActaRecepcionAdapter implements GeneradorActaRecepcionPort
{
    public function __construct(private readonly UsuarioNombrePort $usuarios) {}

    public function generar(ConsultarDetalleRecepcionOutput $recepcion): string
    {
        $depositante = $this->usuarios->obtenerDatosDepositante($recepcion->investigadorId);
        $curador = $recepcion->actaGeneradaPor !== null ? $this->usuarios->obtenerNombre($recepcion->actaGeneradaPor) : null;
        $nombreFirma = $curador;
        if ($curador !== null && preg_match('/^Adri[aá]n(?:\s+Esteban)?\s+Troya\b/iu', trim($curador))) $nombreFirma = 'Dr. Adrian Troya';
        // El responsable institucional puede diferir de quien registra la operación.
        // recibido_por conserva al operador para la auditoría del expediente.
        $receptor = PortalCopy::text('depositos.recepcion_responsable');
        $fechaBase = CarbonImmutable::instance($recepcion->actaGeneradaEn ?? $recepcion->verificadoEn ?? now())
            ->setTimezone(FechaEcuador::ZONA);
        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $fecha = sprintf('%d de %s de %d', (int) $fechaBase->format('j'), $meses[(int) $fechaBase->format('n') - 1], (int) $fechaBase->format('Y'));
        $perfil = 'acta-recepcion:curador:v1';
        $ruta = str_replace([':', '_'], ['/', '-'], $perfil);
        $versionActa = ((int) RecepcionLoteEloquentModel::query()
            ->where('solicitud_deposito_id', $recepcion->solicitudId)
            ->value('acta_original_version')) + 1;

        return Pdf::loadView('gestionprestamosrecepciones::pdf.acta-recepcion', [
            'recepcion' => $recepcion,
            'depositante' => $depositante,
            'investigador' => $depositante?->nombre ?? $recepcion->investigadorId,
            'curador' => $curador,
            'nombreFirma' => $nombreFirma,
            'receptor' => $receptor,
            'fecha' => $fecha,
            'versionActa' => $versionActa,
            'perfilFirma' => ['perfil' => $perfil, 'rol' => 'curador', 'bloque' => 'https://firmas.hubdigital.invalid/bloques/'.$ruta, 'zona' => 'https://firmas.hubdigital.invalid/zonas/'.$ruta],
        ])->output();
    }
}
