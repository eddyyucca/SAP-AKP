<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\Setting;
use App\Services\AkpMail;
use App\Services\HrisEmployees;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Pengaturan notifikasi dan status integrasi HRIS / AKP Mail (admin). */
class SettingController extends Controller
{
    public function show(HrisEmployees $hris, AkpMail $mail)
    {
        return view('ssap.settings.index', [
            'emails' => [
                'notify_hse' => Setting::value('notify_hse', ''),
                'notify_manager' => Setting::value('notify_manager', ''),
                'notify_severe' => Setting::value('notify_severe', ''),
            ],
            'notifyReporter' => Setting::value('notify_reporter') === '1',
            'remindDays' => (int) Setting::value('remind_days_before', '2'),
            'hrisConfigured' => HrisEmployees::configured(),
            'hrisSyncedAt' => $hris->syncedAt(),
            'mailConfigured' => $mail->configured(),
            'logs' => EmailLog::latest('id')->limit(15)->get(['id', 'category', 'to', 'subject', 'status', 'channel', 'error', 'attempts', 'created_at']),
            'portalUrl' => config('sso.portal_url'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'notify_hse' => ['nullable', 'string', 'max:1000'],
            'notify_manager' => ['nullable', 'string', 'max:1000'],
            'notify_severe' => ['nullable', 'string', 'max:1000'],
            'notify_reporter' => ['nullable', 'boolean'],
            'remind_days_before' => ['required', 'integer', 'between:0,14'],
        ]);

        foreach (['notify_hse', 'notify_manager', 'notify_severe'] as $key) {
            $list = preg_split('/[\s,;]+/', (string) ($data[$key] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
            $invalid = array_filter($list, fn ($e) => ! filter_var($e, FILTER_VALIDATE_EMAIL));

            if ($invalid) {
                return back()->withInput()->withErrors([$key => 'Alamat email tidak valid: '.implode(', ', $invalid)]);
            }

            Setting::put($key, implode(', ', array_unique(array_map('strtolower', $list))));
        }

        Setting::put('notify_reporter', ! empty($data['notify_reporter']) ? '1' : '0');
        Setting::put('remind_days_before', (string) $data['remind_days_before']);

        return back()->with('success', 'Pengaturan disimpan.');
    }

    public function testHris(HrisEmployees $hris): RedirectResponse
    {
        $result = $hris->health();

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function syncHris(HrisEmployees $hris): RedirectResponse
    {
        if (! HrisEmployees::configured()) {
            return back()->with('error', $hris->health()['message']);
        }

        try {
            $count = $hris->refresh();
        } catch (\Throwable $e) {
            return back()->with('error', 'Sinkron HRIS gagal: '.str($e->getMessage())->limit(160));
        }

        return back()->with('success', 'Sinkron HRIS selesai: '.number_format($count, 0, ',', '.').' karyawan.');
    }

    public function testMail(Request $request, AkpMail $mail): RedirectResponse
    {
        $health = $mail->health();
        $to = portal_user()->email;

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return back()->with('error', 'Akun Portal Anda belum punya alamat email untuk menerima email uji. '.$health['message']);
        }

        $html = view('emails.notice', [
            'heading' => 'Email uji SAP Safety', 'intro' => 'Jika Anda menerima email ini, notifikasi SAP Safety lewat AKP Mail berfungsi.',
            'rows' => ['Dikirim oleh' => portal_user()->name, 'Waktu' => now()->translatedFormat('d F Y H:i')], 'url' => route('ssap.home'), 'tone' => '#203868',
        ])->render();

        $log = $mail->send($to, 'Email uji SAP Safety', $html, 'test');

        return back()->with($log && $log->status !== 'failed' ? 'success' : 'error', $log && $log->status !== 'failed'
            ? "Email uji dikirim ke {$to} (status: {$log->status}). ".$health['message']
            : 'Email uji gagal dikirim: '.($log?->error ?? $health['message']));
    }

    public function refreshMail(AkpMail $mail): RedirectResponse
    {
        $changed = $mail->refreshStatuses();

        return back()->with('success', $changed ? "{$changed} status email diperbarui." : 'Tidak ada perubahan status email.');
    }

    public function resendMail(EmailLog $emailLog, AkpMail $mail): RedirectResponse
    {
        abort_unless($emailLog->status === 'failed', 403);

        $log = $mail->deliver($emailLog);

        return back()->with($log->status !== 'failed' ? 'success' : 'error', $log->status !== 'failed' ? 'Email dikirim ulang.' : 'Gagal lagi: '.$log->error);
    }
}
