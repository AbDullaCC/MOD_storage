<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait RecordsCreator
{
    /** Save the operation and its server-supplied attribution in one insert. */
    public static function createRecorded(array $attributes, User $user): static
    {
        $record = new static($attributes);
        $record->forceFill([
            'created_by' => $user->getKey(),
            'created_by_name' => $user->name,
            'created_at' => now(),
        ])->save();

        return $record;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRecordedByLabelAttribute(): string
    {
        return $this->created_by_name ?? 'سجل سابق / المستخدم غير معروف';
    }

    public function getRecordedAtDisplayAttribute(): string
    {
        return $this->created_at?->format('Y-m-d H:i:s') ?? 'غير معروف';
    }
}
