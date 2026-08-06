<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'dni',
        'status',
        'response',
        'error',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
