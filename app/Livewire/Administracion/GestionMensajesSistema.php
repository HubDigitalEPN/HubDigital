<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Livewire\Concerns\WithContinuousGrid;
use App\Support\MensajesSistema;
use App\Support\PortalCopy;
use App\Support\TextosSistema;
use App\Support\WizardCopy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Mensajes del Sistema')]
final class GestionMensajesSistema extends Component
{
    use WithContinuousGrid;

    #[Locked] public ?string $editando = null;
    #[Locked] public string $filtro = '';

    public string $busqueda = '';
    public string $textoOriginal = '';
    public string $contenido = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->esAdministrador(), 403);
    }

    public function buscar(): void
    {
        $this->filtro = trim($this->busqueda);
    }

    public function restablecer(): void
    {
        $this->reset('busqueda', 'filtro');
    }

    public function nuevo(): void
    {
        $this->reset('editando', 'textoOriginal', 'contenido');
        $this->resetValidation();
        $this->modal('editor-mensaje-sistema')->show();
    }

    public function editar(string $clave): void
    {
        $original = $this->originalDe($clave);
        abort_if($original === null, 404);
        [$tabla, $id] = $this->destino($clave);
        $contenido = DB::table($tabla)->where('clave', $id)->value('contenido') ?? $original;

        $this->editando = $clave;
        $this->textoOriginal = $original;
        $this->contenido = $contenido;
        $this->resetValidation();
        $this->modal('editor-mensaje-sistema')->show();
    }

    public function guardar(): void
    {
        $this->contenido = trim($this->contenido);
        $reglas = ['contenido' => ['required', 'string', 'max:5000']];
        if ($this->editando === null) {
            $this->textoOriginal = TextosSistema::normalizar($this->textoOriginal);
            $reglas['textoOriginal'] = ['required', 'string', 'max:5000'];
        }
        $this->validate($reglas, [], ['textoOriginal' => 'texto original', 'contenido' => 'mensaje']);

        $original = $this->editando === null ? $this->textoOriginal : $this->originalDe($this->editando);
        abort_if($original === null, 404);
        $clave = $this->editando ?? 'literal.'.hash('sha256', $original);
        [$tabla, $id, $autor] = $this->destino($clave);
        $ahora = now();
        $valores = ['contenido' => $this->contenido, $autor => auth()->id(), 'updated_at' => $ahora];
        if ($tabla === 'usuarios.textos_sistema') {
            $valores['original'] = $original;
        }
        $nuevo = $valores + ['clave' => $id, 'created_at' => $ahora];

        $marcadores = MensajesSistema::marcadores($original);
        if (MensajesSistema::marcadores($this->contenido) !== $marcadores) {
            throw ValidationException::withMessages(['contenido' => $marcadores === []
                ? 'Este mensaje no admite marcadores dinámicos.'
                : 'Conserva estos marcadores: '.implode(', ', $marcadores).'.']);
        }

        if ($this->editando === null) {
            if (!DB::table($tabla)->insertOrIgnore($nuevo)) {
                throw ValidationException::withMessages(['textoOriginal' => 'Ya hay un mensaje para este texto original. Búscalo y elige Editar.']);
            }
        } else {
            DB::table($tabla)->upsert($nuevo, ['clave'], array_keys($valores));
        }

        $this->olvidar($tabla);
        $this->modal('editor-mensaje-sistema')->close();
        session()->flash('mensajes-guardados', 'Transacción Efectuada');
    }

    public function eliminar(string $clave): void
    {
        abort_if($this->originalDe($clave) === null, 404);
        [$tabla, $id] = $this->destino($clave);
        abort_unless(DB::table($tabla)->where('clave', $id)->delete(), 404);
        $this->olvidar($tabla);
        session()->flash('mensajes-guardados', 'Transacción Efectuada');
    }

    private function destino(string $clave): array
    {
        [$grupo, $id] = explode('.', $clave, 2);

        return match ($grupo) {
            'literal' => ['usuarios.textos_sistema', $id, 'actualizado_por'],
            'portal-copy' => ['usuarios.textos_portal', $id, 'updated_by'],
            'wizard-copy' => ['usuarios.textos_wizard', $id, 'updated_by'],
            default => ['usuarios.mensajes_sistema', $clave, 'actualizado_por'],
        };
    }

    private function originalDe(string $clave): ?string
    {
        if (!str_starts_with($clave, 'literal.')) {
            return MensajesSistema::definiciones()[$clave][1] ?? null;
        }

        $id = substr($clave, strlen('literal.'));
        return TextosSistema::catalogo()[$id]['original'] ?? DB::table('usuarios.textos_sistema')->where('clave', $id)->value('original');
    }

    private function olvidar(string $tabla): void
    {
        match ($tabla) {
            'usuarios.textos_sistema' => TextosSistema::olvidar(),
            'usuarios.textos_portal' => PortalCopy::forget(),
            'usuarios.textos_wizard' => WizardCopy::forget(),
            default => null,
        };
    }

    public function render(): View
    {
        $definiciones = MensajesSistema::definiciones();
        $guardados = MensajesSistema::consulta()->pluck('contenido', 'clave')->all();
        foreach (['portal-copy.' => PortalCopy::all(), 'wizard-copy.' => WizardCopy::all()] as $prefijo => $textos) {
            foreach ($textos as $clave => $texto) {
                $guardados[$prefijo.$clave] = $texto;
            }
        }
        $filas = [];
        foreach ($definiciones as $clave => [$uso, $original]) {
            $filas[$clave] = [
                'uso' => $uso,
                'original' => $original,
                'contenido' => $guardados[$clave] ?? $original,
                'estado' => isset($guardados[$clave]) ? 'Personalizado' : 'Original',
            ];
        }
        $literales = TextosSistema::diccionario();
        $registrados = array_fill_keys(array_map(static fn ($definicion) => TextosSistema::normalizar($definicion[1]), $definiciones), true);
        $inventario = TextosSistema::catalogo();
        foreach ($inventario as $id => $fila) {
            $original = $fila['original'];
            if (isset($registrados[$original]) && !isset($literales[$original])) {
                continue;
            }
            $filas['literal.'.$id] = [
                'uso' => implode(' · ', $fila['pantallas']),
                'original' => $original,
                'contenido' => $literales[$original] ?? $original,
                'estado' => isset($literales[$original]) ? 'Personalizado' : 'Original',
            ];
        }
        foreach ($literales as $original => $contenido) {
            $clave = 'literal.'.hash('sha256', $original);
            $filas[$clave] ??= ['uso' => 'Coincidencia de texto', 'original' => $original, 'contenido' => $contenido, 'estado' => 'Adicional'];
        }

        if ($this->filtro !== '') {
            $filas = array_filter($filas, fn (array $fila, string $clave): bool =>
                str_contains(mb_strtolower($clave.' '.implode(' ', $fila)), mb_strtolower($this->filtro)), ARRAY_FILTER_USE_BOTH);
        }

        $original = $this->editando === null ? null : $this->originalDe($this->editando);
        [$inicio, $cantidad] = $this->rangoGrillaContinua($this->filtro, count($filas), 25, 1);

        return view('livewire.administracion.gestion-mensajes-sistema', [
            'filas' => array_slice($filas, $inicio, $cantidad, true),
            'original' => $original,
            'marcadores' => $original === null ? [] : MensajesSistema::marcadores($original),
        ]);
    }
}
