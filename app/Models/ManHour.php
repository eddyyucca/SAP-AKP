<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManHour extends Model
{
    protected $fillable = ['year', 'month', 'location_id', 'hours', 'headcount', 'updated_by'];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
