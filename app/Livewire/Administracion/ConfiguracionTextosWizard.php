<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Support\WizardCopy;
use App\Support\VistaPreviaWizard;
use App\Support\TextosSistema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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

    protected function camposSolicitudBloqueados(): bool { return false; }
    protected function solicitudR2Confirmada(): bool { return false; }
    protected function pdfSolicitudGenerado(): bool { return false; }
    protected function propiedadParaDocumento(string $nombre): string { return (new \Modules\GestionPrestamosRecepciones\Presentation\Http\Controllers\Investigador\RegistroSolicitudDeposito)->propiedadParaDocumento($nombre); }

    public function publicarTextoVisual(string $clave, string $textoVisible, string $textoNuevo): array
    {
        $literal = str_starts_with($clave, 'literal:');
        $original = $literal ? TextosSistema::normalizar(substr($clave, 8)) : config('wizard-copy.'.$clave);
        $id = $literal ? hash('sha256', $original) : $clave;
        if (!is_string($original) || ($literal && !isset(TextosSistema::catalogo()[$id]))) {
            return ['error' => 'Selecciona un texto editable del formulario.'];
        }

        $validacion = Validator::make(['texto' => $textoNuevo], ['texto' => ['required', 'string', 'max:1000', 'not_regex:/^\s*$/u']]);
        if ($validacion->fails()) {
            return ['error' => $validacion->errors()->first('texto')];
        }

        $textoNuevo = trim($textoNuevo);
        $tabla = $literal ? 'usuarios.textos_sistema' : 'usuarios.textos_wizard';
        $guardado = DB::transaction(function () use ($literal, $original, $id, $tabla, $textoVisible, $textoNuevo): bool {
            DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['wizard-editor:'.($literal ? 'sistema:' : '').$id]);
            $vigente = DB::table($tabla)->where('clave', $id)->value('contenido') ?? $original;
            if (!hash_equals($vigente, $textoVisible)) {
                return false;
            }

            $datos = ['contenido' => $textoNuevo, 'updated_at' => now(), ($literal ? 'actualizado_por' : 'updated_by') => auth()->id()];
            if ($literal) {
                $datos['original'] = $original;
            }
            DB::table($tabla)->updateOrInsert(['clave' => $id], $datos);

            return true;
        });
        if (!$guardado) {
            return ['error' => 'Otra persona cambió este texto. Recarga la página antes de volver a editar.'];
        }

        WizardCopy::forget();
        TextosSistema::olvidar();
        $this->mount();
        session()->flash('wizard-textos', 'Transacción Efectuada');

        return ['guardado' => true, 'texto' => $textoNuevo];
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
        session()->flash('wizard-textos', 'Transacción Efectuada');
    }

    public function render(): View
    {
        abort_unless(isset(VistaPreviaWizard::pasos()[$this->pasoEditor]),422);
        $originales = \Illuminate\Support\Arr::dot(config('wizard-copy', []));
        $mapa = array_replace($originales, array_intersect_key(WizardCopy::all(), $originales));
        foreach(TextosSistema::diccionario() as $original=>$contenido)$mapa['literal:'.$original]=$contenido;
        return view('livewire.administracion.configuracion-textos-wizard', [
            'grupos' => config('wizard-copy', []),
            'pasosEditor'=>VistaPreviaWizard::pasos(),
            'datosVista'=>VistaPreviaWizard::datos($this->pasoEditor,$this->modalidadEditor),
            'mapa'=>$mapa,
            'originales'=>$originales,
        ]);
    }
}
