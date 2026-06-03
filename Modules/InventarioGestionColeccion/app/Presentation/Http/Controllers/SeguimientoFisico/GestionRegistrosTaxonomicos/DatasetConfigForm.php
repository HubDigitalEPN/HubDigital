<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\GestionRegistrosTaxonomicos;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfigurarDataset\ConfigurarDatasetHandler;
use Modules\InventarioGestionColeccion\Application\SeguimientoFisico\UseCases\ConfigurarDataset\ConfigurarDatasetInput;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\Repositories\DatasetConfigRepositoryInterface;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\BasisOfRecord;
use Modules\InventarioGestionColeccion\Presentation\Http\Controllers\SeguimientoFisico\Concerns\TraduceErroresPersistencia;

#[Layout('layouts.app', params: ['title' => 'Dataset GBIF'])]
final class DatasetConfigForm extends Component
{
    use TraduceErroresPersistencia;

    public bool $existeConfig = false;

    // Identificación del museo
    #[Rule('required|string|max:60')]
    public string $institutionCode = '';

    #[Rule('required|string|max:60')]
    public string $collectionCode = '';

    #[Rule('nullable|string|max:120')]
    public string $institutionId = '';

    #[Rule('nullable|string|max:120')]
    public string $collectionId = '';

    #[Rule('nullable|string|max:120')]
    public string $ownerInstitutionCode = '';

    // Calidad del registro
    #[Rule('required|string')]
    public string $basisOfRecord = 'PreservedSpecimen';

    // Licencia y derechos
    #[Rule('nullable|string|max:120')]
    public string $license = 'https://creativecommons.org/licenses/by-nc/4.0/';

    #[Rule('nullable|string|max:255')]
    public string $rightsHolder = '';

    #[Rule('nullable|string|max:500')]
    public string $accessRights = '';

    #[Rule('nullable|string|max:500')]
    public string $informationWithheld = '';

    // Metadatos EML
    #[Rule('nullable|string|max:255')]
    public string $datasetName = '';

    #[Rule('nullable|string|max:255')]
    public string $emlTitulo = '';

    #[Rule('nullable|string|max:500')]
    public string $emlContacto = '';

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(DatasetConfigRepositoryInterface $repo): void
    {
        $actual = $repo->obtenerActual();
        if ($actual === null) {
            $this->existeConfig = false;

            return;
        }

        $this->existeConfig = true;
        $this->institutionCode = $actual->institutionCode()->value;
        $this->collectionCode = $actual->collectionCode()->value;
        $this->institutionId = (string) ($actual->institutionId() ?? '');
        $this->collectionId = (string) ($actual->collectionId() ?? '');
        $this->ownerInstitutionCode = (string) ($actual->ownerInstitutionCode() ?? '');
        $this->basisOfRecord = $actual->basisOfRecord()->value;
        $this->license = (string) ($actual->license()?->value ?? '');
        $this->rightsHolder = (string) ($actual->rightsHolder() ?? '');
        $this->accessRights = (string) ($actual->accessRights() ?? '');
        $this->informationWithheld = (string) ($actual->informationWithheld() ?? '');
        $this->datasetName = (string) ($actual->datasetName() ?? '');
        $this->emlTitulo = (string) ($actual->emlTitulo() ?? '');
        $this->emlContacto = (string) ($actual->emlContacto() ?? '');
    }

    public function guardar(ConfigurarDatasetHandler $handler): void
    {
        $this->validate();

        try {
            $handler->handle(new ConfigurarDatasetInput(
                institutionCode: $this->institutionCode,
                collectionCode: $this->collectionCode,
                basisOfRecord: $this->basisOfRecord,
                institutionId: $this->nullable($this->institutionId),
                collectionId: $this->nullable($this->collectionId),
                datasetName: $this->nullable($this->datasetName),
                ownerInstitutionCode: $this->nullable($this->ownerInstitutionCode),
                rightsHolder: $this->nullable($this->rightsHolder),
                accessRights: $this->nullable($this->accessRights),
                license: $this->nullable($this->license),
                informationWithheld: $this->nullable($this->informationWithheld),
                emlContacto: $this->nullable($this->emlContacto),
                emlTitulo: $this->nullable($this->emlTitulo),
            ));

            $this->existeConfig = true;
            $this->successMessage = 'Configuración del dataset GBIF guardada.';
            $this->errorMessage = null;
        } catch (\Throwable $e) {
            $this->errorMessage = $this->traducirErrorParaUsuario($e);
        }
    }

    public function render(): View
    {
        return view('inventariogestioncoleccion::admin.taxonomia.dataset.config', [
            'basisOfRecords' => BasisOfRecord::cases(),
            'licenciasFrecuentes' => [
                ['value' => 'https://creativecommons.org/licenses/by/4.0/', 'label' => 'CC BY 4.0 — Atribución'],
                ['value' => 'https://creativecommons.org/licenses/by-nc/4.0/', 'label' => 'CC BY-NC 4.0 — Atribución no comercial'],
                ['value' => 'https://creativecommons.org/publicdomain/zero/1.0/', 'label' => 'CC0 1.0 — Dominio público'],
            ],
        ]);
    }

    private function nullable(string $valor): ?string
    {
        $v = trim($valor);

        return $v !== '' ? $v : null;
    }
}
