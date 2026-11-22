<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Services\SystemMailConfigurationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\BasisOfRecord;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\CollectionCode;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\EstadoTaxon;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\InstitutionCode;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\License;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\PrioridadColumna;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoLocalidad;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\RangoTaxonomico;
use Modules\InventarioGestionColeccion\Domain\SeguimientoFisico\ValueObjects\TipoEntidadDepositante;

final class TablasCatalogoDatos
{
    private const AUDITORIA = ['created_at', 'updated_at', 'deleted_at', 'updated_by', 'actualizado_por', 'actualizado_por_id'];

    public function permitidas(User $usuario): array
    {
        $definir = static fn (string $nombre, string $tabla, string $pk, string $donde, string $uso, int|bool|null $unico = null): array =>
            [$nombre, $tabla, $pk, $unico, 'donde' => $donde, 'uso' => $uso];
        $tablas = [
            'instituciones' => $definir('Instituciones', 'usuarios.instituciones_catalogo', 'id',
                'Perfiles y formulario de depósitos', 'Proporciona las instituciones que se pueden seleccionar.'),
            'depositantes' => $definir('Entidades depositantes', 'taxonomia.entidades_depositantes', 'id',
                'Inventario de la colección y actas de depósito', 'Identifica a las personas e instituciones que depositan especímenes.'),
            'cargos' => $definir('Cargos', 'usuarios.cargos_catalogo', 'id',
                'Perfiles y formulario de depósitos', 'Proporciona los cargos y posiciones disponibles en los formularios.'),
            'grupos-animales' => $definir('Grupos animales', 'recepciones.catalogo_grupos_invertebrados', 'codigo',
                'Solicitud y recepción de depósitos', 'Identifica los grupos de especímenes que se reciben.'),
            'taxones' => $definir('Taxones', 'taxonomia.taxones', 'id',
                'Inventario y explorador de la colección', 'Organiza la clasificación científica que se asigna a los especímenes.'),
            'localidades-coleccion' => $definir('Localidades de la colección', 'taxonomia.localidades', 'id',
                'Inventario, filtros y mapa de la colección', 'Registra los lugares de colecta, sus coordenadas y su jerarquía.'),
            'fuentes-revocacion' => $definir('Revocación de firmas', 'recepciones.fuentes_revocacion_firma', 'codigo',
                'Verificación de firmas en documentos de depósitos', 'Configura las fuentes que indican si un certificado fue revocado.'),
            'asistente' => $definir('Asistente · Nodos', 'divulgacion.chat_nodes', 'id',
                'Asistente del portal', 'Organiza los temas, respuestas y enlaces del asistente.'),
            'asistente-alias' => $definir('Asistente · Alias', 'divulgacion.chat_aliases', 'id',
                'Búsqueda de respuestas del asistente', 'Vincula frases de consulta con sus temas.'),
            'asistente-variantes' => $definir('Asistente · Variantes', 'divulgacion.chat_variants', 'id',
                'Respuestas del asistente', 'Guarda alternativas de respuesta para cada tema.'),
            'asistente-sinonimos' => $definir('Asistente · Sinónimos', 'divulgacion.chat_synonyms', 'id',
                'Interpretación de consultas del asistente', 'Relaciona palabras equivalentes para encontrar respuestas.'),
            'asistente-configuracion' => $definir('Asistente · Configuración', 'divulgacion.chat_settings', 'key',
                'Selección de respuestas del asistente', 'Guarda parámetros como los pesos y umbrales de confianza.'),
            'gbif' => $definir('Publicación GBIF', 'taxonomia.dataset_config', 'id',
                'Publicación y exportaciones Darwin Core', 'Define los metadatos institucionales y la licencia del conjunto publicado.', true),
            'columnas' => $definir('Prioridad de columnas', 'taxonomia.configuracion_columnas', 'id',
                'Captura y gestión de especímenes', 'Personaliza la prioridad de las columnas por pantalla.'),
        ];

        return $usuario->esAdministrador() ? $tablas + [
            'localidades-ecuador' => $definir('Localidades de Ecuador', 'recepciones.localidades_ecuador_catalogo', 'codigo',
                'Formulario de depósitos e inventario', 'Proporciona localidades oficiales y administradas de Ecuador.'),
            'sistema' => $definir('Correo y descargas · Correo', 'usuarios.configuracion_correo', 'id',
                'Notificaciones y correos del sistema', 'Configura el servidor SMTP, la cuenta y el remitente.', 1),
            'descargas' => $definir('Correo y descargas · Descargas', 'usuarios.configuracion_catalogo', 'clave',
                'Explorador y servicios de descarga del catálogo', 'Guarda ajustes como la habilitación de descargas por autoservicio.'),
            'siglas-expedientes' => $definir('Siglas de expedientes', 'usuarios.configuracion_expediente', 'id',
                'Gestión de depósitos', 'Define el prefijo que reciben los expedientes nuevos.', 1),
        ] : $tablas;
    }

    public function columnas(array $tabla): array
    {
        return array_column(Schema::getColumns($tabla[1]), null, 'name');
    }

    public function columnasVisibles(array $tabla, array $columnas): array
    {
        return array_filter($columnas, fn (array $columna): bool => $columna['name'] !== $tabla[2]
            && !$this->automatica($columna));
    }

    public function listar(array $tabla, array $columnas, string $busqueda): Builder
    {
        $nombres = array_keys($columnas);
        $consulta = DB::table($tabla[1]);
        if ($this->esCorreo($tabla)) {
            $consulta->select(array_values(array_unique([$tabla[2], ...array_diff($nombres, ['password'])])))
                ->selectRaw("CASE WHEN password IS NULL THEN NULL ELSE 'Protegida' END AS password");
            $nombres = array_values(array_diff($nombres, ['password']));
        }
        if (trim($busqueda) !== '') {
            $patron = '%'.strtr(trim($busqueda), ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']).'%';
            $consulta->where(function (Builder $campos) use ($nombres, $patron): void {
                foreach ($nombres as $nombre) {
                    $sql = $campos->getGrammar()->wrap($nombre);
                    $campos->orWhereRaw("cast($sql as text) ilike ?", [$patron]);
                }
            });
        }

        return $consulta;
    }

    public function editar(array $tabla, string $id): array
    {
        $columnas = $this->columnas($tabla);
        $fila = $this->versionar($this->listar($tabla, $columnas, ''), $tabla)->where($tabla[2], $id)->first() ?? abort(404);
        $datos = [];
        foreach ($this->editables($tabla, $columnas, false) as $nombre => $columna) {
            $valor = $fila->{$nombre};
            $datos[$nombre] = is_bool($valor) ? ($valor ? '1' : '0') : $valor;
        }

        return [$datos, $this->huella($fila)];
    }

    public function editables(array $tabla, array $columnas, bool $nuevo): array
    {
        return array_filter($columnas, function (array $columna) use ($tabla, $nuevo): bool {
            $nombre = $columna['name'];
            if ($this->automatica($columna) || ($this->esCorreo($tabla) && $nombre === 'password')) {
                return false;
            }

            return $nombre !== $tabla[2] || ($nuevo && !isset($tabla[3]) && !$columna['auto_increment']
                && $columna['type_name'] !== 'uuid' && $columna['default'] === null);
        });
    }

    public function opcionesEdicion(string $tabla): array
    {
        $etiquetas = static fn (array $casos): array => array_combine(
            array_column($casos, 'value'),
            array_map(static fn (\BackedEnum $caso): string => Str::headline($caso->value), $casos),
        );

        return match ($tabla) {
            'taxonomia.taxones' => [
                'rango' => array_replace($etiquetas(RangoTaxonomico::cases()), ['genero' => 'Género', 'phylum' => 'Filo']),
                'estado' => $etiquetas(EstadoTaxon::cases()),
            ],
            'taxonomia.localidades' => [
                'rango' => array_replace($etiquetas(RangoLocalidad::cases()), ['pais' => 'País', 'area_protegida' => 'Área protegida']),
            ],
            'taxonomia.entidades_depositantes' => ['tipo' => array_replace($etiquetas(TipoEntidadDepositante::cases()), ['institucion' => 'Institución'])],
            'taxonomia.dataset_config' => ['basis_of_record' => array_column(BasisOfRecord::cases(), 'value', 'value')],
            'taxonomia.configuracion_columnas' => ['prioridad' => array_replace($etiquetas(PrioridadColumna::cases()), ['critica' => 'Crítica'])],
            'recepciones.localidades_ecuador_catalogo' => ['provincia_codigo' => array_column(CatalogoTerritorialEcuador::provincias(), 'nombre', 'codigo')],
            'divulgacion.chat_nodes' => [
                'status' => ['draft' => 'Borrador', 'published' => 'Publicado', 'inactive' => 'Inactivo'],
                'action' => [
                    'DEPOSIT_LINK' => 'Depósitos', 'MY_REQUESTS_LINK' => 'Mis solicitudes',
                    'CATALOG_LINK' => 'Catálogo', 'LOGIN_LINK' => 'Iniciar sesión', 'CONTACT_LINK' => 'Contacto',
                ],
            ],
            default => [],
        };
    }

    public function booleanosIniciales(array $tabla, array $columnas): array
    {
        $valores = [];
        foreach ($this->editables($tabla, $columnas, true) as $nombre => $columna) {
            if ($columna['type_name'] === 'bool') {
                $valor = match ($columna['default']) {'true' => '1', 'false' => '0', default => null};
                if ($valor !== null) {
                    $valores[$nombre] = $valor;
                }
            }
        }

        return $valores;
    }

    private function automatica(array $columna): bool
    {
        return in_array($columna['name'], self::AUDITORIA, true) || $columna['generation'] !== null;
    }

    public function crearOEditar(array $tabla, ?string $id, string $huella, array $entrada, User $usuario, #[\SensitiveParameter] string $secreto = ''): void
    {
        DB::transaction(function () use ($tabla, $id, $huella, $entrada, $usuario, $secreto): void {
            $columnas = $this->columnas($tabla);
            $consulta = DB::table($tabla[1]);
            DB::statement("set local lock_timeout = '3s'");
            if ($id === null && isset($tabla[3])) {
                DB::statement('lock table '.$consulta->getGrammar()->wrapTable($tabla[1]).' in share row exclusive mode');
                if ($consulta->exists()) {
                    $this->error('Esta tabla admite un solo registro.');
                }
            }
            $anterior = $id === null ? null : $this->bloquearFila($tabla, $columnas, $id, $huella);

            $datos = $this->validar($tabla, $columnas, $entrada, $id === null);
            if ($tabla[1] === 'usuarios.cargos_catalogo') {
                $datos['nombre'] = trim($datos['nombre']);
                DB::select('select pg_advisory_xact_lock(hashtext(?))', ['cargo:'.mb_strtolower($datos['nombre'])]);
            }
            app(ValidacionTablasCatalogo::class)->validar($tabla[1], $datos, $id);
            $this->validarIdentidadCatalogo($tabla[1], $datos, $id);
            if ($anterior !== null && isset($datos['nombre'], $anterior->nombre) && $datos['nombre'] !== $anterior->nombre) {
                $this->comprobarUsosSinClaveForanea($tabla[1], $anterior);
            }
            if ($this->esCorreo($tabla)) {
                if ($id !== null && $id !== '1') {
                    $this->error('El registro de correo activo utiliza el identificador 1.');
                }
                Validator::make(['contraseña' => $secreto], [
                    'contraseña' => [$id === null ? 'required' : 'nullable', 'string', 'max:255'],
                ])->validate();
                app(SystemMailConfigurationService::class)->save($datos, $secreto, $usuario);

                return;
            }

            foreach (array_intersect(['updated_by', 'actualizado_por', 'actualizado_por_id'], array_keys($columnas)) as $auditoria) {
                $datos[$auditoria] = (string) $usuario->getKey();
            }
            if (isset($columnas['updated_at'])) {
                $datos['updated_at'] = now();
            }

            if ($id === null) {
                $clave = $tabla[2];
                if ($columnas[$clave]['type_name'] === 'uuid') {
                    $datos[$clave] = Str::uuid()->toString();
                } elseif (isset($tabla[3]) && $tabla[3] !== true) {
                    $datos[$clave] = $tabla[3];
                }
                if (isset($columnas['created_at'])) {
                    $datos['created_at'] = now();
                }
                $consulta->insert($datos);
            } else {
                $consulta->where($tabla[2], $id)->update($datos);
            }
            if ($tabla[1] === 'usuarios.configuracion_expediente') {
                ConfiguracionExpediente::olvidar();
            }
        });
    }

    public function eliminar(array $tabla, string $id, string $huella): void
    {
        DB::transaction(function () use ($tabla, $id, $huella): void {
            DB::statement("set local lock_timeout = '3s'");
            $fila = $this->bloquearFila($tabla, $this->columnas($tabla), $id, $huella);
            $this->comprobarUsosSinClaveForanea($tabla[1], $fila);
            $referencias = DB::select(<<<'SQL'
                select c.oid, ns.nspname as esquema, r.relname as tabla,
                    origen.attname as origen, destino.attname as destino
                from pg_constraint c
                join pg_class r on r.oid = c.conrelid
                join pg_namespace ns on ns.oid = r.relnamespace
                cross join lateral unnest(c.conkey, c.confkey) as k(origen, destino)
                join pg_attribute origen on origen.attrelid = c.conrelid and origen.attnum = k.origen
                join pg_attribute destino on destino.attrelid = c.confrelid and destino.attnum = k.destino
                where c.contype = 'f' and c.confrelid = to_regclass(?)
                SQL, [$tabla[1]]);

            foreach (collect($referencias)->groupBy('oid') as $campos) {
                $primero = $campos->first();
                $consulta = DB::table($primero->esquema.'.'.$primero->tabla);
                foreach ($campos as $campo) {
                    $valor = $fila->{$campo->destino};
                    if ($valor === null) {
                        continue 2;
                    }
                    $consulta->where($campo->origen, $valor);
                }
                if ($consulta->exists()) {
                    $this->error('El registro está en uso. Retira sus referencias antes de eliminarlo.');
                }
            }
            DB::table($tabla[1])->where($tabla[2], $id)->delete();
            if ($tabla[1] === 'usuarios.configuracion_expediente') {
                ConfiguracionExpediente::olvidar();
            }
            if ($this->esCorreo($tabla)) {
                app(SystemMailConfigurationService::class)->registrarEliminacion();
            }
        });
    }

    public function esCorreo(array $tabla): bool
    {
        return $tabla[1] === 'usuarios.configuracion_correo';
    }

    private function validar(array $tabla, array $columnas, array $entrada, bool $nuevo): array
    {
        $datos = $reglas = [];
        $opciones = $this->opcionesEdicion($tabla[1]);
        foreach ($this->editables($tabla, $columnas, $nuevo) as $nombre => $columna) {
            $valor = $entrada[$nombre] ?? null;
            if ($nuevo && $columna['default'] !== null && ($valor === null || $valor === '')) {
                continue;
            }
            $datos[$nombre] = $valor === '' && $columna['nullable'] ? null : $valor;
            $tipo = match ($columna['type_name']) {
                'bool' => 'boolean',
                'int2', 'int4', 'int8' => 'integer',
                'numeric', 'float4', 'float8' => 'numeric',
                'uuid' => 'uuid',
                'json', 'jsonb' => 'json',
                'date', 'timestamp', 'timestamptz' => 'date',
                default => 'string',
            };
            $reglas[$nombre] = [$columna['nullable'] ? 'nullable' : 'required', $tipo];
            if (preg_match('/\((\d+)\)$/', $columna['type'], $limite) && $tipo === 'string') {
                $reglas[$nombre][] = 'max:'.$limite[1];
            }
            if (isset($opciones[$nombre])) {
                $reglas[$nombre][] = Rule::in(array_keys($opciones[$nombre]));
            }
        }

        foreach ($this->reglasCatalogo($tabla[1]) as $campo => $regla) {
            if (isset($reglas[$campo])) {
                $reglas[$campo] = [...$reglas[$campo], ...$regla];
            }
        }
        $validado = Validator::make($datos, $reglas, ['*.in' => 'Selecciona un valor de la lista.'])->validate();
        foreach ($validado as $nombre => $valor) {
            if ($columnas[$nombre]['type_name'] === 'bool' && $valor !== null) {
                $validado[$nombre] = (bool) $valor;
            }
        }

        return $validado;
    }

    private function reglasCatalogo(string $tabla): array
    {
        return match ($tabla) {
            'usuarios.configuracion_correo' => [
                'host' => ['regex:/^(?=.{1,255}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/i'],
                'port' => ['between:1,65535'], 'username' => ['email:rfc'], 'from_address' => ['email:rfc'],
            ],
            'usuarios.configuracion_expediente' => ['prefijo' => ['regex:/^[A-Z]{2,10}(?:-[A-Z]{2,10}){0,2}-DEP$/']],
            'recepciones.fuentes_revocacion_firma' => [
                'crl_url' => ['url:https', 'max:2048'],
                'ocsp_url' => ['url:http,https', 'max:2048', 'not_regex:/[|]/'],
                'horas_actualizacion' => ['between:1,168'],
            ],
            'taxonomia.localidades' => ['decimal_latitude' => ['between:-90,90'], 'decimal_longitude' => ['between:-180,180']],
            'usuarios.cargos_catalogo' => ['nombre' => ['min:2']],
            default => [],
        };
    }

    private function validarIdentidadCatalogo(string $tabla, array $datos, ?string $id): void
    {
        if ($tabla === 'taxonomia.dataset_config') {
            try {
                InstitutionCode::desde($datos['institution_code']);
                CollectionCode::desde($datos['collection_code']);
                if (isset($datos['basis_of_record'])) {
                    BasisOfRecord::from($datos['basis_of_record']);
                }
                if (isset($datos['license'])) {
                    License::desde($datos['license']);
                }
            } catch (\ValueError|\InvalidArgumentException|\DomainException) {
                $this->error('Revisa el código de institución, el de colección, basis_of_record y license.');
            }
        }
    }

    private function comprobarUsosSinClaveForanea(string $tabla, object $fila): void
    {
        [$campoUsuario, $campoSolicitud] = match ($tabla) {
            'usuarios.cargos_catalogo' => ['cargo', 'solicitud_cargo'],
            'usuarios.instituciones_catalogo' => ['institucion', 'solicitud_institucion'],
            'recepciones.catalogo_grupos_invertebrados' => [null, 'grupo_animal'],
            default => [null, null],
        };
        if ($campoUsuario !== null && User::query()
            ->whereRaw("lower(btrim($campoUsuario)) = lower(btrim(?))", [$fila->nombre])->exists()) {
            $this->error('El registro está en uso por un perfil de usuario.');
        }
        if ($campoSolicitud !== null && DB::table('recepciones.solicitudes_deposito')
            ->whereRaw("lower(btrim($campoSolicitud)) = lower(btrim(?))", [$fila->nombre])->exists()) {
            $this->error('El registro está en uso por una solicitud de depósito.');
        }
    }

    private function bloquearFila(array $tabla, array $columnas, string $id, string $huella): object
    {
        $fila = $this->versionar($this->listar($tabla, $columnas, ''), $tabla)->where($tabla[2], $id)->lockForUpdate()->first();
        if (!$fila || !hash_equals($huella, $this->huella($fila))) {
            $this->error('El registro cambió. Vuelve a abrirlo antes de continuar.');
        }

        return $fila;
    }

    private function versionar(Builder $consulta, array $tabla): Builder
    {
        if (!$this->esCorreo($tabla)) {
            $consulta->select('*');
        }

        return $consulta->selectRaw('xmin::text AS _version_fila');
    }

    private function huella(object $fila): string
    {
        return hash('sha256', json_encode($fila, JSON_THROW_ON_ERROR));
    }

    private function error(string $mensaje): never
    {
        throw ValidationException::withMessages(['registro' => $mensaje]);
    }
}
