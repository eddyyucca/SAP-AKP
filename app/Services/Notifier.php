<?php

namespace App\Services;

use App\Models\Hazard;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Notifikasi email SAP lewat AKP Mail. Setiap metode aman dipanggil di alur bisnis:
 * galat apa pun ditelan dan dilaporkan ke log, tidak pernah menggagalkan penyimpanan laporan.
 *
 * Konsep: pelapor = PIC hazard; perbaikan dikerjakan secara manual dan dicatat pelapor.
 * Tim safety (HSE) hanya memantau, jadi menerima informasi, bukan tugas.
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
                'Ada laporan hazard baru untuk dipantau. Pelapor bertindak sebagai PIC dan akan mencatat tindakan perbaikannya.',
                $this->hazardRows($hazard) + ['Tenggat' => $hazard->due_date?->translatedFormat('d F Y')],
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
                    'Terima kasih sudah peduli keselamatan. Anda adalah PIC laporan ini: setelah perbaikan selesai dikerjakan, catat tindakan perbaikannya di SAP agar laporan ditutup sebelum tenggat.',
                    $this->hazardRows($hazard) + ['Tenggat' => $hazard->due_date?->translatedFormat('d F Y')],
                    route('ssap.hazards.show', $hazard),
                    '#203868',
                    'hazard',
                    $hazard->number,
                    'Yth. '.$hazard->reporter_name.',',
                );
            }
        });
    }

    /** Pelapor mencatat tindakan perbaikan dan menutup laporan: kabar untuk pemantau (HSE). */
    public function hazardClosed(Hazard $hazard): void
    {
        $this->safely(function () use ($hazard) {
            $this->deliver(
                Setting::emails('notify_hse'),
                'Hazard '.$hazard->number.' selesai diperbaiki',
                'Hazard selesai diperbaiki',
                'Pelapor mencatat tindakan perbaikan dan menutup hazard berikut.',
                $this->hazardRows($hazard) + [
                    'Tindakan perbaikan' => $hazard->closure_note,
                    'Ditutup' => $hazard->closed_at?->translatedFormat('d F Y').' oleh '.$hazard->closed_by,
                    'Ketepatan' => $hazard->due_date && $hazard->closed_at && $hazard->closed_at->copy()->startOfDay()->gt($hazard->due_date) ? 'Terlambat' : 'Tepat waktu',
                ],
                route('ssap.hazards.show', $hazard),
                '#15803d',
                'hazard',
                $hazard->number,
            );
        });
    }

    /** Pengingat harian ke pelapor (PIC) untuk hazard yang mendekati/melewati tenggat. */
    public function remindHazard(Hazard $hazard): void
    {
        $this->safely(function () use ($hazard) {
            $late = $hazard->isOverdue();
            $this->deliver(
                [$hazard->pic_email ?: $hazard->reporter_email],
                ($late ? '[TERLAMBAT] ' : '[Pengingat] ').'Hazard '.$hazard->number,
                $late ? 'Hazard melewati tenggat' : 'Pengingat tenggat hazard',
                $late
                    ? 'Hazard yang Anda laporkan sudah melewati tenggat dan belum ditutup. Bila perbaikan sudah dikerjakan, mohon catat tindakan perbaikannya di SAP.'
                    : 'Hazard yang Anda laporkan segera jatuh tempo. Bila perbaikan sudah dikerjakan, catat tindakan perbaikannya di SAP.',
                $this->hazardRows($hazard) + ['Tenggat' => $hazard->due_date?->translatedFormat('d F Y'), 'Status' => $hazard->statusMeta()['label']],
                route('ssap.hazards.show', $hazard),
                $late ? '#b91c1c' : '#ea580c',
                'reminder',
                $hazard->number,
                'Yth. '.($hazard->pic_name ?: $hazard->reporter_name).',',
            );
        });
    }

    /** Ringkasan harian untuk HSE: pantauan yang butuh perhatian. */
    public function digest(array $stats): void
    {
        $this->safely(function () use ($stats) {
            $this->deliver(
                Setting::emails('notify_hse'),
                'Ringkasan harian SAP - '.now()->translatedFormat('d F Y'),
                'Ringkasan harian SAP',
                'Berikut hal-hal yang perlu dipantau hari ini.',
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
            'Dilaporkan oleh (PIC)' => $h->reporter_name.($h->reporter_department ? ' ('.$h->reporter_department.')' : ''),
            'Tindakan segera' => $h->immediate_action,
        ];
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
