<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Campana de notificaciones del portal. Muestra el conteo de no leídas y las últimas
 * notificaciones del usuario autenticado (notificaciones de base de datos de Laravel),
 * permitiendo marcarlas como leídas y navegar al recurso relacionado.
 */
final class CampanaNotificaciones extends Component
{
    /**
     * Marca una notificación como leída y navega a su recurso.
     */
    public function abrir(string $id): mixed
    {
        $notificacion = auth()->user()?->notifications()->find($id);
        $notificacion?->markAsRead();

        $url = $notificacion?->data['url'] ?? null;

        return $url !== null ? $this->redirect($url, navigate: true) : null;
    }

    /**
     * Marca todas las notificaciones del usuario como leídas.
     */
    public function marcarTodasLeidas(): void
    {
        auth()->user()?->unreadNotifications->markAsRead();
    }

    public function render(): View
    {
        $usuario = auth()->user();

        return view('livewire.campana-notificaciones', [
            'noLeidas' => $usuario ? $usuario->unreadNotifications()->count() : 0,
            'notificaciones' => $usuario ? $usuario->notifications()->latest()->limit(10)->get() : collect(),
        ]);
    }
}
