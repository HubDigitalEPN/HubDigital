<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class EspecimenDivulgableEloquentModel extends Model
{
    protected $table = 'divulgacion.especimenes_divulgables';

    protected $primaryKey = 'id';

    public $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'occurrence_id',
        'occurrence_id_visible',
        'scientific_name_visible',
        'individual_count_visible',
        'type_status_visible',
        'type_notes_visible',
        'specimen_notes_visible',
        'sampling_protocol_visible',
        'recorded_by_visible',
        'occurrence_status_visible',
        'family_visible',
        'genus_visible',
        'country_visible',
        'locality_name_visible',
        'decimal_latitude_visible',
        'decimal_longitude_visible',
    ];
}
