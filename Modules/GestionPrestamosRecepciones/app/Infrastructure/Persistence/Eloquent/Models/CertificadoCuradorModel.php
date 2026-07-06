<?php

declare(strict_types=1);

namespace Modules\GestionPrestamosRecepciones\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo Eloquent para la tabla 'prestamos.certificados_curador'.
 *
 * Custodia el certificado de firma electrónica (PKCS#12) de cada curador, con el
 * archivo y la contraseña cifrados en reposo.
 */
final class CertificadoCuradorModel extends Model
{
    protected $table = 'prestamos.certificados_curador';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'curador_id',
        'p12_cifrado',
        'contrasenia_cifrada',
        'common_name',
        'serial',
        'valido_hasta',
    ];

    protected $casts = [
        'valido_hasta' => 'datetime',
    ];
}
