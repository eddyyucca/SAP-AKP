<?php

namespace App\Services;

use App\Models\CorrectiveAction;
use App\Models\Hazard;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Notifikasi email SAP lewat AKP Mail. Setiap metode aman dipanggil di alur bisnis:
 * galat apa pun ditelan dan dilaporkan ke log, tidak pernah menggagalkan penyimpanan laporan.
 */
class Notifier
{
    public function __construct(private readonly AkpMail $mail)
    {
    }

    public function hazardReported(Hazard $hazard): void
    {
        $this->safely(function () use ($hazard) {
            $this->deliver(
                Setting::emails('notify_hse'),
                'Hazard baru '.$hazard->number.' ('.strtoupper($hazard->category).')',
                'Laporan hazard baru',
                'Ada laporan bahaya (hazard) baru. Tetapkan PIC dan tenggat penyelesaiannya.',
                $this->hazardRows($hazard),
                route('ssap.hazards.show', $hazard),
                $hazard->categoryColor(),
                'hazard',
                $hazard->number,
            );

            if (Setting::value('notify_reporter') === '1') {
                $this->deliver(
                    [$hazard->reporter_email],
                    'Laporan hazard Anda diterima: '.$hazard->number,
                    'Laporan hazard diterima',
                    'Terima kasih sudah peduli keselamatan. Laporan Anda sudah diterima dan akan ditindaklanjuti.',
                    $this->hazardRows($hazard),
                    route('ssap.hazards.show', $hazard),
                    '#203868',
                    'hazard',
                    $hazard->number,
                    'Yth. '.$hazard->reporter_name.',',
                );
            }
        });
    }

    public function hazardAssigned(Hazard $hazard): void
    {
        $this->safely(function () use ($hazard) {
            $this->deliver(
                [$hazard->pic_email],
                'Anda ditugaskan menangani hazard '.$hazard->number,
                'Penugasan penanganan hazard',
                'Anda ditunjuk sebagai penanggung jawab (PIC) hazard berikut. Mohon selesaikan sebelum tenggat.',
                $this->hazardRows($hazard) + ['Tenggat' => $hazard->due_date?->translatedFormat('d F Y')],
                route('ssap.hazards.show', $hazard),
                $hazard->categoryColor(),
                'hazard',
                $hazard->number,
                'Yth. '.$hazard->pic_name.',',
            );
        });
    }

    public function hazardClosed(Hazard $hazard): void
    {
        $this->safely(function () use ($hazard) {
            $status = $hazard->statusMeta()['label'];
            $closed = $hazard->status === 'closed';
            // Ditutup pelapor: kabari PIC dan HSE. Ditolak HSE: kabari pelapor.
            $this->deliver(
                $closed ? array_merge([$hazard->pic_email], Setting::emails('notify_hse')) : [$hazard->reporter_email],
                'Hazard '.$hazard->number.' '.mb_strtolower($status),
                'Laporan hazard '.mb_strtolower($status),
                $closed
                    ? 'Pelapor sudah memastikan perbaikan dan menutup hazard berikut. Terima kasih atas penanganannya.'
                    : 'Laporan hazard Anda tidak dilanjutkan. Alasannya tertera di bawah.',
                ['Nomor' => $hazard->number, 'Keterangan' => Str::limit($hazard->description, 200), 'Status' => $status, 'Catatan' => $hazard->closure_note ?: $hazard->reject_reason],
                route('ssap.hazards.show', $hazard),
                '#15803d',
                'hazard',
                $hazard->number,
                $closed ? null : 'Yth. '.$hazard->reporter_name.',',
            );
        });
    }

    public function actionAssigned(CorrectiveAction $action): void
    {
        $this->safely(function () use ($action) {
            $this->deliver(
                [$action->pic_email],
                'Tindakan safety untuk Anda: '.$this->sourceNumber($action),
                'Tindakan safety ditugaskan',
                'Anda ditugaskan mengerjakan tindakan berikut. Mohon perbarui statusnya di SAP saat dikerjakan dan selesai.',
                $this->actionRows($action),
                route('ssap.actions.index', ['view' => 'mine']),
                '#ea580c',
                'action',
                $this->sourceNumber($action),
                'Yth. '.$action->pic_name.',',
            );
        });
    }

    /** Tindakan ditandai selesai: minta PELAPOR hazard memverifikasi (HSE ditembuskan). */
    public function actionDone(CorrectiveAction $action): void
    {
        $this->safely(function () use ($action) {
            $this->deliver(
                array_merge([$action->source?->reporter_email], Setting::emails('notify_hse')),
                'Tindakan selesai, mohon diverifikasi: '.$this->sourceNumber($action),
                'Tindakan menunggu verifikasi Anda',
                "{$action->pic_name} menandai tindakan berikut selesai. Pelapor yang memastikan perbaikannya sudah benar: mohon periksa di lapangan/buktinya lalu verifikasi di SAP.",
                $this->actionRows($action) + ['Catatan penyelesaian' => $action->completion_note],
                route('ssap.actions.index', ['view' => 'verify']),
                '#203868',
                'action',
                $this->sourceNumber($action),
            );
        });
    }

    public function actionVerified(CorrectiveAction $action, bool $accepted): void
    {
        $this->safely(function () use ($action, $accepted) {
            $this->deliver(
                [$action->pic_email],
                ($accepted ? 'Tindakan terverifikasi: ' : 'Tindakan dikembalikan: ').$this->sourceNumber($action),
                $accepted ? 'Tindakan terverifikasi' : 'Tindakan perlu diperbaiki',
                $accepted ? 'Terima kasih, tindakan Anda sudah diverifikasi pelapor.' : 'Pelapor mengembalikan tindakan ini untuk diperbaiki. Lihat catatan di bawah.',
                $this->actionRows($action) + ['Catatan pelapor' => $action->verification_note],
                route('ssap.actions.index', ['view' => 'mine']),
                $accepted ? '#15803d' : '#ea580c',
                'action',
                $this->sourceNumber($action),
                'Yth. '.$action->pic_name.',',
            );
        });
    }

    /** Pengingat harian untuk tindakan yang jatuh tempo/terlambat. */
    public function remindAction(CorrectiveAction $action): void
    {
        $this->safely(function () use ($action) {
            $late = $action->isOverdue();
            $this->deliver(
                [$action->pic_email],
                ($late ? '[TERLAMBAT] ' : '[Pengingat] ').'Tindakan safety '.$this->sourceNumber($action),
                $late ? 'Tindakan safety terlambat' : 'Pengingat tenggat tindakan safety',
                $late ? 'Tindakan berikut sudah melewati tenggat. Mohon segera diselesaikan atau hubungi HSE.' : 'Tindakan berikut segera jatuh tempo.',
                $this->actionRows($action),
                route('ssap.actions.index', ['view' => 'mine']),
                $late ? '#b91c1c' : '#ea580c',
                'reminder',
                $this->sourceNumber($action),
                'Yth. '.$action->pic_name.',',
            );
        });
    }

    /** Ringkasan harian untuk HSE: item yang butuh perhatian. */
    public function digest(array $stats): void
    {
        $this->safely(function () use ($stats) {
            $this->deliver(
                Setting::emails('notify_hse'),
                'Ringkasan harian SAP - '.now()->translatedFormat('d F Y'),
                'Ringkasan harian SAP',
                'Berikut hal-hal yang memerlukan perhatian HSE hari ini.',
                $stats,
                route('ssap.home'),
                '#203868',
                'digest',
                now()->format('Y-m-d'),
            );
        });
    }

    private function hazardRows(Hazard $h): array
    {
        $h->loadMissing('location');
        return [
            'Nomor' => $h->number,
            'Jenis' => $h->categoryLabel(),
            'Lokasi' => $h->location?->name.($h->area_detail ? ' - '.$h->area_detail : ''),
            'Keterangan' => Str::limit($h->description, 400),
            'Dilaporkan oleh' => $h->reporter_name.($h->reporter_department ? ' ('.$h->reporter_department.')' : ''),
            'Tindakan segera' => $h->immediate_action,
        ];
    }

    private function actionRows(CorrectiveAction $a): array
    {
        return [
            'Sumber' => $this->sourceNumber($a),
            'Jenis tindakan' => $a->typeLabel(),
            'Tindakan' => $a->description,
            'PIC' => $a->pic_name,
            'Tenggat' => $a->due_date->translatedFormat('d F Y'),
            'Status' => $a->statusMeta()['label'],
        ];
    }

    private function sourceNumber(CorrectiveAction $a): string
    {
        return (string) ($a->source?->number ?? strtoupper($a->source_type).'-'.$a->source_id);
    }

    /** @param  array<int, string|null>  $to */
    private function deliver(array $to, string $subject, string $heading, string $intro, array $rows, string $url, string $tone, string $category, ?string $related, ?string $greeting = null): void
    {
        $to = array_values(array_filter($to));
        if ($to === []) {
            return;
        }

        $html = view('emails.notice', [
            'heading' => $heading, 'intro' => $intro, 'rows' => $rows, 'url' => $url, 'tone' => $tone, 'greeting' => $greeting,
        ])->render();

        $this->mail->send($to, $subject, $html, $category, $related);
    }

    private function safely(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
