<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CorrectiveAction extends Model
{
    protected $fillable = [
        'type', 'description', 'pic_nik', 'pic_name', 'pic_department', 'pic_email', 'due_date', 'status',
        'completed_at', 'completion_note', 'verified_at', 'verified_by', 'verification_note', 'reminded_on', 'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'reminded_on' => 'date',
    ];

    /** Tindakan lama dari modul insiden yang diarsipkan tetap tersimpan di database tetapi tidak ditampilkan. */
    protected static function booted(): void
    {
        static::addGlobalScope('hazard-only', fn (Builder $q) => $q->where('source_type', 'hazard'));
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** Belum selesai dan lewat tenggat. */
    public function isOverdue(): bool
    {
        return in_array($this->status, ['open', 'in_progress'], true) && $this->due_date->lt(today());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'in_progress'])->whereDate('due_date', '<', today());
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'in_progress', 'done']);
    }

    public function statusMeta(): array
    {
        return config('ssap.action_statuses.'.$this->status, ['label' => $this->status, 'color' => 'secondary', 'icon' => 'bi-circle']);
    }

    public function typeLabel(): string
    {
        return config('ssap.action_types.'.$this->type, $this->type);
    }

    /** Tautan ke hazard sumber tindakan. */
    public function sourceUrl(): ?string
    {
        return match ($this->source_type) {
            'hazard' => route('ssap.hazards.show', $this->source_id),
            default => null,
        };
    }
}
