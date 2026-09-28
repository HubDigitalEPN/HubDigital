<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Modules\GestionPrestamosRecepciones\Application\Ports\ExtraccionDatosDocumentoPort;
use Modules\GestionPrestamosRecepciones\Domain\ValueObjects\DatosIntegradosDocumento;
use Modules\GestionPrestamosRecepciones\Infrastructure\Jobs\VerificarFirmaDocumentoJob;
use Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Models\SolicitudDepositoEloquentModel;
use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

function crearSolicitudApiDe(User $depositante, string $numero): SolicitudDepositoEloquentModel
{
    return SolicitudDepositoEloquentModel::query()->create([
        'id' => (string) Str::uuid(),
        'numero' => $numero,
        'investigador_id' => (string) $depositante->getKey(),
        'tipo_tramite' => 'Depósito',
        'estado' => 'En Borrador',
        'provincia_origen' => 'Pichincha',
        'documentos_requeridos' => ['Copia del permiso de movilización'],
        'documentos_adjuntos' => [],
        'datos_faltantes' => [],
    ]);
}

test('la API rechaza rutas aportadas por el cliente antes de invocar el extractor', function (): void {
    $depositante = User::factory()->depositante()->create();
    $solicitud = crearSolicitudApiDe($depositante, 'DEP-000001');
    $extractor = new class implements ExtraccionDatosDocumentoPort
    {
        public bool $invocado = false;

        public function extraerDatos(array $documentos): DatosIntegradosDocumento
        {
            $this->invocado = true;

            throw new RuntimeException('El extractor no debía recibir rutas del cliente.');
        }
    };
    app()->instance(ExtraccionDatosDocumentoPort::class, $extractor);
    Sanctum::actingAs($depositante, ['depositos:gestionar']);

    $this->postJson("/api/v1/solicitudes-deposito/{$solicitud->id}/documentacion-oficial", [
        'documentos' => [
            'Copia del permiso de movilización' => 'depositos/expediente-ajeno/documento.pdf',
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('documentos.Copia del permiso de movilización');

    expect($extractor->invocado)->toBeFalse();
});

test('la API oculta un expediente ajeno aunque se envíe un archivo válido', function (): void {
    $propietario = User::factory()->depositante()->create();
    $intruso = User::factory()->depositante()->create();
    $solicitud = crearSolicitudApiDe($propietario, 'DEP-000002');
    Sanctum::actingAs($intruso, ['depositos:gestionar']);

    $this->post("/api/v1/solicitudes-deposito/{$solicitud->id}/documentacion-oficial", [
        'documentos' => [
            'Copia del permiso de movilización' => UploadedFile::fake()->createWithContent('guia.pdf', "%PDF-1.7\ncontenido ajeno"),
        ],
    ], ['Accept' => 'application/json'])->assertNotFound();
});

test('la API guarda archivos con claves privadas server-side y nunca autoavanza la solicitud', function (): void {
    configurarR2FalsoParaPruebas();
    Queue::fake([VerificarFirmaDocumentoJob::class]);

    $depositante = User::factory()->depositante()->create();
    $solicitud = crearSolicitudApiDe($depositante, 'MEPN-INV-DEP-00003');
    $extractor = new class implements ExtraccionDatosDocumentoPort
    {
        /** @var array<string, string> */
        public array $rutas = [];

        public function extraerDatos(array $documentos): DatosIntegradosDocumento
        {
            $this->rutas = $documentos;

            return new DatosIntegradosDocumento(
                nroPermisoRecoleccion: 'MAATE-2026-001',
                nroPermisoMovilizacion: 'GUIA-2026-001',
                grupoAnimal: 'Insecta',
                provinciaOrigen: 'Pichincha',
                localidad: 'Quito',
                origenDonacion: null,
                nombreInvestigador: 'Consultora Prueba',
                nroIndividuos: '10',
                nroMorfoespecies: '2',
                nroLotes: '1',
            );
        }
    };
    app()->instance(ExtraccionDatosDocumentoPort::class, $extractor);
    Sanctum::actingAs($depositante, ['depositos:gestionar']);

    $respuesta = $this->post("/api/v1/solicitudes-deposito/{$solicitud->id}/documentacion-oficial", [
        'documentos' => [
            'Copia del permiso de movilización' => UploadedFile::fake()->createWithContent(
                'nombre-controlado-por-cliente.pdf',
                pdfValidoParaDepositos('Guia de movilizacion de especimenes. Fecha de movilizacion: 28/09/2026. Lugar de origen: Reserva. Lugar de destino: Museo. Medio de transporte: vehiculo. Ministerio del Ambiente, Agua y Transicion Ecologica.'),
            ),
        ],
    ], ['Accept' => 'application/json']);

    $respuesta->assertOk()->assertJsonPath('data.estado', 'En Borrador');
    expect($extractor->rutas)->toHaveCount(1);
    foreach ($extractor->rutas as $rutaPrivada) {
        expect(app(AlmacenamientoDepositos::class)->existe($rutaPrivada))->toBeTrue();
    }
    Queue::assertPushed(VerificarFirmaDocumentoJob::class, 1);

    $persistida = $solicitud->fresh();
    expect($persistida->estado)->toBe('En Borrador')
        ->and($persistida->documentos_adjuntos)->toHaveCount(1);

    $rutaPrivada = $persistida->documentos_adjuntos[0]['ruta'];
    expect($rutaPrivada)->toStartWith("depositos/{$solicitud->id}/documentos-api/");
    expect($rutaPrivada)->not->toContain('nombre-controlado-por-cliente');
    expect(app(\Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos::class)->existe($rutaPrivada))->toBeTrue();
});
