<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\GestionPrestamosRecepciones\Application\Ports\ValidacionFirmaElectronicaPort;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DetalleValidacionFirma;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\ResultadoValidacionFirma;
use Modules\GestionPrestamosRecepciones\Domain\Services\AnalizadorDocumentoAmbiental;
use Modules\GestionPrestamosRecepciones\Infrastructure\Adapters\LocalExtraccionDatosDocumentoAdapter;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\DirectorioTemporalHubDigital;
use Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito;
use Tests\DatabaseFeatureTestCase;

uses(DatabaseFeatureTestCase::class);

test('un permiso legible conserva texto clasificación y procedencia en cualquier plataforma', function (): void {
    $temporal = DirectorioTemporalHubDigital::crear('lectura-portable');
    $archivo = $temporal.DIRECTORY_SEPARATOR.'permiso.pdf';
    try {
        $pdf = new \setasign\Fpdi\Fpdi;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->MultiCell(0, 6, "AUTORIZACION DE RECOLECCION DE ESPECIMENES DE ESPECIES DE LA DIVERSIDAD BIOLOGICA\nMinisterio del Ambiente. Otorga la presente autorizacion de recoleccion a: Persona de prueba.\nNumero: 011-2025-ARVS-OTMO-DZ6-MAATE.\nVigencia de la autorizacion de recoleccion: 01/01/2025 hasta 31/12/2026.\nAREA GEOGRAFICA QUE CUBRE LA RECOLECCION: Cotopaxi.\nGRUPO BIOLOGICO A ESTUDIAR: Insectos.\nMETODOLOGIA PARA RECOLECTAR: red entomologica.");
        file_put_contents($archivo, $pdf->Output('S'));
        $lectura = app(LocalExtraccionDatosDocumentoAdapter::class)->leerArchivoLocal($archivo);
        $analisis = (new AnalizadorDocumentoAmbiental)->analizar($lectura['texto']);
        expect($analisis['tipo_detectado'])->toBe(AnalizadorDocumentoAmbiental::AUTORIZACION_RECOLECCION)
            ->and($lectura['procesamiento_parcial'])->toBeFalse()
            ->and($lectura['uso_ocr'])->toBeFalse()
            ->and($lectura['motor'])->toBe('java-pdfbox')
            ->and($lectura['paginas'][0]['metodo'])->toBe('java-pdfbox');
    } finally {
        DirectorioTemporalHubDigital::eliminar($temporal);
    }
});

test('confirmar contenido incierto conserva los mismos bytes y comprueba siempre su firma', function (ResultadoValidacionFirma $estado): void {
    configurarR2FalsoParaPruebas();
    config()->set('deposit-storage.signature_async', true);
    $nombre = 'Copia de la autorización de recolección (MAE)';
    $modelo = solicitudColaR2ParaPruebas();
    $modelo->forceFill(['documentos_requeridos' => [$nombre]])->save();
    $validador = new class($estado) implements ValidacionFirmaElectronicaPort
    {
        public int $llamadas = 0;
        public function __construct(private readonly ResultadoValidacionFirma $estado) {}
        public function verificarFirma(string $rutaAbsoluta): ResultadoValidacionFirma { $this->llamadas++; return $this->estado; }
        public function verificarFirmaDetallada(string $rutaFirmadaAbsoluta, string $rutaOriginalAbsoluta): DetalleValidacionFirma
        { throw new LogicException('No se compara con otro PDF.'); }
    };
    app()->instance(ValidacionFirmaElectronicaPort::class, $validador);
    $pdf = pdfValidoParaDepositos('Documento legible de formato desconocido para el clasificador.');
    $componente = Livewire::actingAs(User::findOrFail($modelo->investigador_id))
        ->test(RegistroSolicitudDeposito::class, ['id' => $modelo->id])
        ->set('archivoAutorizacionMae', UploadedFile::fake()->createWithContent('documento.pdf', $pdf));
    expect($validador->llamadas)->toBe(0)->and($modelo->fresh()->documentos_cargados ?? [])->not->toHaveKey($nombre);
    $componente->assertSet('confirmacionContenidoPendiente.nombre', $nombre)->call('confirmarCargaContenidoIncierto');
    $actual = $modelo->fresh();
    expect($validador->llamadas)->toBe(1)
        ->and($actual->firmas_electronicas[$nombre])->toBe($estado->value)
        ->and($actual->validacion_previa_documentos[$nombre]['sha256'])->toBe(hash('sha256', $pdf))
        ->and($actual->validacion_previa_documentos[$nombre]['tipo_detectado'])->toBe('desconocido')
        ->and($actual->validacion_previa_documentos[$nombre]['revision_contenido']['confirmada_por'])->toBe((string) $modelo->investigador_id);
    Http::assertNothingSent();
})->with([ResultadoValidacionFirma::Firmado, ResultadoValidacionFirma::SinFirma, ResultadoValidacionFirma::FirmaInvalida]);

test('un tipo distinto identificado no permite confirmar una carga incierta ni comprobar su firma', function (): void {
    configurarR2FalsoParaPruebas();
    config()->set('deposit-storage.signature_async', true);
    $nombre = 'Copia de la autorización de recolección (MAE)';
    $modelo = solicitudColaR2ParaPruebas();
    $modelo->forceFill(['documentos_requeridos' => [$nombre]])->save();
    $validador = Mockery::mock(ValidacionFirmaElectronicaPort::class);
    $validador->shouldNotReceive('verificarFirma');
    app()->instance(ValidacionFirmaElectronicaPort::class, $validador);
    $pdf = pdfValidoParaDepositos('GUIA DE MOVILIZACION DE VIDA SILVESTRE. Fecha de movilizacion: 09/05/2026. Valido hasta: 10/05/2026. Medio de transporte: terrestre.');
    Livewire::actingAs(User::findOrFail($modelo->investigador_id))->test(RegistroSolicitudDeposito::class, ['id' => $modelo->id])
        ->set('archivoAutorizacionMae', UploadedFile::fake()->createWithContent('autorizacion.pdf', $pdf))
        ->assertSet('confirmacionContenidoPendiente', null)
        ->assertSet('rechazosDocumentos.'.$nombre.'.estado', 'tipo_incorrecto');
    expect($modelo->fresh()->documentos_cargados ?? [])->not->toHaveKey($nombre);
    Http::assertNothingSent();
});

test('cancelar la confirmación no conserva archivos ni un consentimiento reutilizable', function (): void {
    configurarR2FalsoParaPruebas();
    config()->set('deposit-storage.signature_async', true);
    $nombre = 'Copia de la autorización de recolección (MAE)';
    $modelo = solicitudColaR2ParaPruebas();
    $modelo->forceFill(['documentos_requeridos' => [$nombre]])->save();
    Livewire::actingAs(User::findOrFail($modelo->investigador_id))->test(RegistroSolicitudDeposito::class, ['id' => $modelo->id])
        ->set('archivoAutorizacionMae', UploadedFile::fake()->createWithContent('documento.pdf', pdfValidoParaDepositos('Formato desconocido.')))
        ->call('cancelarCargaContenidoIncierto')->assertSet('confirmacionContenidoPendiente', null)->assertSet('archivoAutorizacionMae', null);
    expect($modelo->fresh()->documentos_cargados ?? [])->not->toHaveKey($nombre);
    Http::assertNothingSent();
});
