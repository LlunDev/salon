<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id', 'weekday', 'closed', 'opens_at', 'closes_at'])]
class SalonWeeklyHour extends Model
{
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'closed' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
