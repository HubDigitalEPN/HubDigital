<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoTaxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoLocalidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoTaxonomico;

final class ValidacionTablasCatalogo
{
    /** Se ejecuta dentro de la transacción que guarda el registro. */
    public function validar(string $tablaFisica, array $datos, ?string $id): void
    {
        match ($tablaFisica) {
            'taxonomia.taxones' => $this->validarTaxon($datos, $id),
            'taxonomia.localidades' => $this->validarLocalidad($datos, $id),
            'divulgacion.chat_nodes' => $this->validarNodo($datos, $id),
            'divulgacion.chat_settings' => $this->validarRanking($datos, $id),
            'usuarios.cargos_catalogo' => $this->validarCargo($datos, $id),
            default => null,
        };
    }

    private function validarTaxon(array $datos, ?string $id): void
    {
        $rango = RangoTaxonomico::tryFrom((string) ($datos['rango'] ?? ''))
            ?? $this->error('rango', 'Elige un rango taxonómico registrado.');
        if (EstadoTaxon::tryFrom((string) ($datos['estado'] ?? EstadoTaxon::Activo->value)) === null) {
            $this->error('estado', 'El estado debe ser activo o inactivo.');
        }
        if (trim((string) ($datos['epiteto_infraespecifico'] ?? '')) !== '' && !$rango->admiteEpitetoInfraespecifico()) {
            $this->error('epiteto_infraespecifico', 'Solo el rango especie admite un epíteto infraespecífico.');
        }

        $this->validarJerarquia('taxonomia.taxones', $datos, $id, $rango);
    }

    private function validarLocalidad(array $datos, ?string $id): void
    {
        $rango = RangoLocalidad::tryFrom((string) ($datos['rango'] ?? ''))
            ?? $this->error('rango', 'Elige un rango de localidad registrado.');

        $this->validarJerarquia('taxonomia.localidades', $datos, $id, $rango);
    }

    private function validarJerarquia(string $tabla, array $datos, ?string $id, RangoTaxonomico|RangoLocalidad $rango): void
    {
        $this->bloquearJerarquia($tabla);
        $claseRango = $rango::class;
        $padre = $datos['padre_id'] ?? null;
        if ($padre !== null) {
            if ($id !== null && $this->cierraCiclo($tabla, 'padre_id', (string) $padre, $id)) {
                $this->error('padre_id', 'El registro padre no puede ser el propio registro ni uno de sus descendientes.');
            }
            $rangoPadre = $claseRango::tryFrom((string) DB::table($tabla)->where('id', $padre)->value('rango'));
            if ($rangoPadre === null || !$rangoPadre->puedeSerPadreDe($rango)) {
                $this->error('padre_id', 'Selecciona un registro padre existente con un rango superior.');
            }
        }

        if ($id !== null && DB::table($tabla)->where('id', $id)->value('rango') !== $rango->value) {
            $rangosHijos = array_map(
                static fn ($hijo): string => $hijo->value,
                array_filter($claseRango::cases(), $rango->puedeSerPadreDe(...)),
            );
            if (DB::table($tabla)->where('padre_id', $id)->whereNotIn('rango', $rangosHijos)->exists()) {
                $this->error('rango', 'El rango elegido no es compatible con los registros hijos existentes.');
            }
        }
    }

    private function validarNodo(array $datos, ?string $id): void
    {
        if (!in_array($datos['status'] ?? 'draft', ['draft', 'published', 'inactive'], true)) {
            $this->error('status', 'Elige draft, published o inactive.');
        }

        $accion = $datos['action'] ?? null;
        if ($accion !== null && !in_array($accion, ['DEPOSIT_LINK', 'MY_REQUESTS_LINK', 'CATALOG_LINK', 'LOGIN_LINK', 'CONTACT_LINK'], true)) {
            $this->error('action', 'La acción indica un enlace no registrado.');
        }

        $padre = $datos['parent_id'] ?? null;
        if ($padre === null) {
            return;
        }

        $this->bloquearJerarquia('divulgacion.chat_nodes');
        if (!DB::table('divulgacion.chat_nodes')->where('id', $padre)->exists()) {
            $this->error('parent_id', 'Selecciona un nodo padre existente.');
        }
        if ($id !== null && $this->cierraCiclo('divulgacion.chat_nodes', 'parent_id', (string) $padre, $id)) {
            $this->error('parent_id', 'El nodo padre no puede ser el propio nodo ni uno de sus descendientes.');
        }
    }

    private function bloquearJerarquia(string $tabla): void
    {
        DB::selectOne('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['catalogo-jerarquia:'.$tabla]);
    }

    private function cierraCiclo(string $tabla, string $campoPadre, string $padre, string $id): bool
    {
        // Los nombres de tabla y columna provienen de los tres destinos internos anteriores.
        return DB::selectOne(
            "with recursive ascendencia(id) as (
                select id from $tabla where id = ?
                union
                select nodo.$campoPadre
                from $tabla as nodo
                join ascendencia on nodo.id = ascendencia.id
                where nodo.$campoPadre is not null
            )
            select 1 from ascendencia where id = ?",
            [$padre, $id],
        ) !== null;
    }

    private function validarRanking(array $datos, ?string $id): void
    {
        if (($id ?? $datos['key'] ?? null) !== 'ranking') {
            return;
        }

        $valor = json_decode((string) ($datos['value'] ?? ''), true);
        if (!is_array($valor)) {
            $this->error('value', 'El ranking debe tener formato JSON.');
        }
        $defaults = config('chatbot.ranking');
        $pesos = $this->numerosRanking($valor['weights'] ?? null, $defaults['weights']);
        if (array_sum($pesos) <= 0) {
            $this->error('value', 'La suma de los pesos del ranking debe ser positiva.');
        }

        $umbrales = $this->numerosRanking($valor['thresholds'] ?? null, $defaults['thresholds']);
        if ($umbrales['high'] <= $umbrales['medium'] || $umbrales['medium'] <= $umbrales['unknown']
            || $umbrales['ambiguous_min'] < $umbrales['unknown']) {
            $this->error('value', 'Ordena los umbrales de confianza y mantén el mínimo de ambigüedad sobre UNKNOWN.');
        }

        if (array_key_exists('confidence_formula', $valor)) {
            $this->numerosRanking($valor['confidence_formula'], $defaults['confidence_formula']);
        }
    }

    private function numerosRanking(mixed $numeros, array $defaults): array
    {
        if (!is_array($numeros) || array_diff_key($defaults, $numeros) !== [] || array_diff_key($numeros, $defaults) !== []) {
            $this->error('value', 'El ranking debe incluir todos los parámetros configurados para el asistente.');
        }
        foreach ($numeros as $numero) {
            if (!is_numeric($numero) || $numero < 0 || $numero > 1) {
                $this->error('value', 'Los parámetros numéricos del ranking deben estar entre cero y uno.');
            }
        }

        return $numeros;
    }

    private function validarCargo(array $datos, ?string $id): void
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if (mb_strlen($nombre) < 2) {
            $this->error('nombre', 'El cargo necesita un nombre de al menos dos caracteres.');
        }
        if (DB::table('usuarios.cargos_catalogo')->whereRaw('lower(nombre) = lower(?)', [$nombre])
            ->when($id !== null, fn ($consulta) => $consulta->where('id', '<>', $id))->exists()) {
            $this->error('nombre', 'Este cargo ya está registrado.');
        }
    }

    private function error(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
