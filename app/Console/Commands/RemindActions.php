<?php

namespace App\Console\Commands;

use App\Models\CorrectiveAction;
use App\Models\Hazard;
use App\Models\InspectionTemplate;
use App\Models\Setting;
use App\Services\AkpMail;
use App\Services\Notifier;
use Illuminate\Console\Command;

class RemindActions extends Command
{
    protected $signature = 'ssap:remind';

    protected $description = 'Kirim pengingat tindakan yang mendekati/melewati tenggat ke PIC, ringkasan harian ke HSE, dan perbarui status email';

    public function handle(Notifier $notifier, AkpMail $mail): int
    {
        $window = today()->addDays((int) Setting::value('remind_days_before', '2'));
        $sent = 0;

        CorrectiveAction::query()->with('source')
            ->whereIn('status', ['open', 'in_progress'])
            ->whereDate('due_date', '<=', $window)
            ->whereNotNull('pic_email')
            ->where(fn ($q) => $q->whereNull('reminded_on')->orWhereDate('reminded_on', '<', today()))
            ->each(function (CorrectiveAction $action) use ($notifier, &$sent) {
                $notifier->remindAction($action);
                $action->forceFill(['reminded_on' => today()])->save();
                $sent++;
            });

        $behind = InspectionTemplate::active()->where('target_per_month', '>', 0)->get()->filter(function ($t) {
            $done = \App\Models\Inspection::where('template_id', $t->id)->where('inspected_at', '>=', now()->startOfMonth())->count();
            // Peringatan hanya di paruh kedua bulan, saat target masih jauh tertinggal.
            return now()->day >= 15 && $done < ceil($t->target_per_month * now()->day / now()->daysInMonth);
        })->count();

        // Target pelaporan bulanan: diingatkan sejak tanggal 20 agar masih sempat dipenuhi.
        $targetBehind = now()->day >= 20 ? collect(app(\App\Services\TargetStats::class)->rows(now()))->where('achieved', false)->count() : 0;

        $digest = [
            'Tindakan terlambat' => CorrectiveAction::overdue()->count(),
            'Tindakan menunggu verifikasi' => CorrectiveAction::where('status', 'done')->count(),
            'Hazard terbuka (TTA + KTA)' => Hazard::open()->count(),
            'Hazard belum ada PIC' => Hazard::where('status', 'open')->whereNull('pic_name')->count(),
            'Hazard melewati tenggat' => Hazard::open()->whereDate('due_date', '<', today())->count(),
            'Template inspeksi tertinggal dari target bulan ini' => $behind,
            'Karyawan belum mencapai target pelaporan bulan ini' => $targetBehind,
        ];

        if (array_sum($digest) > 0) {
            $notifier->digest(array_map(fn ($n) => (string) $n, $digest));
        }

        $refreshed = $mail->refreshStatuses();

        $this->info("Pengingat dikirim: {$sent}. Status email diperbarui: {$refreshed}.");

        return self::SUCCESS;
    }
}
