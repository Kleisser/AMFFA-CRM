<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanIncrease extends Model
{
    protected $fillable = ['user_id', 'percentage', 'from_period', 'to_period', 'plan_ids'];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
            'plan_ids' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
