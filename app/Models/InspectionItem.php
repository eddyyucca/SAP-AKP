<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function hazard(): BelongsTo
    {
        return $this->belongsTo(Hazard::class);
    }
}
