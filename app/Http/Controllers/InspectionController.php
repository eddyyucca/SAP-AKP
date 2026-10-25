<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesRecords;
use App\Models\Hazard;
use App\Models\Inspection;
use App\Models\InspectionTemplate;
use App\Models\Location;
use App\Services\HrisEmployees;
use App\Services\InspectionStats;
use App\Services\Notifier;
use App\Support\Access;
use App\Support\Numbering;
use App\Support\Risk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Inspeksi rutin: isi checklist per template; temuan tidak sesuai otomatis menjadi hazard. */
class InspectionController extends Controller
{
    use ManagesRecords;

    public function __construct(private readonly Notifier $notifier, private readonly HrisEmployees $hris)
    {
    }

    public function dashboard(Request $request)
    {
        $filters = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100'], 'location' => ['nullable', 'integer']]);
        $year = (int) ($filters['year'] ?? now()->year);
        $locationId = isset($filters['location']) ? (int) $filters['location'] : null;
        $stats = InspectionStats::forYear($year, $locationId);

        return view('ssap.inspections.dashboard', [
            'year' => $year,
            'years' => range(now()->year, now()->year - 5),
            'locationId' => $locationId,
            'locations' => Location::ordered()->get(['id', 'name']),
            'kpi' => $stats->kpis(),
            'charts' => $stats->charts(),
            'templates' => $stats->byTemplate(),
            'locationRows' => $stats->byLocation()['rows'],
            'topFindings' => $stats->topFindings(),
            'recent' => $stats->recent(),
        ]);
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'template' => ['nullable', 'integer'],
            'location' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'result' => ['nullable', 'in:findings,clean'],
        ]);

        $query = Inspection::query()->visibleTo(portal_user())->with('location:id,name')
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('number', 'like', "%{$t}%")->orWhere('inspector_name', 'like', "%{$t}%")->orWhere('area_detail', 'like', "%{$t}%")))
            ->when($filters['template'] ?? null, fn ($q, $v) => $q->where('template_id', $v))
            ->when($filters['location'] ?? null, fn ($q, $v) => $q->where('location_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('inspected_at', '>=', $v.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('inspected_at', '<=', $v.' 23:59:59'))
            ->when(($filters['result'] ?? null) === 'findings', fn ($q) => $q->where('nok_count', '>', 0))
            ->when(($filters['result'] ?? null) === 'clean', fn ($q) => $q->where('nok_count', 0));

        return view('ssap.inspections.index', [
            'inspections' => $query->orderByDesc('inspected_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'locations' => Location::ordered()->get(['id', 'name']),
            'templates' => InspectionTemplate::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request)
    {
        $templates = InspectionTemplate::active()->withCount('items')->orderBy('name')->get();
        $template = $request->filled('template') ? InspectionTemplate::active()->with('items')->find((int) $request->query('template')) : null;

        return view('ssap.inspections.form', [
            'templates' => $templates,
            'template' => $template,
            'locations' => Location::active()->ordered()->get(['id', 'name']),
            'me' => $this->hris->find(portal_user()->employee_no),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = InspectionTemplate::active()->with('items')->findOrFail((int) $request->input('template_id'));

        $data = $request->validate([
            'template_id' => ['required', 'integer'],
            'location_id' => ['required', Rule::exists('locations', 'id')->where('is_active', true)],
            'area_detail' => ['nullable', 'string', 'max:160'],
            'inspected_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'inspector_department' => ['nullable', 'string', 'max:120'],
            'result' => ['required', 'array'],
            'result.*' => ['required', 'in:ok,nok,na'],
            'note' => ['nullable', 'array'],
            'note.*' => ['nullable', 'string', 'max:1000'],
        ] + $this->fileRules(), [
            'result.*.required' => 'Semua butir pemeriksaan harus dinilai.',
        ]);

        // Setiap butir template harus dinilai; temuan (tidak sesuai) wajib diberi catatan.
        $errors = [];
        foreach ($template->items as $item) {
            $res = $data['result'][$item->id] ?? null;
            if (! in_array($res, ['ok', 'nok', 'na'], true)) {
                $errors['result'] = 'Semua butir pemeriksaan harus dinilai.';
                break;
            }
            if ($res === 'nok' && blank($data['note'][$item->id] ?? null)) {
                $errors['note'] = 'Temuan "Tidak sesuai" wajib diberi catatan (apa yang ditemukan).';
            }
        }
        if ($errors) {
            return back()->withInput()->withErrors($errors);
        }

        $request->merge(['reporter_department' => $request->input('inspector_department')]);
        $inspector = $this->reporterSnapshot($request);

        $counts = ['ok' => 0, 'nok' => 0, 'na' => 0];
        foreach ($template->items as $item) {
            $counts[$data['result'][$item->id]]++;
        }
        $checked = $counts['ok'] + $counts['nok'];

        $createdHazards = [];

        $inspection = Numbering::create('INS', Inspection::class, function (string $number) use ($template, $data, $inspector, $counts, $checked, &$createdHazards) {
            return DB::transaction(function () use ($number, $template, $data, $inspector, $counts, $checked, &$createdHazards) {
                $inspection = Inspection::create([
                    'number' => $number,
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                    'category' => $template->category,
                    'location_id' => $data['location_id'],
                    'area_detail' => $this->clean($data['area_detail'] ?? null),
                    'inspected_at' => $data['inspected_at'],
                    'inspector_nik' => $inspector['reporter_nik'],
                    'inspector_name' => $inspector['reporter_name'],
                    'inspector_department' => $inspector['reporter_department'],
                    'inspector_email' => $inspector['reporter_email'],
                    'created_by_id' => $inspector['created_by_id'],
                    'total_items' => $template->items->count(),
                    'ok_count' => $counts['ok'], 'nok_count' => $counts['nok'], 'na_count' => $counts['na'],
                    'score' => $checked > 0 ? round($counts['ok'] * 100 / $checked, 1) : null,
                    'notes' => $this->clean($data['notes'] ?? null),
                ]);

                foreach ($template->items as $item) {
                    $result = $data['result'][$item->id];
                    $note = $this->clean($data['note'][$item->id] ?? null);
                    $hazardId = null;

                    if ($result === 'nok') {
                        $hazard = $this->hazardFromFinding($inspection, $item->question, (string) $note, $inspector);
                        $createdHazards[] = $hazard;
                        $hazardId = $hazard->id;
                    }

                    $inspection->items()->create(['position' => $item->position, 'question' => $item->question, 'result' => $result, 'note' => $note, 'hazard_id' => $hazardId]);
                }

                return $inspection;
            });
        });

        $files = $this->storeAttachments($inspection, 'inspection', $request);
        $inspection->log('created', "Inspeksi selesai: skor {$inspection->score}%, {$counts['nok']} temuan".($files ? ", {$files} lampiran" : ''));

        foreach ($createdHazards as $hazard) {
            $hazard->log('created', "Dibuat otomatis dari temuan inspeksi {$inspection->number}");
            $this->notifier->hazardReported($hazard->fresh());
        }

        return redirect()->route('ssap.inspections.show', $inspection)
            ->with('success', "Inspeksi {$inspection->number} tersimpan".($createdHazards ? ' dan '.count($createdHazards).' temuan masuk sebagai hazard.' : '. Tidak ada temuan.'));
    }

    public function show(Inspection $inspection)
    {
        $this->authorizeView($inspection);
        $inspection->load(['location', 'items.hazard:id,number,status,risk_level', 'attachments', 'logs']);

        return view('ssap.inspections.show', ['inspection' => $inspection, 'canManage' => Access::canManage()]);
    }

    public function destroy(Inspection $inspection): RedirectResponse
    {
        abort_unless(Access::isAdmin(), 403);

        $number = $inspection->number;
        $inspection->delete();

        return redirect()->route('ssap.inspections.index')->with('success', "Inspeksi {$number} dihapus.");
    }

    /** Temuan "tidak sesuai" menjadi laporan hazard (risiko awal Sedang; HSE menilai ulang). */
    private function hazardFromFinding(Inspection $inspection, string $question, string $note, array $inspector): Hazard
    {
        $likelihood = 3;
        $consequence = 2;
        $score = Risk::score($likelihood, $consequence);
        $level = Risk::level($score);

        return Numbering::create('HZD', Hazard::class, fn (string $number) => Hazard::create([
            'number' => $number,
            'observed_at' => $inspection->inspected_at,
            'location_id' => $inspection->location_id,
            'area_detail' => $inspection->area_detail,
            'category' => array_key_exists($inspection->category, config('ssap.hazard_categories')) ? $inspection->category : 'other',
            'title' => \Illuminate\Support\Str::limit('Temuan inspeksi: '.$question, 155),
            'description' => "Ditemukan saat {$inspection->template_name} ({$inspection->number}).\nButir: {$question}\nTemuan: {$note}",
            'likelihood' => $likelihood, 'consequence' => $consequence, 'risk_score' => $score, 'risk_level' => $level,
            'reporter_nik' => $inspector['reporter_nik'], 'reporter_name' => $inspector['reporter_name'],
            'reporter_department' => $inspector['reporter_department'], 'reporter_position' => $inspector['reporter_position'],
            'reporter_email' => $inspector['reporter_email'], 'created_by_id' => $inspector['created_by_id'],
            'status' => 'open',
            'due_date' => now()->addDays(Risk::slaDays($level))->toDateString(),
        ]));
    }
}
