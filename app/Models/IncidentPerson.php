<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentPerson extends Model
{
    protected $table = 'incident_persons';

    protected $guarded = ['id'];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function roleLabel(): string
    {
        return config('ssap.person_roles.'.$this->role, $this->role);
    }
}
