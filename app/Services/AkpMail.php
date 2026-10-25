<?php

namespace App\Services;

use App\Models\EmailLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * Klien AKP Mail (layanan email terpusat). Setiap email dicatat di email_logs.
 * Tidak pernah melempar galat ke pemanggil: kegagalan email tidak boleh menggagalkan proses safety (mis. membuat laporan).
 */
class AkpMail
{
    public function configured(): bool
    {
        return filled(config('mailsvc.url')) && filled(config('mailsvc.token'));
    }

    /**
     * Catat lalu kirim sesudah respons ke browser terkirim (tidak memperlambat halaman).
     *
     * @param  array<int, string>|string  $to
     */
    public function send(array|string $to, string $subject, string $html, string $category, ?string $related = null): ?EmailLog
    {
        $to = array_values(array_unique(array_filter(array_map(fn ($a) => strtolower(trim((string) $a)), (array) $to), fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL))));

        if ($to === []) {
            return null;
        }

        $log = EmailLog::create([
            'category' => $category, 'to' => implode(', ', $to), 'subject' => mb_substr($subject, 0, 200), 'body' => $html,
            'status' => 'pending', 'related' => $related,
        ]);

        if (app()->runningInConsole()) {
            $this->deliver($log);
        } else {
            dispatch(fn () => $this->deliver($log))->afterResponse();
        }

        return $log;
    }

    /** Kirim sekarang (dipakai tombol "Kirim ulang"/uji dan job). */
    public function deliver(EmailLog $log): EmailLog
    {
        $log->increment('attempts');
        $recipients = array_map('trim', explode(',', $log->to));

        try {
            if ($this->configured()) {
                $response = Http::withToken((string) config('mailsvc.token'))
                    ->acceptJson()
                    ->timeout((int) config('mailsvc.timeout'))
                    ->withHeaders(['Idempotency-Key' => 'ssap-'.$log->id.'-'.$log->attempts])
                    ->post(rtrim((string) config('mailsvc.url'), '/').'/api/v1/emails', [
                        'to' => $recipients, 'subject' => $log->subject, 'html' => $log->body,
                        'from_name' => config('mailsvc.from_name'),
                    ]);

                if (in_array($response->status(), [200, 202], true)) {
                    $log->update(['status' => 'queued', 'channel' => 'akpmail', 'remote_id' => data_get($response->json(), 'data.id'), 'error' => null]);
                } else {
                    $log->update(['status' => 'failed', 'channel' => 'akpmail', 'error' => 'AKP Mail menolak (HTTP '.$response->status().'): '.mb_substr($response->body(), 0, 300)]);
                }
            } else {
                Mail::html($log->body, fn ($m) => $m->to($recipients)->subject($log->subject));
                $log->update(['status' => 'sent', 'channel' => 'laravel', 'sent_at' => now(), 'error' => null]);
            }
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }

        return $log->fresh();
    }

    /** Perbarui status email yang masih "queued" di AKP Mail (queued -> sent / failed). */
    public function refreshStatuses(int $limit = 50): int
    {
        if (! $this->configured()) {
            return 0;
        }

        $changed = 0;

        EmailLog::where('status', 'queued')->whereNotNull('remote_id')->latest('id')->limit($limit)->get()->each(function (EmailLog $log) use (&$changed) {
            try {
                $res = Http::withToken((string) config('mailsvc.token'))->acceptJson()->timeout((int) config('mailsvc.timeout'))
                    ->get(rtrim((string) config('mailsvc.url'), '/').'/api/v1/emails/'.$log->remote_id);

                $remote = data_get($res->json(), 'data.status');

                if ($res->ok() && in_array($remote, ['sent', 'failed'], true)) {
                    $log->update([
                        'status' => $remote,
                        'sent_at' => $remote === 'sent' ? now() : null,
                        'error' => $remote === 'failed' ? (data_get($res->json(), 'data.error') ?: 'Gagal dikirim oleh AKP Mail.') : null,
                    ]);
                    $changed++;
                }
            } catch (\Throwable) {
                // dicoba lagi pada pembaruan berikutnya
            }
        });

        return $changed;
    }

    /** Cek koneksi dan token: daftar template hanya bisa dibaca dengan token yang valid. */
    public function health(): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'message' => 'MAILSVC_URL / MAILSVC_TOKEN belum diisi di .env. Email memakai pengaturan MAIL_* Laravel (di lokal: dicatat ke log).'];
        }

        try {
            $res = Http::withToken((string) config('mailsvc.token'))->acceptJson()->timeout(6)->get(rtrim((string) config('mailsvc.url'), '/').'/api/v1/ping');

            return match (true) {
                $res->ok() => ['ok' => true, 'message' => 'Tersambung ke AKP Mail dan token valid.'],
                in_array($res->status(), [401, 403], true) => ['ok' => false, 'message' => 'AKP Mail menolak token (HTTP '.$res->status().'). Buat ulang token di AKP Mail > Aplikasi.'],
                default => ['ok' => false, 'message' => 'AKP Mail menjawab HTTP '.$res->status().'.'],
            };
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'AKP Mail tidak terjangkau: '.mb_substr($e->getMessage(), 0, 160)];
        }
    }
}
