<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contractor extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    public function areaMeta(): array
    {
        return config('ssap.contractor_areas.'.$this->area_type, ['label' => strtoupper((string) $this->area_type), 'full' => '', 'color' => '#64748b']);
    }
}
