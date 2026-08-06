<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyMember extends Model
{
    protected $fillable = ['contact_id', 'relation', 'name', 'age', 'sort_order'];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
