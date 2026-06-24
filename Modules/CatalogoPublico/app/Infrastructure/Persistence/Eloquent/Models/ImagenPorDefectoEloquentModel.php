<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class ImagenPorDefectoEloquentModel extends Model
{
    protected $table = 'divulgacion.imagenes_por_defecto';

    protected $primaryKey = 'id';

    public $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'nivel',
        'valor_taxon',
        'imagen_id',
    ];
}
