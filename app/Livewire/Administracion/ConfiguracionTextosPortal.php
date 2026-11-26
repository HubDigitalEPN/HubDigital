<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Support\PortalCopy;
use App\Support\EditorPortal;
use App\Support\TextosSistema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Textos del portal')]
final class ConfiguracionTextosPortal extends Component
{
    use \Livewire\WithFileUploads, \App\Livewire\Concerns\WithFotosEditoriales;
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
        $contenido = DB::table('usuarios.textos_portal')->where('clave', $clave)->value('contenido');
        $this->textoActual = $contenido ?? $this->originalSeleccionado;
        $this->versionTexto = hash('sha256', (string) $contenido);
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
            $this->addError('textoActual', 'Ese valor es un dato variable. Selecciona un texto editorial completo.');
            return ['error' => 'Ese valor es un dato variable. Selecciona un texto editorial completo.'];
        }
        $pagina = EditorPortal::pagina($this->grupo);
        abort_unless(isset(EditorPortal::paginas()[$pagina]), 422);
        $this->claveSeleccionada = 'editorial.'.$pagina.'.'.$id;
        $this->originalSeleccionado = $original;
        $contenido = DB::table('usuarios.textos_portal')->where('clave', $this->claveSeleccionada)->value('contenido');
        $guardado = json_decode((string) $contenido, true);
        $this->textoActual = $guardado['texto'] ?? $original;
        $this->versionTexto = hash('sha256', (string) $contenido);
        $this->editorAbierto = true;
        return ['original' => $this->originalSeleccionado, 'texto' => $this->textoActual];
    }

    #[\Livewire\Attributes\Renderless]
    public function publicarTextoVisual(string $clave, string $textoVisible, string $textoNuevo): array
    {
        $seleccion = str_starts_with($clave, 'literal:')
            ? $this->seleccionarLiteral(substr($clave, strlen('literal:')))
            : $this->seleccionarTexto($clave);

        if (isset($seleccion['error'])) {
            $this->editorAbierto = true;
            return ['error' => $seleccion['error']];
        }

        if (!hash_equals($seleccion['texto'], $textoVisible)) {
            return ['error' => 'Otra persona cambió este texto. Recarga la página antes de volver a editar.'];
        }

        $this->textoActual = $textoNuevo;

        try {
            $this->guardarTexto();
        } catch (ValidationException $error) {
            return ['error' => $error->errors()['textoActual'][0] ?? $error->getMessage()];
        }

        return ['guardado' => true];
    }

    #[\Livewire\Attributes\Renderless]
    public function guardarTexto(): void
    {
        abort_unless($this->claveSeleccionada !== '', 422);
        $reglas = ['required', 'string', 'max:5000', 'not_regex:/^\s*$/u'];
        if (in_array($this->claveSeleccionada, ['contacto.email', 'depositos.entrega_contacto'], true)) $reglas[] = 'email';
        $this->validate(['textoActual' => $reglas]);
        $literal = str_starts_with($this->claveSeleccionada, 'editorial.');
        $texto = trim($this->textoActual);
        $contenido = $literal ? json_encode(['original' => $this->originalSeleccionado, 'texto' => $texto], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : $texto;
        EditorPortal::guardar($this->claveSeleccionada, $contenido, $this->versionTexto);
        $this->textoActual = $texto;
        if (!$literal) { [$grupo, $clave] = explode('.', $this->claveSeleccionada, 2); $this->textos[$grupo][$clave] = $texto; }
        $this->editorAbierto = false;
        $this->dispatch('portal-texto-publicado');
        session()->flash('portal-textos', 'Transacción Efectuada');
    }

    public function boot(): void
    {
        app(\App\Support\AccesoMenu::class)->autorizar('admin.contenidos');
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
        abort_unless(app(\App\Support\AccesoMenu::class)->permite('admin.contenidos', 'editar'), 403);
        $defecto = config('portal-copy.'.$grupo.'.'.$clave);
        abort_unless(is_string($defecto), 422);
        $this->textos[$grupo][$clave] = $defecto;
    }

    public function guardar(): void
    {
        abort_unless(app(\App\Support\AccesoMenu::class)->permite('admin.contenidos', 'editar'), 403);
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
        session()->flash('portal-textos', 'Transacción Efectuada');
    }

    public function render(): View
    {
        $grupos = ['inicio' => 'Inicio', 'navegacion' => 'Cabecera y pie de página', 'contacto' => 'Contacto', 'depositos' => 'Portal de depósitos', 'catalogo' => 'Colección Biológica', 'importacion' => 'Importar y actualizar catálogo', 'privacidad' => 'Política de privacidad', 'terminos' => 'Términos de uso', 'licencia' => 'Licencia', 'citar' => 'Cómo citar', 'creditos' => 'Créditos y financiamiento', 'indicadores' => 'Explicaciones de indicadores', 'figuras' => 'Pies de figuras'];
        abort_unless(array_key_exists($this->grupo, $grupos), 422);
        $pagina = EditorPortal::pagina($this->grupo);
        $parametrosVista = $pagina === 'catalogo' ? ['vista' => 'mapa', '_editor' => '1'] : ['_editor' => '1'];
        $vista = isset(EditorPortal::paginas()[$pagina]) ? route(EditorPortal::paginas()[$pagina], $parametrosVista) : null;
        $mapa = [];
        $originales = [];
        foreach (config('portal-copy', []) as $grupo => $campos) {
            foreach ($campos as $clave => $defecto) {
                $originales[$grupo.'.'.$clave] = $defecto;
                $mapa[$grupo.'.'.$clave] = PortalCopy::text($grupo.'.'.$clave);
            }
        }
        foreach (PortalCopy::all() as $clave => $contenido) {
            if (!str_starts_with($clave, 'editorial.'.$pagina.'.')) continue;
            $dato = json_decode($contenido, true);
            if (is_array($dato) && is_string($dato['original'] ?? null) && is_string($dato['texto'] ?? null)) $mapa['literal:'.$dato['original']] = $dato['texto'];
        }
        $paginasEditor = [];
        foreach (EditorPortal::paginas() as $id => $ruta) {
            $paginasEditor[parse_url(route($ruta), PHP_URL_PATH) ?: '/'] = $id === 'laboratorio'
                ? route('admin.contenidos', ['seccion' => 'laboratorio']) : $id;
        }
        return view('livewire.administracion.editor-visual-portal', ['grupos' => $grupos, 'vista' => $vista, 'mapa' => $mapa, 'originales' => $originales, 'paginasEditor' => $paginasEditor]);
    }
}
