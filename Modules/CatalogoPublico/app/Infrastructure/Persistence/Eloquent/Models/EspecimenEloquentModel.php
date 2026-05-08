<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class EspecimenEloquentModel extends Model
{
    protected $table = 'divulgacion.especimenes';

    protected $primaryKey = 'id';

    public $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'occurrence_id',
        'scientific_name',
        'individual_count',
        'type_status',
        'type_notes',
        'specimen_notes',
        'sampling_protocol',
        'recorded_by',
        'occurrence_status',
        'family',
        'genus',
        'country',
        'locality_name',
        'decimal_latitude',
        'decimal_longitude',
    ];
}
