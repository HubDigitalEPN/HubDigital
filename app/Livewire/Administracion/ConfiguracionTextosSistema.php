<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Support\TextosSistema;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app', params: ['title' => 'Textos del sistema'])]
final class ConfiguracionTextosSistema extends Component
{
    public string $busqueda = '';
    public string $pantalla = '';
    public int $pagina = 1;
    #[Locked] public string $original = '';
    public string $contenido = '';
    public string $textoAdicional = '';

    public function boot(): void { abort_unless(auth()->user()?->esAdministrador(), 403); }
    public function updatedBusqueda(): void { $this->pagina = 1; }
    public function updatedPantalla(): void { $this->pagina = 1; }
    public function cancelar(): void { $this->original = ''; $this->contenido = ''; }
    public function editar(string $clave): void
    {
        $catalogo = TextosSistema::catalogo();
        $guardado = DB::table('usuarios.textos_sistema')->where('clave', $clave)->first();
        $this->original = $catalogo[$clave]['original'] ?? $guardado?->original ?? '';
        abort_if($this->original === '', 404);
        $this->contenido = $guardado?->contenido ?? $this->original;
    }
    public function agregar(): void
    {
        $this->validate(['textoAdicional' => ['required', 'string', 'max:5000']]);
        $this->original = TextosSistema::normalizar($this->textoAdicional);
        $this->contenido = TextosSistema::diccionario()[$this->original] ?? $this->original;
    }
    public function guardar(): void
    {
        abort_unless(auth()->user()?->esAdministrador(), 403);
        $this->validate(['original' => ['required', 'string', 'max:5000'], 'contenido' => ['required', 'string', 'max:5000', 'not_regex:/^\s*$/u']]);
        DB::table('usuarios.textos_sistema')->updateOrInsert(['clave' => hash('sha256', $this->original)], [
            'original' => $this->original, 'contenido' => trim($this->contenido), 'actualizado_por' => auth()->id(), 'updated_at' => now(),
        ]);
        TextosSistema::olvidar();
        session()->flash('textos-sistema', 'Texto guardado. Se aplica al recargar las pantallas.');
        $this->original = '';
    }
    public function restaurar(): void
    {
        abort_unless(auth()->user()?->esAdministrador(), 403);
        DB::table('usuarios.textos_sistema')->where('clave', hash('sha256', $this->original))->delete();
        TextosSistema::olvidar(); $this->original = '';
    }
    public function render()
    {
        $catalogo = TextosSistema::catalogo(); $guardados = TextosSistema::diccionario();
        foreach ($guardados as $original => $_) $catalogo[hash('sha256', $original)] ??= ['original' => $original, 'pantallas' => ['Adicional' => 'Adicional']];
        $pantallas = [];
        foreach ($catalogo as $fila) foreach ($fila['pantallas'] as $pantalla) $pantallas[$pantalla] = $pantalla;
        ksort($pantallas);
        $filas = array_filter($catalogo, fn ($fila) => ($this->pantalla === '' || in_array($this->pantalla, $fila['pantallas'], true))
            && ($this->busqueda === '' || str_contains(mb_strtolower($fila['original'].' '.($guardados[$fila['original']] ?? '')), mb_strtolower($this->busqueda))));
        $ultima = max(1, (int) ceil(count($filas) / 40)); $this->pagina = max(1, min($ultima, $this->pagina));
        return view('livewire.administracion.configuracion-textos-sistema', ['filas' => array_slice($filas, ($this->pagina-1)*40, 40, true), 'pantallas' => $pantallas, 'guardados' => $guardados, 'ultima' => $ultima]);
    }
}
