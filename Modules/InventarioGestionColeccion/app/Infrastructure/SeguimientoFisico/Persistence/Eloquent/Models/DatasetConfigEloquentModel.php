<?php

declare(strict_types=1);

namespace Modules\InventarioGestionColeccion\Infrastructure\SeguimientoFisico\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class DatasetConfigEloquentModel extends Model
{
    protected $table = 'taxonomia.dataset_config';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'institution_code',
        'collection_code',
        'institution_id',
        'collection_id',
        'dataset_name',
        'owner_institution_code',
        'rights_holder',
        'access_rights',
        'license',
        'basis_of_record',
        'information_withheld',
        'eml_contacto',
        'eml_titulo',
    ];
}
