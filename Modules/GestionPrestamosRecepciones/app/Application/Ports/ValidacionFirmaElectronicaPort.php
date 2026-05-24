<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Application\Ports;

use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma;

interface ValidacionFirmaElectronicaPort
{
    /**
     * Verifica si un PDF tiene firma electrónica válida.
     *
     * @param  string  $rutaAbsoluta  Ruta absoluta al archivo PDF
     * @return ResultadoValidacionFirma Firmado, SinFirma o NoVerificado si hubo error
     */
    public function verificarFirma(string $rutaAbsoluta): ResultadoValidacionFirma;
}
