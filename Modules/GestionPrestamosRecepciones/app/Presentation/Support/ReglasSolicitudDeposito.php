<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Presentation\Support;

use App\Rules\IdentificacionEcuatoriana;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class ReglasSolicitudDeposito
{
    public static function obtener(string $tipoIdentificacion): array
    {
        return [
            'solicitudNombrePermiso' => ['required', 'string', 'max:200'],
            'solicitudTipoIdentificacion' => ['required', Rule::in(['cedula', 'ruc', 'pasaporte'])],
            'solicitudCedula' => ['required', new IdentificacionEcuatoriana($tipoIdentificacion)],
            'solicitudCargo' => ['required', 'string', 'max:200', Rule::exists(DB::getDefaultConnection().'.usuarios.cargos_catalogo', 'nombre')->where('activo', true)],
            'solicitudGrupo' => ['required', Rule::in(config('depositos.grupos_especimenes'))],
            'solicitudProyecto' => ['required', 'string', 'max:300'],
            'solicitudInstitucion' => ['required', 'string', 'max:250', Rule::exists(DB::getDefaultConnection().'.usuarios.instituciones_catalogo', 'nombre')->where('activo', true)],
            'solicitudCorreo' => ['required', 'email', 'max:250'],
            'solicitudOficio' => ['required', 'string', 'max:100'],
            'solicitudTexto' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
