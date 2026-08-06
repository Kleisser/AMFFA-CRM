<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Venta extends Model
{
    protected $fillable = [
        'user_id',
        'asesor',
        'mes',
        'monto',
        'sincronizada_at',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'sincronizada_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
