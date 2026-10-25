<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = ['actor_id', 'actor_name', 'event', 'note'];

    /** Label peristiwa untuk tampilan riwayat. */
    public const EVENTS = [
        'created' => ['Laporan dibuat', 'bi-plus-circle', 'primary'],
        'updated' => ['Laporan diperbarui', 'bi-pencil', 'secondary'],
        'status' => ['Status berubah', 'bi-arrow-repeat', 'info'],
        'investigation' => ['Investigasi diperbarui', 'bi-search', 'warning'],
        'action_added' => ['Tindakan ditambahkan', 'bi-list-check', 'primary'],
        'action_updated' => ['Tindakan diperbarui', 'bi-hourglass-split', 'warning'],
        'action_verified' => ['Tindakan diverifikasi', 'bi-patch-check', 'success'],
        'attachment' => ['Lampiran ditambahkan', 'bi-paperclip', 'secondary'],
        'lpi' => ['LPI kontraktor', 'bi-file-earmark-text', 'primary'],
        'closed' => ['Laporan ditutup', 'bi-check2-circle', 'success'],
        'cancelled' => ['Laporan dibatalkan', 'bi-x-circle', 'secondary'],
        'reopened' => ['Laporan dibuka kembali', 'bi-arrow-counterclockwise', 'warning'],
    ];

    public function meta(): array
    {
        return self::EVENTS[$this->event] ?? [$this->event, 'bi-dot', 'secondary'];
    }
}
