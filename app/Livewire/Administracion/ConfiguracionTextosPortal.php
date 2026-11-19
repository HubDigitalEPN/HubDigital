<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Support\PortalCopy;
use App\Support\EditorPortal;
use App\Support\TextosSistema;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Textos del portal')]
final class ConfiguracionTextosPortal extends Component
{
    public array $textos = [];
    #[\Livewire\Attributes\Url] public string $grupo = 'inicio';
    #[\Livewire\Attributes\Locked] public string $claveSeleccionada = '';
    #[\Livewire\Attributes\Locked] public string $originalSeleccionado = '';
    #[\Livewire\Attributes\Locked] public string $versionTexto = '';
    public string $textoActual = '';
    public bool $editorAbierto = false;

    #[\Livewire\Attributes\Renderless]
    public function seleccionarTexto(string $clave): array
    {
        abort_unless(is_string(config('portal-copy.'.$clave)), 422);
        $this->resetValidation();
        $this->claveSeleccionada = $clave;
        $this->originalSeleccionado = config('portal-copy.'.$clave);
        $this->textoActual = PortalCopy::text($clave);
        $this->versionTexto = EditorPortal::version($clave);
        $this->editorAbierto = true;
        return ['original' => $this->originalSeleccionado, 'texto' => $this->textoActual];
    }

    #[\Livewire\Attributes\Renderless]
    public function seleccionarLiteral(string $original): array
    {
        $this->resetValidation();
        $original = TextosSistema::normalizar($original);
        $id = hash('sha256', $original);
        if (!isset(TextosSistema::catalogo()[$id])) {
            $this->addError('textoActual', 'Ese valor es un dato variable, no un texto editorial. Usa la lista de campos para editar el texto completo.');
            return ['error' => 'Ese valor es un dato variable. Selecciona un texto editorial o usa la lista de campos.'];
        }
        $pagina = EditorPortal::pagina($this->grupo);
        abort_unless(isset(EditorPortal::paginas()[$pagina]), 422);
        $this->claveSeleccionada = 'editorial.'.$pagina.'.'.$id;
        $this->originalSeleccionado = $original;
        $guardado = json_decode((string) DB::table('usuarios.textos_portal')->where('clave', $this->claveSeleccionada)->value('contenido'), true);
        $this->textoActual = $guardado['texto'] ?? $original;
        $this->versionTexto = EditorPortal::version($this->claveSeleccionada);
        $this->editorAbierto = true;
        return ['original' => $this->originalSeleccionado, 'texto' => $this->textoActual];
    }

    #[\Livewire\Attributes\Renderless]
    public function guardarDisposicion(array $grupos, string $version): string
    {
        return \App\Support\DisposicionContenido::guardar('portal:'.EditorPortal::pagina($this->grupo), $grupos, $version);
    }

    public function guardarTexto(): void
    {
        abort_unless($this->claveSeleccionada !== '', 422);
        $reglas = ['required', 'string', 'max:5000', 'not_regex:/^\s*$/u'];
        if (in_array($this->claveSeleccionada, ['contacto.email', 'depositos.entrega_contacto'], true)) $reglas[] = 'email';
        $this->validate(['textoActual' => $reglas]);
        $literal = str_starts_with($this->claveSeleccionada, 'editorial.');
        $contenido = $literal ? json_encode(['original' => $this->originalSeleccionado, 'texto' => trim($this->textoActual)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : trim($this->textoActual);
        EditorPortal::guardar($this->claveSeleccionada, $contenido, $this->versionTexto);
        if (!$literal) { [$grupo, $clave] = explode('.', $this->claveSeleccionada, 2); $this->textos[$grupo][$clave] = trim($this->textoActual); }
        $this->editorAbierto = false;
        $this->dispatch('portal-texto-publicado');
        session()->flash('portal-textos', 'Texto publicado. La vista muestra el contenido guardado en PostgreSQL.');
    }

    public function boot(): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
    }

    public function mount(): void
    {
        $guardados = PortalCopy::all();
        foreach (config('portal-copy', []) as $grupo => $campos) {
            foreach ($campos as $clave => $defecto) $this->textos[$grupo][$clave] = $guardados[$grupo.'.'.$clave] ?? $defecto;
        }
    }

    public function restaurar(string $grupo, string $clave): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
        $defecto = config('portal-copy.'.$grupo.'.'.$clave);
        abort_unless(is_string($defecto), 422);
        $this->textos[$grupo][$clave] = $defecto;
    }

    public function guardar(): void
    {
        abort_unless(auth()->user()?->esCurador(), 403);
        $reglas = ['textos' => ['required', 'array']];
        foreach (config('portal-copy', []) as $grupo => $campos) {
            foreach ($campos as $clave => $defecto) $reglas['textos.'.$grupo.'.'.$clave] = ['required', 'string', 'max:5000', 'not_regex:/^\s*$/u'];
        }
        $reglas['textos.contacto.email'][] = 'email';
        $reglas['textos.depositos.entrega_contacto'][] = 'email';
        $this->validate($reglas, ['*.required' => 'Completa el texto o restaura el original.', '*.max' => 'El texto admite hasta 5000 caracteres.']);

        DB::transaction(function (): void {
            foreach (config('portal-copy', []) as $grupo => $campos) {
                foreach ($campos as $clave => $defecto) {
                    DB::table('usuarios.textos_portal')->updateOrInsert(['clave' => $grupo.'.'.$clave], [
                        'contenido' => trim($this->textos[$grupo][$clave]), 'updated_by' => auth()->id(), 'updated_at' => now(),
                    ]);
                }
            }
        });
        PortalCopy::forget();
        session()->flash('portal-textos', 'Textos guardados. Se muestran en el portal al recargar la página.');
    }

    public function render(): View
    {
        $grupos = ['inicio' => 'Inicio', 'navegacion' => 'Cabecera y pie de página', 'contacto' => 'Contacto', 'depositos' => 'Portal de depósitos', 'catalogo' => 'Colección Biológica', 'importacion' => 'Importar y actualizar catálogo', 'privacidad' => 'Política de privacidad', 'terminos' => 'Términos de uso', 'licencia' => 'Licencia', 'citar' => 'Cómo citar', 'creditos' => 'Créditos y financiamiento', 'indicadores' => 'Explicaciones de indicadores', 'figuras' => 'Pies de figuras'];
        abort_unless(array_key_exists($this->grupo, $grupos), 422);
        $pagina = EditorPortal::pagina($this->grupo);
        $vista = isset(EditorPortal::paginas()[$pagina]) ? route(EditorPortal::paginas()[$pagina]) : null;
        $mapa = [];
        foreach (config('portal-copy', []) as $grupo => $campos) foreach ($campos as $clave => $defecto) $mapa[$grupo.'.'.$clave] = PortalCopy::text($grupo.'.'.$clave);
        foreach (PortalCopy::all() as $clave => $contenido) {
            if (!str_starts_with($clave, 'editorial.'.$pagina.'.')) continue;
            $dato = json_decode($contenido, true);
            if (is_array($dato) && is_string($dato['original'] ?? null) && is_string($dato['texto'] ?? null)) $mapa['literal:'.$dato['original']] = $dato['texto'];
        }
        return view('livewire.administracion.editor-visual-portal', ['grupos' => $grupos, 'campos' => config('portal-copy.'.$this->grupo, []), 'vista' => $vista, 'mapa' => $mapa]);
    }
}
