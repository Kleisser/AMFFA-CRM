<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alta de venta sincronizada desde Google Sheets (una fila por afiliado).
 */
class Venta extends Model
{
    protected $fillable = [
        'user_id',
        'asesor',
        'afiliado',
        'capitas',
        'plan',
        'mes',
        'tab',
        'fuente',
        'sincronizada_at',
    ];

    protected function casts(): array
    {
        return [
            'capitas' => 'integer',
            'sincronizada_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
