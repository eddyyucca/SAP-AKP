<?php

namespace App\Console\Commands;

use App\Models\CorrectiveAction;
use App\Models\Hazard;
use App\Models\Incident;
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

        // LPI kontraktor yang mendekati/melewati tenggat: ingatkan PIC kontraktor tiap hari sampai LPI diterima.
        Incident::query()->with('contractor')->withLpi()->whereIn('lpi_status', ['pending', 'revision'])
            ->whereDate('lpi_due', '<=', $window)
            ->where(fn ($q) => $q->whereNull('lpi_reminded_on')->orWhereDate('lpi_reminded_on', '<', today()))
            ->each(function (Incident $incident) use ($notifier, &$sent) {
                if (filled($incident->contractor?->pic_email)) {
                    $notifier->lpiReminder($incident);
                    $sent++;
                }
                $incident->forceFill(['lpi_reminded_on' => today()])->save();
            });

        $digest = [
            'Tindakan terlambat' => CorrectiveAction::overdue()->count(),
            'Tindakan menunggu verifikasi' => CorrectiveAction::where('status', 'done')->count(),
            'Investigasi melewati tenggat' => Incident::whereIn('status', ['submitted', 'investigating'])->whereDate('investigation_due', '<', today())->count(),
            'Insiden belum ada investigator' => Incident::where('status', 'submitted')->count(),
            'Hazard risiko tinggi/ekstrem terbuka' => Hazard::open()->whereIn('risk_level', ['high', 'extreme'])->count(),
            'LPI kontraktor terlambat' => Incident::lpiOverdue()->count(),
            'LPI menunggu peninjauan HSE' => Incident::where('lpi_status', 'submitted')->count(),
            'Hazard belum ada PIC' => Hazard::where('status', 'open')->whereNull('pic_name')->count(),
            'Hazard melewati tenggat' => Hazard::open()->whereDate('due_date', '<', today())->count(),
        ];

        if (array_sum($digest) > 0) {
            $notifier->digest(array_map(fn ($n) => (string) $n, $digest));
        }

        $refreshed = $mail->refreshStatuses();

        $this->info("Pengingat dikirim: {$sent}. Status email diperbarui: {$refreshed}.");

        return self::SUCCESS;
    }
}
