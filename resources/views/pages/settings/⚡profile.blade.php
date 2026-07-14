<?php

use App\Concerns\ProfileValidationRules;
use App\Enums\RolUsuario;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Configuración de perfil')] class extends Component {
    use ProfileValidationRules;

    public string $first_name = '';
    public string $last_name = '';
    public string $email = '';
    public string $cargo = '';
    public string $institucion = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->first_name = Auth::user()->first_name;
        $this->last_name = Auth::user()->last_name;
        $this->email = Auth::user()->email;
        $this->cargo = Auth::user()->cargo ?? '';
        $this->institucion = Auth::user()->institucion ?? '';
    }

    /**
     * Indica si el usuario es depositante: solo ellos declaran cargo e institución
     * (datos usados en el Acta recepción-depósito).
     */
    #[Computed]
    public function esDepositante(): bool
    {
        return Auth::user()->rol === RolUsuario::DEPOSITANTE;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $reglas = $this->profileRules($user->id);

        if ($this->esDepositante) {
            $reglas['cargo'] = ['required', 'string', 'max:255'];
            $reglas['institucion'] = ['required', 'string', 'max:255'];
        }

        $validated = $this->validate($reglas);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);  // accessor returns full name
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Configuración de perfil') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Perfil')" :subheading="__('Actualiza tu nombre y correo electrónico')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:input wire:model="first_name" :label="__('Nombre')" type="text" required autofocus autocomplete="given-name" />
                <flux:input wire:model="last_name" :label="__('Apellido')" type="text" required autocomplete="family-name" />
            </div>

            @if ($this->esDepositante)
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input wire:model="cargo" :label="__('Cargo o posición')" type="text" required autocomplete="organization-title" placeholder="Ej. Coordinador Técnico de Proyectos" />
                    <flux:input wire:model="institucion" :label="__('Institución o empresa')" type="text" required autocomplete="organization" placeholder="Ej. EcoSambito C. Ltda" />
                </div>
            @endif

            <div>
                <flux:input wire:model="email" :label="__('Correo electrónico')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Tu correo electrónico no está verificado.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Haz clic aquí para reenviar el correo de verificación.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium !dark:text-green-400 !text-green-600">
                                {{ __('Se ha enviado un nuevo enlace de verificación a tu correo electrónico.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Guardar') }}
                    </flux:button>
                </div>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Guardado.') }}
                </x-action-message>
            </div>
        </form>

        @if ($this->showDeleteUser)
            <livewire:pages::settings.delete-user-form />
        @endif
    </x-pages::settings.layout>
</section>
