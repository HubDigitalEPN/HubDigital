<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodigoQrEloquentModel extends Model
{
    protected $table = 'taxonomia.codigos_qr';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'especimen_id',
        'payload',
    ];

    public function especimen(): BelongsTo
    {
        return $this->belongsTo(EspecimenEloquentModel::class, 'especimen_id');
    }
}
