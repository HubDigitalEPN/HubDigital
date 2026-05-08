<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Presentation\Http\Controllers;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\CatalogoPublico\Application\UseCases\SincronizarEspecimenes\SincronizarEspecimenesHandler;
use Modules\CatalogoPublico\Application\UseCases\SincronizarEspecimenes\SincronizarEspecimenesInput;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Models\EspecimenDivulgableEloquentModel;
use Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Models\EspecimenEloquentModel;

#[Layout('layouts.app', params: ['title' => 'Sincronizar Especímenes'])]
final class SincronizarEspecimenes extends Component
{
    public int $paso = 1;

    /** @var string[] */
    public array $seleccionados = [];

    public ?string $especimenActivoId = null;

    /**
     * @var array<string, array<string, bool>>
     *                                         occurrenceID => [ campoVisible => bool, ... ]
     */
    public array $configuracionPorEspecimen = [];

    /** @var string[] */
    public array $occurrenceIDsActualizados = [];

    public bool $sincronizando = false;

    private const GRUPOS = [
        'Identificación' => [
            ['key' => 'occurrenceIDVisible', 'label' => 'occurrenceID', 'desc' => 'ID del registro', 'sensible' => false],
        ],
        'Taxonomía' => [
            ['key' => 'scientificNameVisible', 'label' => 'Nombre científico', 'desc' => 'Nombre taxonómico completo', 'sensible' => false],
            ['key' => 'familyVisible', 'label' => 'Familia', 'desc' => 'Familia taxonómica', 'sensible' => false],
            ['key' => 'genusVisible', 'label' => 'Género', 'desc' => 'Género taxonómico', 'sensible' => false],
        ],
        'Registro' => [
            ['key' => 'individualCountVisible', 'label' => 'Cantidad individuos', 'desc' => 'Número de individuos', 'sensible' => false],
            ['key' => 'typeStatusVisible', 'label' => 'Tipo de estatus', 'desc' => 'Estado del tipo nomenclatural', 'sensible' => false],
            ['key' => 'typeNotesVisible', 'label' => 'Notas de tipo', 'desc' => 'Anotaciones sobre el tipo', 'sensible' => true],
            ['key' => 'specimenNotesVisible', 'label' => 'Notas del espécimen', 'desc' => 'Observaciones del espécimen', 'sensible' => true],
            ['key' => 'occurrenceStatusVisible', 'label' => 'Estado de ocurrencia', 'desc' => 'Presente / Ausente', 'sensible' => false],
        ],
        'Recolección' => [
            ['key' => 'samplingProtocolVisible', 'label' => 'Protocolo de muestreo', 'desc' => 'Método de recolección', 'sensible' => false],
            ['key' => 'recordedByVisible', 'label' => 'Registrado por', 'desc' => 'Nombre del colector', 'sensible' => true],
        ],
        'Localización' => [
            ['key' => 'countryVisible', 'label' => 'País', 'desc' => 'País de origen', 'sensible' => false],
            ['key' => 'localityNameVisible', 'label' => 'Localidad', 'desc' => 'Nombre del sitio de colecta', 'sensible' => true],
            ['key' => 'decimalLatitudeVisible', 'label' => 'Latitud', 'desc' => 'Coordenada decimal', 'sensible' => true],
            ['key' => 'decimalLongitudeVisible', 'label' => 'Longitud', 'desc' => 'Coordenada decimal', 'sensible' => true],
        ],
    ];

    public function toggleSeleccion(string $occurrenceID): void
    {
        if (in_array($occurrenceID, $this->seleccionados, true)) {
            $this->seleccionados = array_values(
                array_filter($this->seleccionados, fn ($id) => $id !== $occurrenceID)
            );
            unset($this->configuracionPorEspecimen[$occurrenceID]);
            if ($this->especimenActivoId === $occurrenceID) {
                $this->especimenActivoId = null;
            }
        } else {
            $this->seleccionados[] = $occurrenceID;
            $this->inicializarConfiguracion($occurrenceID);
            if ($this->especimenActivoId === null) {
                $this->especimenActivoId = $occurrenceID;
            }
        }
    }

    public function seleccionarTodos(array $todosIds): void
    {
        foreach ($todosIds as $id) {
            if (! in_array($id, $this->seleccionados, true)) {
                $this->seleccionados[] = $id;
                $this->inicializarConfiguracion($id);
            }
        }
        if ($this->especimenActivoId === null && count($this->seleccionados) > 0) {
            $this->especimenActivoId = $this->seleccionados[0];
        }
    }

    public function deseleccionarTodos(): void
    {
        $this->seleccionados = [];
        $this->configuracionPorEspecimen = [];
        $this->especimenActivoId = null;
    }

    public function setEspecimenActivo(string $occurrenceID): void
    {
        if (in_array($occurrenceID, $this->seleccionados, true)) {
            $this->especimenActivoId = $occurrenceID;
        }
    }

    public function toggleCampo(string $occurrenceID, string $campo): void
    {
        if (isset($this->configuracionPorEspecimen[$occurrenceID][$campo])) {
            $this->configuracionPorEspecimen[$occurrenceID][$campo]
                = ! $this->configuracionPorEspecimen[$occurrenceID][$campo];
        }
    }

    public function habilitarTodo(string $occurrenceID): void
    {
        if (isset($this->configuracionPorEspecimen[$occurrenceID])) {
            foreach ($this->configuracionPorEspecimen[$occurrenceID] as $campo => $_) {
                $this->configuracionPorEspecimen[$occurrenceID][$campo] = true;
            }
        }
    }

    public function copiarConfigDeActivo(string $occurrenceIDDestino): void
    {
        if ($this->especimenActivoId === null
            || ! isset($this->configuracionPorEspecimen[$this->especimenActivoId])) {
            return;
        }
        $this->configuracionPorEspecimen[$occurrenceIDDestino]
            = $this->configuracionPorEspecimen[$this->especimenActivoId];
    }

    public function copiarConfigATodos(): void
    {
        if ($this->especimenActivoId === null
            || ! isset($this->configuracionPorEspecimen[$this->especimenActivoId])) {
            return;
        }
        foreach ($this->seleccionados as $id) {
            if ($id !== $this->especimenActivoId) {
                $this->configuracionPorEspecimen[$id]
                    = $this->configuracionPorEspecimen[$this->especimenActivoId];
            }
        }
    }

    public function avanzarPaso(): void
    {
        if ($this->paso === 1 && count($this->seleccionados) === 0) {
            return;
        }
        if ($this->paso < 3) {
            $this->paso++;
        }
    }

    public function retrocederPaso(): void
    {
        if ($this->paso > 1) {
            $this->paso--;
        }
    }

    public function sincronizar(SincronizarEspecimenesHandler $handler): void
    {
        $this->sincronizando = true;

        $especimenes = array_map(function (string $id): array {
            return [
                'occurrenceID' => $id,
                'configuracion' => $this->configuracionPorEspecimen[$id] ?? null,
            ];
        }, $this->seleccionados);

        $output = $handler->handle(new SincronizarEspecimenesInput(
            especimenes: $especimenes,
        ));

        $this->occurrenceIDsActualizados = $output->occurrenceIDsActualizados;
        $this->sincronizando = false;
        $this->paso = 3;
    }

    private function inicializarConfiguracion(string $occurrenceID): void
    {
        $existente = EspecimenDivulgableEloquentModel::where('occurrence_id', $occurrenceID)->first();

        if ($existente !== null) {
            $this->configuracionPorEspecimen[$occurrenceID] = [
                'occurrenceIDVisible' => (bool) $existente->occurrence_id_visible,
                'scientificNameVisible' => (bool) $existente->scientific_name_visible,
                'individualCountVisible' => (bool) $existente->individual_count_visible,
                'typeStatusVisible' => (bool) $existente->type_status_visible,
                'typeNotesVisible' => (bool) $existente->type_notes_visible,
                'specimenNotesVisible' => (bool) $existente->specimen_notes_visible,
                'occurrenceStatusVisible' => (bool) $existente->occurrence_status_visible,
                'samplingProtocolVisible' => (bool) $existente->sampling_protocol_visible,
                'recordedByVisible' => (bool) $existente->recorded_by_visible,
                'familyVisible' => (bool) $existente->family_visible,
                'genusVisible' => (bool) $existente->genus_visible,
                'countryVisible' => (bool) $existente->country_visible,
                'localityNameVisible' => (bool) $existente->locality_name_visible,
                'decimalLatitudeVisible' => (bool) $existente->decimal_latitude_visible,
                'decimalLongitudeVisible' => (bool) $existente->decimal_longitude_visible,
            ];
        } else {
            $campos = array_merge(...array_values(self::GRUPOS));
            $this->configuracionPorEspecimen[$occurrenceID] = array_fill_keys(
                array_column($campos, 'key'),
                true,
            );
        }
    }

    public function render(): View
    {
        $sincronizadosIds = EspecimenDivulgableEloquentModel::pluck('occurrence_id')->all();

        $especimenes = EspecimenEloquentModel::whereNotIn('occurrence_id', $sincronizadosIds)
            ->orderBy('occurrence_id')
            ->get();

        return view('catalogopublico::livewire.sincronizar-especimenes', [
            'especimenes' => $especimenes,
            'grupos' => self::GRUPOS,
        ]);
    }
}
