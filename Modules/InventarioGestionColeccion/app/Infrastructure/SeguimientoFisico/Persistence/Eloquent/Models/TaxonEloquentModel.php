<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxonEloquentModel extends Model
{
    protected $table = 'taxonomia.taxones';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'nombre_cientifico',
        'rango',
        'autor',
        'anio_descripcion',
        'epiteto_infraespecifico',
        'estado',
        'padre_id',
    ];

    public function especimenes(): HasMany
    {
        return $this->hasMany(EspecimenEloquentModel::class, 'taxon_id');
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'padre_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'padre_id');
    }
}
