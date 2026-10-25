<?php

namespace App\Services;

use App\Models\CorrectiveAction;
use App\Models\Hazard;
use App\Models\Incident;
use App\Models\Setting;
use App\Support\Risk;

/**
 * Notifikasi email SSAP Safety lewat AKP Mail. Setiap metode aman dipanggil di alur bisnis:
 * galat apa pun ditelan dan dilaporkan ke log, tidak pernah menggagalkan penyimpanan laporan.
 */
class Notifier
{
    public function __construct(private readonly AkpMail $mail)
    {
    }

    public function incidentReported(Incident $incident): void
    {
        $this->safely(function () use ($incident) {
            $meta = $incident->typeMeta();
            $severe = in_array($incident->type, config('ssap.severe_types', []), true);

            $to = array_merge(Setting::emails('notify_hse'), Setting::emails('notify_manager'));
            if ($severe) {
                $to = array_merge($to, Setting::emails('notify_severe'));
            }

            $this->deliver(
                $to,
                ($severe ? '[SEGERA] ' : '').'Laporan insiden '.$incident->number.' - '.$meta['label'],
                $severe ? 'INSIDEN BERAT DILAPORKAN' : 'Laporan insiden baru',
                'Laporan insiden baru memerlukan penanganan HSE. Segera tentukan investigator dan batas waktu investigasi.',
                $this->incidentRows($incident),
                route('ssap.incidents.show', $incident),
                $severe ? '#b91c1c' : '#203868',
                'incident',
                $incident->number,
            );

            if (Setting::value('notify_reporter') === '1') {
                $this->deliver(
                    [$incident->reporter_email],
                    'Laporan Anda diterima: '.$incident->number,
                    'Laporan insiden diterima',
                    'Terima kasih sudah melapor. Laporan Anda sudah diterima tim HSE dan akan ditindaklanjuti. Anda akan mendapat kabar saat statusnya berubah.',
                    $this->incidentRows($incident),
                    route('ssap.incidents.show', $incident),
                    '#203868',
                    'incident',
                    $incident->number,
                    'Yth. '.$incident->reporter_name.',',
                );
            }
        });
    }

    public function incidentStatusChanged(Incident $incident, string $note = ''): void
    {
        $this->safely(function () use ($incident, $note) {
            $status = $incident->statusMeta()['label'];
            $rows = ['Nomor' => $incident->number, 'Judul' => $incident->title, 'Status baru' => $status, 'Catatan' => $note];

            $this->deliver(
                [$incident->reporter_email, $incident->investigator_email],
                'Status laporan '.$incident->number.': '.$status,
                'Status laporan insiden berubah',
                "Laporan {$incident->number} kini berstatus {$status}.",
                $rows,
                route('ssap.incidents.show', $incident),
                $incident->status === 'closed' ? '#15803d' : '#203868',
                'incident',
                $incident->number,
            );
        });
    }

    public function investigatorAssigned(Incident $incident): void
    {
        $this->safely(function () use ($incident) {
            $this->deliver(
                [$incident->investigator_email],
                'Anda ditugaskan menginvestigasi '.$incident->number,
                'Penugasan investigasi',
                'Anda ditunjuk sebagai investigator laporan berikut. Mohon selesaikan investigasi sebelum batas waktu.',
                $this->incidentRows($incident) + ['Batas investigasi' => $incident->investigation_due?->translatedFormat('d F Y')],
                route('ssap.incidents.show', $incident),
                '#ea580c',
                'incident',
                $incident->number,
                'Yth. '.$incident->investigator_name.',',
            );
        });
    }

    public function hazardReported(Hazard $hazard): void
    {
        $this->safely(function () use ($hazard) {
            $risk = $hazard->riskMeta();
            $high = in_array($hazard->risk_level, ['high', 'extreme'], true);

            $to = Setting::emails('notify_hse');
            if ($hazard->risk_level === 'extreme') {
                $to = array_merge($to, Setting::emails('notify_manager'), Setting::emails('notify_severe'));
            }

            $this->deliver(
                $to,
                ($high ? '['.mb_strtoupper($risk['label']).'] ' : '').'Hazard baru '.$hazard->number,
                'Laporan hazard baru',
                'Ada laporan bahaya (hazard) baru. Tetapkan PIC dan tenggat penyelesaian sesuai tingkat risikonya.',
                $this->hazardRows($hazard),
                route('ssap.hazards.show', $hazard),
                $risk['color'],
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
                $hazard->riskMeta()['color'],
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
            $this->deliver(
                [$hazard->reporter_email],
                'Hazard '.$hazard->number.' '.mb_strtolower($status),
                'Laporan hazard '.mb_strtolower($status),
                $hazard->status === 'closed'
                    ? 'Hazard yang Anda laporkan sudah ditangani dan ditutup. Terima kasih atas kepedulian Anda.'
                    : 'Laporan hazard Anda tidak dilanjutkan. Alasannya tertera di bawah.',
                ['Nomor' => $hazard->number, 'Judul' => $hazard->title, 'Status' => $status, 'Catatan' => $hazard->closure_note ?: $hazard->reject_reason],
                route('ssap.hazards.show', $hazard),
                '#15803d',
                'hazard',
                $hazard->number,
                'Yth. '.$hazard->reporter_name.',',
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
                'Anda ditugaskan mengerjakan tindakan berikut. Mohon perbarui statusnya di SSAP Safety saat dikerjakan dan selesai.',
                $this->actionRows($action),
                route('ssap.actions.index', ['view' => 'mine']),
                '#ea580c',
                'action',
                $this->sourceNumber($action),
                'Yth. '.$action->pic_name.',',
            );
        });
    }

    /** Tindakan ditandai selesai: minta HSE memverifikasi. */
    public function actionDone(CorrectiveAction $action): void
    {
        $this->safely(function () use ($action) {
            $this->deliver(
                Setting::emails('notify_hse'),
                'Tindakan selesai, mohon diverifikasi: '.$this->sourceNumber($action),
                'Tindakan menunggu verifikasi',
                "{$action->pic_name} menandai tindakan berikut selesai. Mohon periksa buktinya lalu verifikasi.",
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
                $accepted ? 'Terima kasih, tindakan Anda sudah diverifikasi HSE.' : 'HSE mengembalikan tindakan ini untuk diperbaiki. Lihat catatan di bawah.',
                $this->actionRows($action) + ['Catatan HSE' => $action->verification_note],
                route('ssap.actions.index', ['view' => 'mine']),
                $accepted ? '#15803d' : '#ea580c',
                'action',
                $this->sourceNumber($action),
                'Yth. '.$action->pic_name.',',
            );
        });
    }

    /** Pengingat harian untuk tindakan yang jatuh tempo/terlambat. Kembali: jumlah email terkirim. */
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

    /** Kontraktor diminta menyampaikan LPI (insiden baru di areanya). */
    public function lpiRequested(Incident $incident): void
    {
        $this->safely(function () use ($incident) {
            $incident->loadMissing('contractor');
            if (! $incident->contractor || ! $incident->lpi_due) {
                return;
            }

            $this->deliver(
                [$incident->contractor->pic_email],
                'Mohon LPI insiden '.$incident->number.' - '.$incident->contractor->name,
                'Permintaan LPI (Laporan Penyelidikan Insiden)',
                'Terjadi insiden di area kerja perusahaan Anda. Mohon sampaikan LPI kepada tim HSE AKP paling lambat pada tanggal tenggat.',
                $this->incidentRows($incident) + ['Kontraktor' => $incident->contractor->name, 'Tenggat LPI' => $incident->lpi_due->translatedFormat('d F Y')],
                route('ssap.incidents.show', $incident),
                '#ea580c',
                'lpi',
                $incident->number,
                'Yth. '.($incident->contractor->pic_name ?: 'PIC Kontraktor').',',
            );
        });
    }

    /** Hasil tinjauan HSE atas LPI: disetujui atau perlu revisi. */
    public function lpiReviewed(Incident $incident): void
    {
        $this->safely(function () use ($incident) {
            $incident->loadMissing('contractor');
            $revise = $incident->lpi_status === 'revision';

            $this->deliver(
                [$incident->contractor?->pic_email],
                ($revise ? 'LPI perlu revisi: ' : 'LPI disetujui: ').$incident->number,
                $revise ? 'LPI dikembalikan untuk revisi' : 'LPI disetujui',
                $revise ? 'LPI Anda ditinjau dan perlu diperbaiki. Mohon kirim ulang LPI yang sudah direvisi.' : 'LPI Anda telah ditinjau dan disetujui. Terima kasih.',
                ['Insiden' => $incident->number.' - '.$incident->title, 'Kontraktor' => $incident->contractor?->name, 'Nomor LPI' => $incident->lpi_number, 'Catatan HSE' => $incident->lpi_note, 'Ditinjau oleh' => $incident->lpi_reviewed_by],
                route('ssap.incidents.show', $incident),
                $revise ? '#ea580c' : '#15803d',
                'lpi',
                $incident->number,
                'Yth. '.($incident->contractor?->pic_name ?: 'PIC Kontraktor').',',
            );
        });
    }

    public function lpiReminder(Incident $incident): void
    {
        $this->safely(function () use ($incident) {
            $incident->loadMissing('contractor');
            $late = $incident->lpiOverdue();

            $this->deliver(
                [$incident->contractor?->pic_email],
                ($late ? '[TERLAMBAT] ' : '[Pengingat] ').'LPI insiden '.$incident->number,
                $late ? 'LPI melewati tenggat' : 'Pengingat tenggat LPI',
                $late ? 'LPI untuk insiden berikut sudah melewati tenggat. Mohon segera disampaikan kepada HSE AKP.' : 'Tenggat penyampaian LPI untuk insiden berikut sudah dekat.',
                ['Insiden' => $incident->number.' - '.$incident->title, 'Kontraktor' => $incident->contractor?->name, 'Tenggat LPI' => $incident->lpi_due?->translatedFormat('d F Y'), 'Status' => $incident->lpiMeta()['label'] ?? '', 'Catatan HSE' => $incident->lpi_note],
                route('ssap.incidents.show', $incident),
                $late ? '#b91c1c' : '#ea580c',
                'reminder',
                $incident->number,
                'Yth. '.($incident->contractor?->pic_name ?: 'PIC Kontraktor').',',
            );
        });
    }

    /** Ringkasan harian untuk HSE: item yang butuh perhatian. */
    public function digest(array $stats): void
    {
        $this->safely(function () use ($stats) {
            $this->deliver(
                Setting::emails('notify_hse'),
                'Ringkasan harian SSAP Safety - '.now()->translatedFormat('d F Y'),
                'Ringkasan harian SSAP Safety',
                'Berikut hal-hal yang memerlukan perhatian HSE hari ini.',
                $stats,
                route('ssap.home'),
                '#203868',
                'digest',
                now()->format('Y-m-d'),
            );
        });
    }

    private function incidentRows(Incident $i): array
    {
        $i->loadMissing('location');

        return [
            'Nomor' => $i->number,
            'Jenis' => $i->typeMeta()['label'],
            'Keparahan' => $i->severity.' - '.$i->severityMeta()['label'],
            'Judul' => $i->title,
            'Waktu kejadian' => $i->occurred_at->translatedFormat('d F Y H:i'),
            'Lokasi' => $i->location?->name.($i->area_detail ? ' - '.$i->area_detail : ''),
            'Uraian' => \Illuminate\Support\Str::limit($i->description, 400),
            'Dilaporkan oleh' => $i->reporter_name.($i->reporter_department ? ' ('.$i->reporter_department.')' : ''),
        ];
    }

    private function hazardRows(Hazard $h): array
    {
        $h->loadMissing('location');
        $risk = $h->riskMeta();

        return [
            'Nomor' => $h->number,
            'Kategori' => $h->categoryLabel(),
            'Tingkat risiko' => $risk['label'].' (skor '.$h->risk_score.')',
            'Judul' => $h->title,
            'Lokasi' => $h->location?->name.($h->area_detail ? ' - '.$h->area_detail : ''),
            'Uraian' => \Illuminate\Support\Str::limit($h->description, 400),
            'Dilaporkan oleh' => $h->reporter_name.($h->reporter_department ? ' ('.$h->reporter_department.')' : ''),
            'Tindakan segera' => $h->immediate_action,
            'Arahan' => Risk::meta($h->risk_level)['action'],
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
