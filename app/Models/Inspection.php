<?php

namespace App\Models;

use Akp\PortalSso\PortalUser;
use App\Models\Concerns\HasSafetyRecord;
use App\Support\Access;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inspection extends Model
{
    use HasSafetyRecord, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = ['inspected_at' => 'datetime', 'score' => 'float'];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplate::class, 'template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InspectionItem::class)->orderBy('position');
    }

    /** Admin/HSE/Manajer/grade 6+ melihat semua; grade 5 inspeksi timnya; selain itu miliknya sendiri. */
    public function scopeVisibleTo(Builder $query, PortalUser $user): Builder
    {
        $scope = Access::scope($user);
        if ($scope['mode'] === 'all') {
            return $query;
        }

        return $query->where(function (Builder $w) use ($user, $scope) {
            $w->where('created_by_id', (string) $user->id);
            if ($scope['mode'] === 'team') {
                $w->orWhereIn('inspector_nik', $scope['niks']);
            }
            if (filled($user->employee_no)) {
                $w->orWhere('inspector_nik', $user->employee_no);
            }
        });
    }

    public function canBeSeenBy(PortalUser $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

    /** Warna/label nilai kepatuhan: >=90 baik, >=75 cukup, selebihnya perlu perbaikan. */
    public function scoreMeta(): array
    {
        return self::scoreBand($this->score);
    }

    public static function scoreBand(?float $score): array
    {
        return match (true) {
            $score === null => ['label' => 'N/A', 'color' => 'secondary'],
            $score >= config('ssap.inspection_score.good') => ['label' => 'Baik', 'color' => 'success'],
            $score >= config('ssap.inspection_score.fair') => ['label' => 'Cukup', 'color' => 'warning'],
            default => ['label' => 'Perlu perbaikan', 'color' => 'danger'],
        };
    }
}
