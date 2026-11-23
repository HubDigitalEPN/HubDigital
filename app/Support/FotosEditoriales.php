<?php

declare(strict_types=1);

namespace App\Support;

use Modules\GestionPrestamosRecepciones\Infrastructure\Storage\AlmacenamientoDepositos;

/** Fotografías de presentación; sus identidades nunca proceden de una ruta enviada por el navegador. */
final class FotosEditoriales
{
    public const CABECERA_SISTEMA = 'logo-sistema-cabecera';
    public const ACCESO_SISTEMA = 'logo-sistema-acceso';
    public const ALT_SISTEMA = 'Laboratorio de Invertebrados';

    public const ORIGINALES = [
        'inicio' => 'images/portal-laboratorio-hero.jpg',
        'depositos' => 'images/portal-depositos-hero.webp',
        'logo' => 'images/logo-epn-v1.avif',
        self::CABECERA_SISTEMA => 'images/logo-laboratorio-blanco.png',
        self::ACCESO_SISTEMA => 'images/logo-laboratorio-transparente.png',
    ];

    public static function clave(string $id): string
    {
        abort_unless(isset(self::ORIGINALES[$id]), 422);
        return 'foto_editorial.'.$id;
    }

    public static function leer(string $id): array
    {
        $dato = json_decode(PortalCopy::all()[self::clave($id)] ?? '{}', true);
        if (!is_array($dato)) return [];
        $hash = (string) ($dato['sha256'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/D', $hash) || ($dato['ruta'] ?? '') !== 'divulgacion/laboratorio/'.$hash.'.webp') unset($dato['ruta'], $dato['sha256']);
        return array_intersect_key($dato, array_flip(['ruta', 'sha256', 'alt']));
    }

    public static function url(string $id): string
    {
        $foto = self::leer($id);
        return isset($foto['sha256']) ? route('portal.foto-editorial', ['foto' => $id, 'hash' => $foto['sha256']]) : asset(self::ORIGINALES[$id]);
    }

    public static function alt(string $id, string $defecto): string { return (string) (self::leer($id)['alt'] ?? $defecto); }

    public static function contenido(string $id, string $hash): string
    {
        $foto = self::leer($id);
        abort_unless(isset($foto['sha256']) && hash_equals($foto['sha256'], $hash), 404);
        $contenido = app(AlmacenamientoDepositos::class)->obtenerVerificado($foto['ruta'], $hash);
        abort_unless((@getimagesizefromstring($contenido)['mime'] ?? '') === 'image/webp', 404);
        return $contenido;
    }
}
