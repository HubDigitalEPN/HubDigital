<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Curador;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\GestionPrestamosRecepciones\Application\Ports\EsquemaMatrizPort;

#[Layout('layouts.portal', params: ['title' => 'Columnas de la matriz de depósito'])]
final class ConfiguracionMatrizDeposito extends Component
{
    public string $busqueda = '';
    public bool $editando = false;
    #[Locked]
    public ?string $campoAnterior = null;
    public array $columna = [];
    public string $aliasTexto = '';

    public function mount(): void
    {
        $this->autorizar();
    }

    public function editar(?string $campo = null): void
    {
        $this->autorizar();
        $columnas = app(EsquemaMatrizPort::class)->leer()['columnas'];
        $existente = $campo === null ? null : collect($columnas)->firstWhere('campo', $campo);
        abort_if($campo !== null && $existente === null, 404);
        $this->campoAnterior = $campo;
        $this->columna = $existente ?? ['campo' => '', 'cabecera' => '', 'alias' => [], 'requisito' => 'opcional',
            'descripcion' => '', 'formato' => 'texto', 'excepcion_campo' => '', 'excepcion_valor' => ''];
        $this->aliasTexto = implode(', ', $this->columna['alias']);
        $this->resetValidation();
        $this->editando = true;
    }

    public function guardar(): void
    {
        $this->autorizar();
        $this->validate([
            'columna.campo' => ['required', 'string', 'max:160'],
            'columna.cabecera' => ['required', 'string', 'max:160'],
            'columna.requisito' => ['required', 'in:obligatorio,recomendado,opcional,interno,predeterminado'],
            'columna.formato' => ['required', 'in:texto,fecha'],
            'columna.descripcion' => ['nullable', 'string', 'max:4000'],
            'columna.excepcion_campo' => ['nullable', 'string', 'max:160'],
            'columna.excepcion_valor' => ['nullable', 'string', 'max:160'],
            'aliasTexto' => ['nullable', 'string', 'max:2000'],
        ]);
        $columna = array_map(static fn ($v) => is_string($v) ? trim($v) : $v,
            array_intersect_key($this->columna, array_flip(['campo', 'cabecera', 'requisito', 'descripcion', 'formato', 'excepcion_campo', 'excepcion_valor'])));
        if ($this->campoAnterior !== null) $columna['campo'] = $this->campoAnterior;
        $columna['alias'] = array_values(array_unique(array_filter(array_map('trim', explode(',', $this->aliasTexto)))));
        try {
            app(EsquemaMatrizPort::class)->guardarColumna($columna, $this->campoAnterior, (string) auth()->id());
        } catch (\DomainException $error) {
            $this->dispatch('show-toast', message: $error->getMessage(), variant: 'error');
            return;
        }
        $this->editando = false;
        $this->dispatch('show-toast', message: 'Columna guardada. Las siguientes cargas usarán esta configuración.', variant: 'success');
    }

    private function autorizar(): void
    {
        $usuario = auth()->user();
        $usuario?->unsetRelation('roles');
        abort_unless($usuario?->esCurador(), 403);
    }

    public function render(): View
    {
        $this->autorizar();
        $columnas = app(EsquemaMatrizPort::class)->leer()['columnas'];
        $consulta = mb_strtolower(trim($this->busqueda));
        if ($consulta !== '') $columnas = array_values(array_filter($columnas, static fn ($c): bool => str_contains(mb_strtolower($c['campo'].' '.$c['cabecera'].' '.implode(' ', $c['alias']).' '.$c['requisito']), $consulta)));
        return view('gestionprestamosrecepciones::curador.configuracion-matriz', ['columnas' => $columnas]);
    }
}
