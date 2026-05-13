<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntidadDepositanteEloquentModel extends Model
{
    protected $table = 'taxonomia.entidades_depositantes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nombre',
        'tipo',
        'contacto',
    ];

    public function especimenes(): HasMany
    {
        return $this->hasMany(EspecimenEloquentModel::class, 'entidad_depositante_id');
    }
}
