<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EspecimenIdentificadorEloquentModel extends Model
{
    protected $table = 'taxonomia.especimen_identificadores';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'especimen_id',
        'tipo',
        'valor',
    ];

    public function especimen(): BelongsTo
    {
        return $this->belongsTo(EspecimenEloquentModel::class, 'especimen_id');
    }
}
