<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class ImagenTaxonomicaEloquentModel extends Model
{
    protected $table = 'divulgacion.imagenes_taxonomicas';

    protected $primaryKey = 'id';

    public $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'occurrence_id',
        'nombre_original',
        'ruta',
        'disco',
        'autor_nombre',
        'autor_apellido',
        'autor_nombre_completo',
        'marca_agua_aplicada',
    ];

    protected $casts = [
        'marca_agua_aplicada' => 'boolean',
    ];
}
