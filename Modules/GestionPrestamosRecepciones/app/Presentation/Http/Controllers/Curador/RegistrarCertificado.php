<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use App\Concerns\HandlesDomainExceptions;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarCertificadoCurador\RegistrarCertificadoCuradorHandler;
use Modules\GestionPrestamosRecepciones\Application\UseCases\RegistrarCertificadoCurador\RegistrarCertificadoCuradorInput;

/**
 * Componente Livewire donde el curador registra su certificado de firma electrónica
 * (.p12) una sola vez, para que las actas se firmen automáticamente al aprobarlas.
 */
#[Layout('layouts.app', params: ['title' => 'Certificado de firma'])]
final class RegistrarCertificado extends Component
{
    use HandlesDomainExceptions;
    use WithFileUploads;

    /** @var TemporaryUploadedFile|null */
    #[Validate('required|file|max:5120')]
    public $certificado = null;

    #[Validate('required|string')]
    public string $contrasenia = '';

    public string $successMessage = '';

    public function registrar(RegistrarCertificadoCuradorHandler $handler): void
    {
        $this->validate();

        $extension = strtolower($this->certificado->getClientOriginalExtension());
        if (! in_array($extension, ['p12', 'pfx'], true)) {
            $this->addError('certificado', 'El archivo debe ser un certificado .p12 o .pfx.');

            return;
        }

        $salida = $handler->handle(new RegistrarCertificadoCuradorInput(
            curadorId: (string) auth()->id(),
            contenidoP12: $this->certificado->get(),
            contrasenia: $this->contrasenia,
        ));

        $this->reset('certificado', 'contrasenia');
        $this->successMessage = 'Certificado registrado: '.$salida->commonName.
            ', válido hasta '.$salida->validoHasta->format('d/m/Y').'.';
    }

    public function render(): View
    {
        return view('gestionprestamosrecepciones::curador.registrar-certificado');
    }
}
