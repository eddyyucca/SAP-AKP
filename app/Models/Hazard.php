<?php

namespace App\Models;

use Akp\PortalSso\PortalUser;
use App\Models\Concerns\HasSafetyRecord;
use App\Support\Access;
use App\Support\Risk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hazard extends Model
{
    use HasSafetyRecord, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'observed_at' => 'datetime',
        'due_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** Hazard yang boleh dilihat pengguna: semua untuk admin/HSE/manajer; selain itu laporan sendiri atau yang ia tangani (PIC). */
    public function scopeVisibleTo(Builder $query, PortalUser $user): Builder
    {
        if (Access::canViewAll($user)) {
            return $query;
        }

        return $query->where(function (Builder $w) use ($user) {
            $w->where('created_by_id', (string) $user->id);

            if (filled($user->employee_no)) {
                $w->orWhere('reporter_nik', $user->employee_no)
                    ->orWhere('pic_nik', $user->employee_no)
                    ->orWhereExists(fn ($s) => $s->from('corrective_actions')
                        ->whereColumn('corrective_actions.source_id', 'hazards.id')
                        ->where('corrective_actions.source_type', 'hazard')
                        ->where('corrective_actions.pic_nik', $user->employee_no));
            }
        });
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'in_progress']);
    }

    public function canBeSeenBy(PortalUser $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['open', 'in_progress'], true);
    }

    public function isOverdue(): bool
    {
        return $this->isActive() && $this->due_date && $this->due_date->lt(today());
    }

    public function riskMeta(): array
    {
        return Risk::meta($this->risk_level);
    }

    public function statusMeta(): array
    {
        return config('ssap.hazard_statuses.'.$this->status, ['label' => $this->status, 'color' => 'secondary', 'icon' => 'bi-circle']);
    }

    public function categoryLabel(): string
    {
        return config('ssap.hazard_categories.'.$this->category, $this->category);
    }
}
