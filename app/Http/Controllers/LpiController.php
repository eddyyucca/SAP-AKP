<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesRecords;
use App\Models\Contractor;
use App\Models\Incident;
use App\Services\LpiStats;
use App\Services\Notifier;
use App\Support\Access;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Monitoring LPI (Laporan Penyelidikan Insiden) per kontraktor di area IUP / IUJP. */
class LpiController extends Controller
{
    use ManagesRecords;

    public function __construct(private readonly Notifier $notifier)
    {
    }

    public function index(Request $request)
    {
        [$stats, $filters] = $this->stats($request);

        return view('ssap.lpi.index', [
            'filters' => $filters,
            'years' => range(now()->year, now()->year - 5),
            'contractors' => Contractor::ordered()->get(['id', 'name', 'area_type']),
            'kpi' => $stats->kpis(),
            'rows' => $stats->byContractor(),
            'areas' => $stats->byArea(),
            'work' => $stats->worklist(),
            'charts' => $stats->charts(),
            'canManage' => Access::canManage(),
        ]);
    }

    /** Catat LPI diterima, setujui, atau minta revisi (HSE). */
    public function update(Request $request, Incident $incident): RedirectResponse
    {
        abort_unless(Access::canManage(), 403);
        abort_if($incident->lpi_status === null, 404, 'Insiden ini tidak mewajibkan LPI.');

        $data = $request->validate([
            'action' => ['required', 'in:receive,accept,revise'],
            'lpi_number' => ['nullable', 'string', 'max:80'],
            'lpi_submitted_at' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:2000'],
        ] + $this->fileRules());

        $note = $this->clean($data['note'] ?? null);
        $user = portal_user();

        switch ($data['action']) {
            case 'receive':
                if (! in_array($incident->lpi_status, ['pending', 'revision'], true)) {
                    throw ValidationException::withMessages(['action' => 'LPI sudah diterima dan sedang ditinjau.']);
                }
                if (blank($data['lpi_number'] ?? null)) {
                    throw ValidationException::withMessages(['lpi_number' => 'Nomor/judul dokumen LPI dari kontraktor wajib diisi.']);
                }

                $resubmit = $incident->lpi_status === 'revision';
                $incident->update([
                    'lpi_status' => 'submitted',
                    'lpi_number' => trim($data['lpi_number']),
                    // Ketepatan waktu dinilai dari penyampaian pertama; revisi tidak mengubah tanggal ini.
                    'lpi_submitted_at' => $incident->lpi_submitted_at ?? ($data['lpi_submitted_at'] ?? today()->toDateString()),
                    'lpi_revisions' => $incident->lpi_revisions + ($resubmit ? 1 : 0),
                    'lpi_note' => null,
                ]);
                $files = $this->storeAttachments($incident, 'incident', $request);
                $incident->log('lpi', ($resubmit ? 'LPI revisi diterima' : 'LPI diterima').': '.$incident->lpi_number.($files ? " (+{$files} berkas)" : ''));
                break;

            case 'accept':
                if ($incident->lpi_status !== 'submitted') {
                    throw ValidationException::withMessages(['action' => 'Hanya LPI yang sedang ditinjau yang dapat disetujui.']);
                }
                $incident->update(['lpi_status' => 'accepted', 'lpi_reviewed_at' => now(), 'lpi_reviewed_by' => $user->name, 'lpi_note' => $note]);
                $incident->log('lpi', 'LPI disetujui'.($note ? ': '.$note : ''));
                break;

            case 'revise':
                if ($incident->lpi_status !== 'submitted') {
                    throw ValidationException::withMessages(['action' => 'Hanya LPI yang sedang ditinjau yang dapat dikembalikan.']);
                }
                if (blank($note)) {
                    throw ValidationException::withMessages(['note' => 'Tuliskan bagian LPI yang perlu direvisi.']);
                }
                $incident->update(['lpi_status' => 'revision', 'lpi_reviewed_at' => now(), 'lpi_reviewed_by' => $user->name, 'lpi_note' => $note, 'lpi_reminded_on' => null]);
                $incident->log('lpi', 'LPI dikembalikan untuk revisi: '.$note);
                break;
        }

        if ($data['action'] !== 'receive') {
            $this->notifier->lpiReviewed($incident->fresh('contractor'));
        }

        return back()->with('success', match ($data['action']) {
            'receive' => 'LPI dicatat diterima dan menunggu peninjauan.',
            'accept' => 'LPI disetujui.',
            'revise' => 'LPI dikembalikan ke kontraktor untuk direvisi.',
        });
    }

    public function export(Request $request): StreamedResponse
    {
        [$stats] = $this->stats($request);
        $contractors = Contractor::query()->get()->keyBy('id');

        $rows = $stats->incidents()->sortBy('occurred_at')->map(function ($i) use ($contractors) {
            $c = $contractors[$i->contractor_id] ?? null;

            return [
                $c?->name, $c?->areaMeta()['label'], $i->number, $i->occurred_at->format('Y-m-d'), config("ssap.incident_types.{$i->type}.label"), $i->title,
                $i->lpi_status ? config("ssap.lpi_statuses.{$i->lpi_status}.label") : 'Tidak diwajibkan',
                $i->lpi_due?->format('Y-m-d'), $i->lpi_submitted_at?->format('Y-m-d'), $i->lpi_number,
                $i->lpiOnTime() === null ? '' : ($i->lpiOnTime() ? 'Ya' : 'Tidak'), $i->lpiOverdue() ? 'Ya' : 'Tidak', $i->lpi_revisions,
            ];
        })->values();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Kontraktor', 'Area', 'Nomor insiden', 'Tanggal kejadian', 'Jenis', 'Judul', 'Status LPI', 'Tenggat LPI', 'LPI diterima', 'Nomor LPI', 'Tepat waktu', 'Terlambat', 'Jumlah revisi'], ';');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@'], true) ? "'".$v : $v, $row), ';');
            }
            fclose($out);
        }, 'ssap-lpi-kontraktor-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0:LpiStats,1:array} */
    private function stats(Request $request): array
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'area' => ['nullable', 'in:iup,iujp'],
            'contractor' => ['nullable', 'integer'],
        ]);

        $year = (int) ($filters['year'] ?? now()->year);
        $filters['year'] = $year;

        return [new LpiStats(Carbon::create($year, 1, 1)->startOfDay(), Carbon::create($year, 12, 31)->endOfDay(), $filters['area'] ?? null, isset($filters['contractor']) ? (int) $filters['contractor'] : null), $filters];
    }
}
