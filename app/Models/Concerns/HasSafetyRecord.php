<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\CorrectiveAction;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Relasi bersama insiden dan hazard: tindakan korektif, lampiran, dan riwayat. */
trait HasSafetyRecord
{
    public function actions(): MorphMany
    {
        return $this->morphMany(CorrectiveAction::class, 'source')->orderBy('due_date');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('id');
    }

    public function logs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'loggable')->orderByDesc('id');
    }

    /** Catat peristiwa ke riwayat (siapa, apa, kapan). */
    public function log(string $event, ?string $note = null): ActivityLog
    {
        $user = portal_user();

        return $this->logs()->create([
            'actor_id' => $user?->id,
            'actor_name' => $user?->name ?? 'Sistem',
            'event' => $event,
            'note' => $note,
        ]);
    }
}
