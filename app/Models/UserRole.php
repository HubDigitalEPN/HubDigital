<?php

namespace App\Models;

use App\Enums\RolUsuario;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRole extends Model
{
    use HasUuids;

    protected $table = 'usuarios.user_roles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'rol'];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'rol' => RolUsuario::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
