<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Password;

/** Evita que la latencia SMTP revele si una cuenta existe. */
final class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /** Dev consume su cola aislada; nunca habilita trabajos restaurados de default. */
    public function viaQueues(): array
    {
        return ['mail' => config('hubdigital.validation_mode')
            ? (string) config('hubdigital.validation_queue', 'validation')
            : null];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        // Un trabajo retrasado no debe entregar un enlace ya usado, reemplazado
        // o caducado mientras el trabajador estuvo desconectado.
        return Password::broker()->tokenExists($notifiable, $this->token);
    }

    protected function resetUrl($notifiable): string
    {
        // En HTTP y en el worker se usa el dominio de la instancia, no el Host
        // proporcionado por quien solicita la recuperación.
        return rtrim((string) config('app.url'), '/').route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false);
    }

    protected function buildMailMessage($url): MailMessage
    {
        $data = [
            'url' => $url,
            'expireMinutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
        ];

        return (new MailMessage)
            ->subject('Restablece tu contraseña de HubDigital')
            ->view('emails.auth.reset-password', $data)
            ->text('emails.auth.reset-password-text', $data);
    }
}
