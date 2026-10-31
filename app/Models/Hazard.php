<?php

namespace App\Models;

use Akp\PortalSso\PortalUser;
use App\Models\Concerns\HasSafetyRecord;
use App\Support\Access;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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

    /** Hazard yang boleh dilihat: semua (admin/HSE/manajer, grade 6+); tim (grade 5); selain itu laporan sendiri atau yang ia tangani (PIC). */
    public function scopeVisibleTo(Builder $query, PortalUser $user): Builder
    {
        $scope = Access::scope($user);
        if ($scope['mode'] === 'all') {
            return $query;
        }

        return $query->where(function (Builder $w) use ($user, $scope) {
            $w->where('created_by_id', (string) $user->id);

            if ($scope['mode'] === 'team') {
                $w->orWhereIn('reporter_nik', $scope['niks']);
            }

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

    /** Kolom penilaian risiko lama tetap ada di database; SAP tidak lagi menilai risiko, jadi diisi nilai tetap. */
    protected static function booted(): void
    {
        static::creating(function (self $h) {
            $h->likelihood ??= 3;
            $h->consequence ??= 2;
            $h->risk_score ??= 6;
            $h->risk_level ??= 'medium';
            $h->title = $h->title ?: Str::limit(trim((string) $h->description), 150);
            $h->category = $h->category ?: 'kta';
            // Pelapor = PIC hazard (perbaikan dikerjakan manual; pelapor yang mencatat dan menutup).
            $h->pic_nik ??= $h->reporter_nik;
            $h->pic_name ??= $h->reporter_name;
            $h->pic_department ??= $h->reporter_department;
            $h->pic_email ??= $h->reporter_email;
        });
    }

    public function categoryColor(): string
    {
        return config('ssap.hazard_category_colors.'.$this->category, '#64748b');
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
