<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Domain\ValueObjects;

/** Metadatos editoriales: nunca se dibujan sobre los píxeles de la fotografía. */
final readonly class AtribucionImagen
{
    public function __construct(
        public string $photographer = '',
        public string $license = '',
        public string $licenseHolder = '',
        public string $licenseInstitution = '',
        public string $contact = '',
        public string $view = '',
    ) {
        foreach ([$photographer, $license, $licenseHolder, $licenseInstitution, $contact] as $valor) {
            if (mb_strlen($valor) > 255 || preg_match('/[\x00-\x1F\x7F]/u', $valor)) {
                throw new \InvalidArgumentException('Las atribuciones deben ser textos de hasta 255 caracteres, sin caracteres de control.');
            }
        }
        if (mb_strlen($view) > 80 || preg_match('/[\x00-\x1F\x7F]/u', $view)) {
            throw new \InvalidArgumentException('El tipo de vista debe tener hasta 80 caracteres.');
        }
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return ['photographer' => $this->photographer, 'license' => $this->license,
            'license_holder' => $this->licenseHolder, 'license_institution' => $this->licenseInstitution,
            'contact' => $this->contact, 'view' => $this->view];
    }

    public static function vistaDesdeNombre(string $nombre): string
    {
        preg_match('/_([A-Za-z]{1,4})\.[^.]+$/', $nombre, $coincidencia);
        return self::vistaDesdeSufijo($coincidencia[1] ?? '');
    }

    public static function vistaDesdeSufijo(string $sufijo): string
    {
        return ['L' => 'Lateral view', 'D' => 'Dorsal view', 'F' => 'Frontal view',
            'G' => 'Genital view', 'p' => 'Profile view'][$sufijo] ?? ($sufijo === '' ? '' : 'Vista '.$sufijo);
    }
}
