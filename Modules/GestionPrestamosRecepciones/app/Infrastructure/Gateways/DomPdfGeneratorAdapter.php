<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Gateways;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Modules\GestionPrestamosRecepciones\Application\Ports\PdfGeneratorPort;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Adaptador para la generación del acta en PDF utilizando DomPDF.
 *
 * Es el ÚNICO lugar que sabe cómo se compone el acta, para que el PDF que se
 * descarga al vuelo y el que se almacena para firmar sean idénticos.
 */
final class DomPdfGeneratorAdapter implements PdfGeneratorPort
{
    private const VISTA = 'gestionprestamosrecepciones::pdf.acta-documento';

    /**
     * Genera el acta completa. DomPDF no soporta orientación mixta en un mismo
     * documento, así que la hoja de especímenes se renderiza aparte en horizontal
     * y se pega al final con FPDI.
     *
     * @param  array<string, mixed>  $datos
     */
    public function generarActa(array $datos): string
    {
        $acta = Pdf::loadView(self::VISTA, $datos)->output();

        if (count($datos['acta']->items) === 0) {
            return $acta;
        }

        $especimenes = Pdf::loadView(self::VISTA, array_merge($datos, ['soloEspecimenes' => true]))
            ->setPaper('a4', 'landscape')
            ->output();

        return $this->fusionar($acta, $especimenes);
    }

    /**
     * Genera el acta completa y la almacena en el storage.
     *
     * @param  array<string, mixed>  $datos
     * @return string La ruta donde se almacenó el archivo.
     */
    public function generarActaYAlmacenar(array $datos, string $rutaDestino): string
    {
        Storage::put($rutaDestino, $this->generarActa($datos));

        return $rutaDestino;
    }

    /**
     * Decodifica una imagen en base64 y la almacena como un archivo PNG.
     */
    public function almacenarImagenPng(string $base64, string $rutaDestino): void
    {
        $data = base64_decode(str_replace(' ', '+', substr($base64, strpos($base64, ',') + 1)));

        Storage::put($rutaDestino, $data);
    }

    /**
     * Lee una imagen PNG almacenada y la devuelve como data-URI base64.
     */
    public function leerImagenBase64(string $ruta): ?string
    {
        if (! Storage::exists($ruta)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(Storage::get($ruta));
    }

    /**
     * Fusiona varios PDF (bytes) en uno solo respetando el tamaño y la orientación
     * de cada hoja original. Solo recibe PDF generados por nosotros (DomPDF).
     */
    private function fusionar(string ...$pdfs): string
    {
        $fpdi = new Fpdi;
        $fpdi->SetAutoPageBreak(false);

        foreach ($pdfs as $bytes) {
            $paginas = $fpdi->setSourceFile(StreamReader::createByString($bytes));

            for ($n = 1; $n <= $paginas; $n++) {
                $plantilla = $fpdi->importPage($n);
                $tamano = $fpdi->getTemplateSize($plantilla);
                $fpdi->AddPage($tamano['orientation'], [$tamano['width'], $tamano['height']]);
                $fpdi->useTemplate($plantilla);
            }
        }

        return $fpdi->Output('S');
    }
}
