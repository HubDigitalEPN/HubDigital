<?php

declare(strict_types=1);

namespace Modules\CatalogoPublico\Infrastructure\Adapters;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only projection of taxonomia.especimenes.
 * Belongs to CatalogoPublico's Infrastructure ACL — never used outside this layer.
 */
final class TaxonomiaEspecimenReadModel extends Model
{
    protected $table = 'taxonomia.especimenes';

    protected $primaryKey = 'id';

    public $keyType = 'string';

    public $incrementing = false;
}
