<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Support\WizardCopy;
use App\Support\VistaPreviaWizard;
use App\Support\TextosSistema;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Textos del asistente de depositos')]
final class ConfiguracionTextosWizard extends Component
{
    /** @var array<string, string> */
    public array $textos = [];
    #[\Livewire\Attributes\Url] public int $pasoEditor = 0;
    public string $modalidadEditor = 'Depósito';
    #[\Livewire\Attributes\Locked] public string $claveSeleccionada = '';
    #[\Livewire\Attributes\Locked] public string $originalSeleccionado = '';
    #[\Livewire\Attributes\Locked] public string $versionTexto = '';
    public string $textoActual = '';
    public bool $editorAbierto = false;

    #[\Livewire\Attributes\Renderless]
    public function guardarDisposicion(array $grupos, string $version): string
    {
        return \App\Support\DisposicionContenido::guardar('wizard:'.$this->pasoEditor, $grupos, $version);
    }

    protected function camposSolicitudBloqueados(): bool { return false; }
    protected function solicitudR2Confirmada(): bool { return false; }
    protected function pdfSolicitudGenerado(): bool { return false; }
    protected function propiedadParaDocumento(string $nombre): string { return (new \Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito)->propiedadParaDocumento($nombre); }

    #[\Livewire\Attributes\Renderless]
    public function seleccionarTexto(string $clave): array
    {
        abort_unless(is_string(config('wizard-copy.'.$clave)),422);
        $this->claveSeleccionada=$clave;
        $this->originalSeleccionado=config('wizard-copy.'.$clave);
        $this->textoActual=WizardCopy::text($clave);
        $this->versionTexto=$this->versionSeleccion();$this->resetValidation();$this->editorAbierto=true;
        return ['original' => $this->originalSeleccionado, 'texto' => $this->textoActual];
    }

    #[\Livewire\Attributes\Renderless]
    public function seleccionarLiteral(string $texto): array
    {
        $texto=TextosSistema::normalizar($texto);
        abort_unless(isset(TextosSistema::catalogo()[hash('sha256',$texto)]),422);
        $this->claveSeleccionada='sistema:'.hash('sha256',$texto);
        $this->originalSeleccionado=$texto;
        $this->textoActual=TextosSistema::diccionario()[$texto]??$texto;
        $this->versionTexto=$this->versionSeleccion();$this->resetValidation();$this->editorAbierto=true;
        return ['original' => $this->originalSeleccionado, 'texto' => $this->textoActual];
    }

    private function tablaSeleccion(): string { return str_starts_with($this->claveSeleccionada,'sistema:')?'usuarios.textos_sistema':'usuarios.textos_wizard'; }
    private function idSeleccion(): string { return str_starts_with($this->claveSeleccionada,'sistema:')?substr($this->claveSeleccionada,8):$this->claveSeleccionada; }
    private function versionSeleccion(): string { return hash('sha256',json_encode(DB::table($this->tablaSeleccion())->where('clave',$this->idSeleccion())->first())); }

    public function guardarSeleccion(): void
    {
        abort_if($this->claveSeleccionada==='',422);
        $this->validate(['textoActual'=>['required','string','max:1000','not_regex:/^\s*$/u']]);
        DB::transaction(function(): void {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))',['wizard-editor:'.$this->claveSeleccionada]);
            if(!hash_equals($this->versionTexto,$this->versionSeleccion()))throw \Illuminate\Validation\ValidationException::withMessages(['textoActual'=>'Otra persona cambió este texto. Cierra y vuelve a seleccionarlo para ver la versión actual.']);
            $sistema=str_starts_with($this->claveSeleccionada,'sistema:');
            $datos=['contenido'=>trim($this->textoActual),'updated_at'=>now(),($sistema?'actualizado_por':'updated_by')=>auth()->id()];
            if($sistema)$datos['original']=$this->originalSeleccionado;
            DB::table($this->tablaSeleccion())->updateOrInsert(['clave'=>$this->idSeleccion()],$datos);
        });
        WizardCopy::forget();TextosSistema::olvidar();$this->mount();$this->editorAbierto=false;
        $this->dispatch('wizard-texto-publicado');session()->flash('wizard-textos','Texto publicado. La vista muestra el contenido guardado.');
    }

    public function boot(): void
    {
        abort_unless(auth()->user()?->esAdministrador(), 403);
    }

    public function mount(): void
    {
        $guardados = WizardCopy::all();
        foreach (config('wizard-copy', []) as $grupo => $campos) {
            foreach ($campos as $nombre => $defecto) {
                $clave = $grupo.'.'.$nombre;
                $this->textos[$grupo][$nombre] = $guardados[$clave] ?? $defecto;
            }
        }
    }

    public function guardar(): void
    {
        abort_unless(auth()->user()?->esAdministrador(), 403);
        $this->validate(['textos' => ['required', 'array'], 'textos.*' => ['required', 'array'], 'textos.*.*' => ['required', 'string', 'max:1000']]);

        DB::transaction(function (): void {
            foreach (config('wizard-copy', []) as $grupo => $campos) {
                foreach ($campos as $nombre => $defecto) {
                    $clave = $grupo.'.'.$nombre;
                    DB::table('usuarios.textos_wizard')->updateOrInsert(
                        ['clave' => $clave],
                        [
                            'contenido' => trim($this->textos[$grupo][$nombre] ?? $defecto),
                            'updated_by' => auth()->id(),
                            'updated_at' => now(),
                        ],
                    );
                }
            }
        });

        WizardCopy::forget();
        session()->flash('wizard-textos', 'Textos del asistente actualizados.');
    }

    public function render(): View
    {
        abort_unless(isset(VistaPreviaWizard::pasos()[$this->pasoEditor]),422);
        $mapa=[];foreach(config('wizard-copy',[]) as $grupo=>$campos)foreach($campos as $clave=>$_)$mapa[$grupo.'.'.$clave]=WizardCopy::text($grupo.'.'.$clave);
        foreach(TextosSistema::diccionario() as $original=>$contenido)$mapa['literal:'.$original]=$contenido;
        return view('livewire.administracion.configuracion-textos-wizard', [
            'grupos' => config('wizard-copy', []),
            'pasosEditor'=>VistaPreviaWizard::pasos(),
            'datosVista'=>VistaPreviaWizard::datos($this->pasoEditor,$this->modalidadEditor),
            'mapa'=>$mapa,
        ]);
    }
}
