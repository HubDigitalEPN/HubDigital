<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Support\ConfiguracionIpt;
use DomainException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Importers\ConfiguracionPlantillaCatalogo;

#[Layout('layouts.app', params: ['title' => 'Formato IPT (GBIF)'])]
final class ConfiguracionFormatoIpt extends Component
{
    public array $columnas = [];
    #[Locked] public string $huella = '';
    public string $nuevoCampo = '';
    public string $mensaje = '';
    public function boot(): void { abort_unless(auth()->user()?->esAdministrador(), 403); }
    public function mount(ConfiguracionIpt $config): void
    {
        $def = $config->leer(); $this->columnas = $def['columnas']; $this->huella = ConfiguracionPlantillaCatalogo::huella($def);
    }
    public function agregar(): void
    {
        if (!in_array($this->nuevoCampo, ConfiguracionIpt::terminos(), true) || in_array($this->nuevoCampo, array_column($this->columnas, 'campo'), true)) { $this->addError('formato', 'Selecciona una columna que todavía no esté incluida.'); return; }
        $this->columnas[] = ['campo' => $this->nuevoCampo, 'origen' => $this->nuevoCampo, 'formato' => 'texto', 'ancho' => 10];
        $this->nuevoCampo = ''; $this->mensaje = '';
    }
    public function quitar(int $indice): void
    {
        if (!isset($this->columnas[$indice])) return;
        if (in_array($this->columnas[$indice]['campo'], ['occurrenceID', 'basisOfRecord', 'scientificName', 'eventDate'], true)) { $this->addError('formato', 'Esta columna es obligatoria para GBIF.'); return; }
        array_splice($this->columnas, $indice, 1); $this->mensaje = '';
    }
    public function mover(int $indice, int $direccion): void
    {
        if (!in_array($direccion, [-1, 1], true) || !isset($this->columnas[$indice], $this->columnas[$indice + $direccion])) return;
        [$this->columnas[$indice], $this->columnas[$indice + $direccion]] = [$this->columnas[$indice + $direccion], $this->columnas[$indice]];
        $this->mensaje = '';
    }
    public function restaurar(): void { $this->columnas = ConfiguracionIpt::predeterminada()['columnas']; $this->mensaje = 'Perfil de 69 columnas preparado. Guarda para aplicarlo.'; }
    public function guardar(ConfiguracionIpt $config): void
    {
        $this->resetErrorBag(); $def = ['columnas' => $this->columnas, 'fuente' => 'configuracion-administrador'];
        try { $config->guardar($def, $this->huella); $this->huella = ConfiguracionPlantillaCatalogo::huella($def); $this->mensaje = 'Formato guardado. Las próximas descargas IPT usarán estas columnas, este orden y estos formatos.'; }
        catch (DomainException $e) { $this->addError('formato', $e->getMessage()); }
    }
    public function render()
    {
        return view('livewire.administracion.configuracion-formato-ipt', ['terminos' => ConfiguracionIpt::terminos(), 'origenes' => app(ConfiguracionIpt::class)->origenes(), 'formatos' => ConfiguracionIpt::FORMATOS]);
    }
}
