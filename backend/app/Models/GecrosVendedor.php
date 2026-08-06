<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catálogo de vendedores de afiliación de GECROS (dbo.vendedoresafi)
 * sincronizado vía puente. user_id vincula el vendedor de GECROS con un
 * usuario del CRM (rol seller); el equipo sale de user.supervisor_id.
 */
class GecrosVendedor extends Model
{
    protected $table = 'gecros_vendedores';

    protected $primaryKey = 'venafi_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'venafi_id',
        'nombre',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
