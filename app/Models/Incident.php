<?php

namespace App\Models;

use Akp\PortalSso\PortalUser;
use App\Models\Concerns\HasSafetyRecord;
use App\Support\Access;
use App\Support\Risk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Incident extends Model
{
    use HasSafetyRecord, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'occurred_at' => 'datetime',
        'investigation_due' => 'date',
        'investigation_started_at' => 'datetime',
        'closed_at' => 'datetime',
        'why_chain' => 'array',
        'cost_estimate' => 'decimal:2',
        'reported_to_authority' => 'boolean',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function persons(): HasMany
    {
        return $this->hasMany(IncidentPerson::class)->orderBy('id');
    }

    /** Insiden yang boleh dilihat pengguna: semua untuk admin/HSE/manajer; selain itu laporan sendiri atau yang ia tangani. */
    public function scopeVisibleTo(Builder $query, PortalUser $user): Builder
    {
        if (Access::canViewAll($user)) {
            return $query;
        }

        return $query->where(function (Builder $w) use ($user) {
            $w->where('created_by_id', (string) $user->id);

            if (filled($user->employee_no)) {
                $w->orWhere('reporter_nik', $user->employee_no)
                    ->orWhere('investigator_nik', $user->employee_no)
                    ->orWhereExists(fn ($s) => $s->from('corrective_actions')
                        ->whereColumn('corrective_actions.source_id', 'incidents.id')
                        ->where('corrective_actions.source_type', 'incident')
                        ->where('corrective_actions.pic_nik', $user->employee_no));
            }
        });
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['closed', 'cancelled']);
    }

    public function canBeSeenBy(PortalUser $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

    public function typeMeta(): array
    {
        return config('ssap.incident_types.'.$this->type, ['label' => $this->type, 'short' => $this->type, 'group' => 'asset', 'color' => '#64748b', 'lti' => false, 'recordable' => false, 'injury' => false]);
    }

    public function statusMeta(): array
    {
        return config('ssap.incident_statuses.'.$this->status, ['label' => $this->status, 'color' => 'secondary', 'icon' => 'bi-circle']);
    }

    public function severityMeta(): array
    {
        return config('ssap.severity.'.$this->severity, ['label' => '-', 'hint' => '', 'color' => '#64748b']);
    }

    public function riskMeta(): ?array
    {
        return $this->risk_level ? Risk::meta($this->risk_level) : null;
    }

    public function isActive(): bool
    {
        return ! in_array($this->status, ['closed', 'cancelled'], true);
    }

    /** Investigasi lewat tenggat. */
    public function investigationOverdue(): bool
    {
        return $this->isActive()
            && in_array($this->status, ['submitted', 'investigating'], true)
            && $this->investigation_due
            && $this->investigation_due->lt(today());
    }

    /** Jam antara kejadian dan dilaporkan (untuk ukuran ketepatan pelaporan). */
    public function reportDelayHours(): int
    {
        return (int) max(0, $this->occurred_at->diffInHours($this->created_at, false));
    }

    public function notifyLimitHours(): int
    {
        $limits = config('ssap.notify_hours');

        return (int) ($limits[$this->type] ?? $limits['default']);
    }

    public function reportedLate(): bool
    {
        return $this->reportDelayHours() > $this->notifyLimitHours();
    }
}
