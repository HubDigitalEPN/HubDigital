<?php

declare(strict_types=1);

namespace App\Livewire\Administracion;

use App\Livewire\Concerns\WithInteractiveGrid;

use App\Enums\RolUsuario;
use App\Models\User;
use App\Support\Administracion\CreadorUsuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Administración de usuarios')]
final class GestionUsuarios extends Component
{
    use WithInteractiveGrid;
    use WithPagination;

    public string $busqueda = '';

    public string $filtroRol = '';

    public bool $mostrarFormulario = false;

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $rol = RolUsuario::DEPOSITANTE->value;

    public string $cargo = '';

    public string $institucion = '';

    public ?string $usuarioEnEdicion = null;

    public string $edicionFirstName = '';

    public string $edicionEmail = '';

    public ?string $usuarioPorEliminar = null;

    public string $edicionLastName = '';

    public string $edicionRol = '';

    /** @var list<string> */
    public array $edicionRoles = [];

    public string $edicionCargo = '';

    public string $edicionInstitucion = '';

    public function boot(): void
    {
        request()->attributes->set('administracion_sensible', true);
        $this->autorizarAdministracion();
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroRol(): void
    {
        $this->resetPage();
    }

    public function alternarFormulario(): void
    {
        $this->mostrarFormulario = ! $this->mostrarFormulario;
        $this->resetValidation();
    }

    public function crear(
        CreadorUsuario $creador,
        #[\SensitiveParameter] string $password,
        #[\SensitiveParameter] string $passwordConfirmation,
    ): void {
        $this->autorizarAdministracion();
        $this->email = User::normalizarEmail($this->email);
        $this->first_name = trim($this->first_name);
        $this->last_name = trim($this->last_name);

        $datos = Validator::make([
            ...$this->only([
                'first_name',
                'last_name',
                'email',
                'rol',
                'cargo',
                'institucion',
            ]),
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique(User::class, 'email_normalizado')],
            'password' => ['required', 'string', 'max:72', Password::min(12)->mixedCase()->numbers()->symbols(), 'confirmed'],
            'rol' => ['required', Rule::enum(RolUsuario::class)],
            'cargo' => [Rule::requiredIf($this->rol === RolUsuario::DEPOSITANTE->value), 'nullable', 'string', 'max:255'],
            'institucion' => [Rule::requiredIf($this->rol === RolUsuario::DEPOSITANTE->value), 'nullable', 'string', 'max:255'],
        ])->validate();

        $creador->crear($datos, RolUsuario::from($datos['rol']), creadoPorAdministrador: true);

        $this->reset([
            'first_name',
            'last_name',
            'email',
            'cargo',
            'institucion',
        ]);
        $this->rol = RolUsuario::DEPOSITANTE->value;
        $this->mostrarFormulario = false;
        $this->resetPage();
        session()->flash('usuario-creado', 'Cuenta creada y habilitada. Al iniciar sesion debera cambiar su contrasena inicial.');
    }

    public function editar(string $usuarioId): void
    {
        $this->autorizarAdministracion();

        $usuario = User::query()->with('roles')->findOrFail($usuarioId);

        $this->usuarioEnEdicion = $usuario->getKey();
        $this->edicionFirstName = $usuario->first_name;
        $this->edicionLastName = $usuario->last_name;
        $this->edicionEmail = $usuario->email;
        $this->edicionRol = $usuario->rol->value;
        $this->edicionRoles = $usuario->rolesAsignados()
            ->map(fn (RolUsuario $rol): string => $rol->value)
            ->values()
            ->all();
        $this->edicionCargo = $usuario->cargo ?? '';
        $this->edicionInstitucion = $usuario->institucion ?? '';
        $this->resetValidation();
    }

    public function cancelarEdicion(): void
    {
        $this->resetEdicion();
    }

    public function actualizar(): void
    {
        $this->autorizarAdministracion();

        $usuario = User::query()->with('roles')->findOrFail($this->usuarioEnEdicion);
        $rol = RolUsuario::tryFrom($this->edicionRol);
        $rolesSeleccionados = collect($this->edicionRoles)
            ->map(static fn (mixed $valor): ?RolUsuario => is_string($valor) ? RolUsuario::tryFrom($valor) : null)
            ->filter()
            ->values();

        $datos = Validator::make([
            'first_name' => trim($this->edicionFirstName),
            'last_name' => trim($this->edicionLastName),
            'email' => User::normalizarEmail($this->edicionEmail),
            'rol' => $this->edicionRol,
            'roles' => $rolesSeleccionados->map(fn (RolUsuario $rol): string => $rol->value)->all(),
            'cargo' => trim($this->edicionCargo),
            'institucion' => trim($this->edicionInstitucion),
        ], [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique(User::class, 'email_normalizado')->ignore($usuario->id)],
            'rol' => ['required', Rule::enum(RolUsuario::class)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::enum(RolUsuario::class)],
            'cargo' => [Rule::requiredIf($rolesSeleccionados->contains(RolUsuario::DEPOSITANTE)), 'nullable', 'string', 'max:255'],
            'institucion' => [Rule::requiredIf($rolesSeleccionados->contains(RolUsuario::DEPOSITANTE)), 'nullable', 'string', 'max:255'],
        ])->validate();

        if ($rol === null || ! $rolesSeleccionados->contains($rol)) {
            throw ValidationException::withMessages([
                'edicionRol' => 'El rol operativo principal debe formar parte de los roles asignados.',
            ]);
        }

        $internos = $rolesSeleccionados->filter(
            fn (RolUsuario $asignado): bool => in_array($asignado, RolUsuario::rolesInternos(), true),
        );
        if ($internos->count() > 1 || ($internos->isNotEmpty() && $rolesSeleccionados->count() > 1)) {
            throw ValidationException::withMessages([
                'edicionRoles' => 'Un perfil interno de la EPN no puede combinarse con otros roles.',
            ]);
        }

        if ($rolesSeleccionados->isEmpty()) {
            return;
        }

        if ($internos->isNotEmpty() && ! $this->esCorreoInstitucional($datos['email'])) {
            throw ValidationException::withMessages([
                'edicionRol' => 'Los roles internos requieren un correo institucional autorizado.',
            ]);
        }

        if ($usuario->is(auth()->user()) && ! $rolesSeleccionados->contains(RolUsuario::ADMIN)) {
            throw ValidationException::withMessages([
                'edicionRol' => 'No puedes retirar tu propio rol de administración.',
            ]);
        }

        DB::transaction(function () use ($usuario, $datos, $rol, $rolesSeleccionados): void {
            $usuario->fill([
                'first_name' => $datos['first_name'],
                'last_name' => $datos['last_name'],
                'email' => $datos['email'],
                'rol' => $rol,
                'cargo' => $this->valorOpcional($datos['cargo']),
                'institucion' => $this->valorOpcional($datos['institucion']),
            ])->save();
            $usuario->sincronizarRoles($rolesSeleccionados->all(), $rol);
        });

        $this->resetEdicion();
        session()->flash('usuario-actualizado', 'Perfil y rol actualizados.');
    }

    public function confirmarEliminacion(string $usuarioId): void
    {
        $this->autorizarAdministracion();
        $usuario = User::query()->findOrFail($usuarioId);
        abort_if($usuario->is(auth()->user()), 403);
        $this->usuarioPorEliminar = $usuario->id;
    }

    public function cancelarEliminacion(): void
    {
        $this->usuarioPorEliminar = null;
    }

    public function eliminarDefinitivamente(): void
    {
        $this->autorizarAdministracion();
        $usuario = User::query()->findOrFail($this->usuarioPorEliminar);
        abort_if($usuario->is(auth()->user()), 403);

        if ($usuario->esAdministrador() && User::query()
            ->whereHas('roles', fn ($roles) => $roles->where('rol', RolUsuario::ADMIN->value))
            ->count() <= 1) {
            throw ValidationException::withMessages([
                'usuarioPorEliminar' => 'Debe existir al menos otra cuenta administradora.',
            ]);
        }

        if (DB::table('recepciones.solicitudes_deposito')
            ->where('investigador_id', $usuario->id)->exists()) {
            throw ValidationException::withMessages([
                'usuarioPorEliminar' => 'Esta cuenta tiene depositos registrados. Su historial y los objetos cargados en R2 deben conservar su responsable.',
            ]);
        }

        if (DB::table('prestamos.solicitudes_prestamo')
            ->where('investigador_id', $usuario->id)->exists()
            || DB::table('prestamos.prestamos')
                ->where('investigador_id', $usuario->id)->exists()) {
            throw ValidationException::withMessages([
                'usuarioPorEliminar' => 'Esta cuenta tiene prestamos historicos y no se puede eliminar sin perder la trazabilidad.',
            ]);
        }

        try {
            DB::transaction(function () use ($usuario): void {
                $usuario->tokens()->delete();
                DB::table((string) config('session.table', 'sessions'))
                    ->where('user_id', $usuario->id)
                    ->delete();
                $usuario->delete();
            });
        } catch (\Illuminate\Database\QueryException) {
            throw ValidationException::withMessages([
                'usuarioPorEliminar' => 'La cuenta tiene registros vinculados que impiden su eliminacion. Revisa sus expedientes antes de eliminarla.',
            ]);
        }

        $this->usuarioPorEliminar = null;
        $this->resetPage();
        session()->flash('usuario-actualizado', 'La cuenta fue eliminada definitivamente.');
    }

    public function reenviarVerificacion(string $usuarioId): void
    {
        $this->autorizarAdministracion();

        $usuario = User::query()->findOrFail($usuarioId);

        if (! $usuario->hasVerifiedEmail()) {
            $usuario->sendEmailVerificationNotification();
            session()->flash('usuario-actualizado', 'Se reenvió el enlace de verificación al correo registrado.');
        }
    }

    public function render(): View
    {
        return view('livewire.administracion.gestion-usuarios', [
            'usuarios' => $this->usuarios(),
            'roles' => RolUsuario::cases(),
        ]);
    }

    private function autorizarAdministracion(): void
    {
        // Se vuelve a consultar la membresía en cada petición Livewire para
        // que revocar ADMIN invalide incluso un formulario que ya estaba abierto.
        abort_unless(
            auth()->check() && User::query()
                ->whereKey(auth()->id())
                ->whereNotNull('email_verified_at')
                ->whereHas('roles', fn ($roles) => $roles->where('rol', RolUsuario::ADMIN->value))
                ->exists(),
            403,
        );
    }

    private function resetEdicion(): void
    {
        $this->reset([
            'usuarioEnEdicion',
            'edicionFirstName',
            'edicionEmail',
            'edicionLastName',
            'edicionRol',
            'edicionRoles',
            'edicionCargo',
            'edicionInstitucion',
        ]);
        $this->resetValidation();
    }

    private function esCorreoInstitucional(string $email): bool
    {
        foreach (config('auth.internal_email_domains', []) as $dominio) {
            if (str_ends_with(User::normalizarEmail($email), '@'.mb_strtolower((string) $dominio))) {
                return true;
            }
        }

        return false;
    }

    private function valorOpcional(string $valor): ?string
    {
        $valor = trim($valor);

        return $valor === '' ? null : $valor;
    }

    /** @return LengthAwarePaginator<User> */
    private function usuarios(): LengthAwarePaginator
    {
        $termino = mb_strtolower(trim($this->busqueda));

        $consulta = User::query()
            ->with('roles')
            ->when($termino !== '', function ($consulta) use ($termino): void {
                $patron = '%'.$termino.'%';
                $consulta->where(function ($subconsulta) use ($patron): void {
                    $subconsulta
                        ->whereRaw('LOWER(first_name) LIKE ?', [$patron])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$patron])
                        ->orWhere('email_normalizado', 'like', $patron)
                        ->orWhereRaw('LOWER(COALESCE(institucion, ?)) LIKE ?', ['', $patron]);
                });
            })
            ->when($this->filtroRol !== '', function ($consulta): void {
                $rol = RolUsuario::tryFrom($this->filtroRol);
                if ($rol !== null) {
                    $consulta->whereHas('roles', fn ($roles) => $roles->where('rol', $rol->value));
                }
            });

        return $this->ordenarConsultaGrilla($consulta, 'first_name')->orderBy('last_name')->orderBy('id')->paginate($this->filasGrilla());
    }

    protected function gridColumns(): array
    {
        return ['persona' => 'first_name', 'perfil' => 'cargo', 'estado' => 'email_verified_at', 'alta' => 'created_at'];
    }
}
