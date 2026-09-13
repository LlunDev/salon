<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['tenant_id', 'starts_at', 'ends_at', 'reason', 'created_by', 'updated_by'])]
class SalonScheduleBlock extends Model
{
    use HasUuids;

    protected static function booted(): void
    {
        static::updating(function (self $block): void {
            if ($block->isDirty(['tenant_id', 'created_by'])) {
                throw new LogicException('El salón y el creador del bloqueo son inmutables.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
