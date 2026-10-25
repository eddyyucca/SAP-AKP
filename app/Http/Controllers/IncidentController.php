<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesRecords;
use App\Models\Incident;
use App\Models\Location;
use App\Services\HrisEmployees;
use App\Services\Notifier;
use App\Support\Access;
use App\Support\Numbering;
use App\Support\Risk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IncidentController extends Controller
{
    use ManagesRecords;

    /** Perpindahan status yang diizinkan. */
    private const TRANSITIONS = [
        'submitted' => ['investigating', 'cancelled'],
        'investigating' => ['follow_up', 'cancelled'],
        'follow_up' => ['closed', 'investigating'],
        'closed' => ['follow_up'],
        'cancelled' => ['submitted'],
    ];

    public function __construct(private readonly Notifier $notifier, private readonly HrisEmployees $hris)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'max:20'],
            'location' => ['nullable', 'integer'],
            'severity' => ['nullable', 'integer', 'between:1,5'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'scope' => ['nullable', 'in:open,late'],
        ]);

        $query = Incident::query()->visibleTo(portal_user())->with('location:id,name')
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(fn ($w) => $w->where('number', 'like', "%{$term}%")->orWhere('title', 'like', "%{$term}%")
                    ->orWhere('reporter_name', 'like', "%{$term}%")->orWhere('area_detail', 'like', "%{$term}%"));
            })
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['location'] ?? null, fn ($q, $v) => $q->where('location_id', $v))
            ->when($filters['severity'] ?? null, fn ($q, $v) => $q->where('severity', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('occurred_at', '>=', $v.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('occurred_at', '<=', $v.' 23:59:59'))
            ->when(($filters['scope'] ?? null) === 'open', fn ($q) => $q->open())
            ->when(($filters['scope'] ?? null) === 'late', fn ($q) => $q->whereIn('status', ['submitted', 'investigating'])->whereDate('investigation_due', '<', today()));

        return view('ssap.incidents.index', [
            'incidents' => $query->orderByDesc('occurred_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'locations' => Location::ordered()->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('ssap.incidents.form', $this->formData(new Incident(['occurred_at' => now(), 'severity' => 1])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $persons = $data['persons'] ?? [];
        $reporter = $this->reporterSnapshot($request);

        $incident = Numbering::create('INC', Incident::class, function (string $number) use ($data, $persons, $reporter) {
            return DB::transaction(function () use ($number, $data, $persons, $reporter) {
                $incident = Incident::create($this->attributes($data, $persons) + $reporter + [
                    'number' => $number,
                    'status' => 'submitted',
                    'investigation_due' => now()->addDays($this->investigationDays($data['type']))->toDateString(),
                ]);
                $this->syncPersons($incident, $persons);

                return $incident;
            });
        });

        $files = $this->storeAttachments($incident, 'incident', $request);
        $incident->log('created', 'Laporan dibuat'.($files ? " dengan {$files} lampiran" : ''));

        $this->notifier->incidentReported($incident->fresh());

        return redirect()->route('ssap.incidents.show', $incident)
            ->with('success', "Laporan {$incident->number} berhasil dikirim ke tim HSE.");
    }

    public function show(Incident $incident)
    {
        $this->authorizeView($incident);
        $incident->load(['location', 'persons', 'actions', 'attachments', 'logs']);

        return view('ssap.incidents.show', [
            'incident' => $incident,
            'canManage' => Access::canManage(),
            'canEdit' => $this->canEdit($incident),
            'transitions' => Access::canManage() ? self::TRANSITIONS[$incident->status] ?? [] : [],
            'pendingActions' => $incident->actions->whereIn('status', ['open', 'in_progress', 'done'])->count(),
        ]);
    }

    public function print(Incident $incident)
    {
        $this->authorizeView($incident);
        $incident->load(['location', 'persons', 'actions', 'attachments']);

        return view('ssap.incidents.print', ['incident' => $incident]);
    }

    public function edit(Incident $incident)
    {
        abort_unless($this->canEdit($incident), 403, 'Laporan ini tidak dapat diubah lagi.');
        $incident->load('persons');

        return view('ssap.incidents.form', $this->formData($incident));
    }

    public function update(Request $request, Incident $incident): RedirectResponse
    {
        abort_unless($this->canEdit($incident), 403, 'Laporan ini tidak dapat diubah lagi.');

        $data = $this->validated($request);
        $persons = $data['persons'] ?? [];

        DB::transaction(function () use ($incident, $data, $persons) {
            $incident->update($this->attributes($data, $persons));
            $this->syncPersons($incident, $persons);
        });

        $files = $this->storeAttachments($incident, 'incident', $request);
        $incident->log('updated', 'Isi laporan diperbarui'.($files ? " (+{$files} lampiran)" : ''));

        return redirect()->route('ssap.incidents.show', $incident)->with('success', 'Laporan diperbarui.');
    }

    /** Simpan hasil investigasi: investigator, tenggat, akar masalah, analisis 5-Why, dampak, pelaporan ke otoritas. */
    public function investigate(Request $request, Incident $incident): RedirectResponse
    {
        abort_unless(Access::canManage(), 403);
        abort_if(in_array($incident->status, ['closed', 'cancelled'], true), 403, 'Laporan sudah ditutup. Buka kembali untuk mengubah investigasi.');

        $data = $request->validate($this->personRules('investigator', false) + [
            'investigation_due' => ['nullable', 'date'],
            'immediate_cause' => ['nullable', 'string', 'max:3000'],
            'root_cause_category' => ['nullable', $this->inList('ssap.root_cause_categories')],
            'root_cause' => ['nullable', 'string', 'max:3000'],
            'contributing_factors' => ['nullable', 'string', 'max:3000'],
            'lessons_learned' => ['nullable', 'string', 'max:3000'],
            'why' => ['nullable', 'array', 'max:5'],
            'why.*' => ['nullable', 'string', 'max:500'],
            'reported_to_authority' => ['nullable', 'boolean'],
            'authority_note' => ['nullable', 'string', 'max:200'],
            'lost_days' => ['nullable', 'integer', 'min:0', 'max:36500'],
            'cost_estimate' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ]);

        $investigator = $this->resolvePerson($data['investigator_nik'] ?? null, $data['investigator_name'] ?? null, null, $data['investigator_email'] ?? null);
        $newInvestigator = filled($investigator['name']) && $investigator['name'] !== $incident->investigator_name;

        $update = [
            'investigator_nik' => $investigator['nik'],
            'investigator_name' => $investigator['name'],
            'investigator_email' => $investigator['email'],
            'investigation_due' => $data['investigation_due'] ?? $incident->investigation_due,
            'immediate_cause' => $this->clean($data['immediate_cause'] ?? null),
            'root_cause_category' => $data['root_cause_category'] ?? null,
            'root_cause' => $this->clean($data['root_cause'] ?? null),
            'contributing_factors' => $this->clean($data['contributing_factors'] ?? null),
            'lessons_learned' => $this->clean($data['lessons_learned'] ?? null),
            'why_chain' => array_values(array_filter(array_map(fn ($w) => $this->clean($w), $data['why'] ?? []))) ?: null,
            'reported_to_authority' => (bool) ($data['reported_to_authority'] ?? false),
            'authority_note' => $this->clean($data['authority_note'] ?? null),
            'cost_estimate' => $data['cost_estimate'] ?? $incident->cost_estimate,
        ];

        if (array_key_exists('lost_days', $data) && $data['lost_days'] !== null) {
            $update['lost_days'] = (int) $data['lost_days'];
        }

        $startedNow = false;
        if ($incident->status === 'submitted' && filled($investigator['name'])) {
            $update['status'] = 'investigating';
            $update['investigation_started_at'] = now();
            $startedNow = true;
        }

        $incident->update($update);
        $incident->log('investigation', $startedNow ? 'Investigasi dimulai, investigator: '.$investigator['name'] : 'Hasil investigasi diperbarui');

        if ($newInvestigator) {
            $this->notifier->investigatorAssigned($incident->fresh());
        }
        if ($startedNow) {
            $this->notifier->incidentStatusChanged($incident->fresh(), 'Investigasi dimulai oleh '.$investigator['name']);
        }

        return back()->with('success', 'Data investigasi disimpan.');
    }

    public function transition(Request $request, Incident $incident): RedirectResponse
    {
        abort_unless(Access::canManage(), 403);

        $data = $request->validate([
            'to' => ['required', Rule::in(array_keys(config('ssap.incident_statuses')))],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $to = $data['to'];
        $note = $this->clean($data['note'] ?? null);

        if (! in_array($to, self::TRANSITIONS[$incident->status] ?? [], true)) {
            throw ValidationException::withMessages(['to' => 'Perpindahan status ini tidak diizinkan dari status saat ini.']);
        }

        $from = $incident->status;
        $update = ['status' => $to];

        switch ($to) {
            case 'investigating':
                if (blank($incident->investigator_name)) {
                    throw ValidationException::withMessages(['to' => 'Tetapkan investigator di bagian Investigasi lebih dulu.']);
                }
                if (! $incident->investigation_started_at) {
                    $update['investigation_started_at'] = now();
                }
                $update['closed_at'] = null;
                break;

            case 'follow_up':
                if ($incident->status === 'investigating' && blank($incident->root_cause)) {
                    throw ValidationException::withMessages(['to' => 'Isi akar masalah (root cause) di bagian Investigasi sebelum masuk tindak lanjut.']);
                }
                $update['closed_at'] = null;
                $update['closed_by'] = null;
                break;

            case 'closed':
                if (blank($incident->root_cause)) {
                    throw ValidationException::withMessages(['to' => 'Laporan tidak bisa ditutup sebelum akar masalah diisi.']);
                }
                if ($incident->actions()->whereIn('status', ['open', 'in_progress', 'done'])->exists()) {
                    throw ValidationException::withMessages(['to' => 'Masih ada tindakan korektif yang belum terverifikasi. Selesaikan atau hapus dulu.']);
                }
                if (blank($note)) {
                    throw ValidationException::withMessages(['note' => 'Catatan penutupan wajib diisi.']);
                }
                $update += ['closed_at' => now(), 'closed_by' => portal_user()->name, 'closure_note' => $note];
                break;

            case 'cancelled':
                if (blank($note)) {
                    throw ValidationException::withMessages(['note' => 'Alasan pembatalan wajib diisi.']);
                }
                $update['cancel_reason'] = $note;
                break;

            case 'submitted':
                $update['cancel_reason'] = null;
                break;
        }

        $incident->update($update);

        $event = match (true) {
            $to === 'closed' => 'closed',
            $to === 'cancelled' => 'cancelled',
            in_array($from, ['closed', 'cancelled'], true) => 'reopened',
            default => 'status',
        };
        $incident->log($event, config("ssap.incident_statuses.{$to}.label").($note ? ': '.$note : ''));

        $this->notifier->incidentStatusChanged($incident->fresh(), (string) $note);

        return back()->with('success', 'Status laporan diubah menjadi '.config("ssap.incident_statuses.{$to}.label").'.');
    }

    public function destroy(Incident $incident): RedirectResponse
    {
        abort_unless(Access::isAdmin(), 403);

        $number = $incident->number;
        $incident->log('cancelled', 'Laporan dihapus oleh administrator');
        $incident->delete();

        return redirect()->route('ssap.incidents.index')->with('success', "Laporan {$number} dihapus.");
    }

    /** Admin/HSE kapan saja selama belum ditutup; pelapor hanya saat masih "Dilaporkan". */
    private function canEdit(Incident $incident): bool
    {
        if (in_array($incident->status, ['closed', 'cancelled'], true)) {
            return false;
        }

        if (Access::canManage()) {
            return true;
        }

        $user = portal_user();

        return $incident->status === 'submitted' && (string) $incident->created_by_id === (string) $user->id;
    }

    private function formData(Incident $incident): array
    {
        $me = $this->hris->find(portal_user()->employee_no);

        return [
            'incident' => $incident,
            'locations' => Location::active()->ordered()->get(['id', 'name']),
            'me' => $me,
            'hrisReady' => HrisEmployees::configured(),
        ];
    }

    private function validated(Request $request): array
    {
        // Baris orang kosong (belum diisi) dibuang sebelum divalidasi.
        $request->merge(['persons' => array_values(array_filter((array) $request->input('persons', []), fn ($p) => is_array($p) && filled($p['name'] ?? null)))]);

        $data = $request->validate([
            'type' => ['required', $this->inList('ssap.incident_types')],
            'severity' => ['required', 'integer', 'between:1,5'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'location_id' => ['required', Rule::exists('locations', 'id')->where('is_active', true)],
            'area_detail' => ['nullable', 'string', 'max:160'],
            'activity' => ['nullable', 'string', 'max:160'],
            'equipment' => ['nullable', 'string', 'max:160'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'immediate_action' => ['nullable', 'string', 'max:3000'],
            'likelihood' => ['nullable', 'integer', 'between:1,5', 'required_with:consequence'],
            'consequence' => ['nullable', 'integer', 'between:1,5', 'required_with:likelihood'],
            'cost_estimate' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'environmental_impact' => ['nullable', 'string', 'max:2000'],
            'reporter_department' => ['nullable', 'string', 'max:120'],
            'reporter_position' => ['nullable', 'string', 'max:120'],
            'persons' => ['nullable', 'array', 'max:20'],
            'persons.*.role' => ['required', $this->inList('ssap.person_roles')],
            'persons.*.name' => ['required', 'string', 'max:120'],
            'persons.*.nik' => ['nullable', 'string', 'max:30'],
            'persons.*.company' => ['nullable', 'string', 'max:120'],
            'persons.*.department' => ['nullable', 'string', 'max:120'],
            'persons.*.position' => ['nullable', 'string', 'max:120'],
            'persons.*.injury_type' => ['nullable', 'string', 'max:80'],
            'persons.*.body_part' => ['nullable', 'string', 'max:80'],
            'persons.*.treatment' => ['nullable', 'string', 'max:1000'],
            'persons.*.lost_days' => ['nullable', 'integer', 'min:0', 'max:36500'],
        ] + $this->fileRules());

        $injury = config("ssap.incident_types.{$data['type']}.injury");
        if ($injury && ! collect($data['persons'] ?? [])->contains('role', 'korban')) {
            throw ValidationException::withMessages(['persons' => 'Jenis kejadian ini melibatkan cedera: tambahkan minimal satu korban.']);
        }

        return $data;
    }

    private function attributes(array $data, array $persons): array
    {
        $victims = collect($persons)->where('role', 'korban');
        $score = isset($data['likelihood'], $data['consequence']) ? Risk::score((int) $data['likelihood'], (int) $data['consequence']) : null;

        return [
            'type' => $data['type'],
            'severity' => (int) $data['severity'],
            'occurred_at' => $data['occurred_at'],
            'location_id' => $data['location_id'],
            'area_detail' => $this->clean($data['area_detail'] ?? null),
            'activity' => $this->clean($data['activity'] ?? null),
            'equipment' => $this->clean($data['equipment'] ?? null),
            'title' => trim($data['title']),
            'description' => trim($data['description']),
            'immediate_action' => $this->clean($data['immediate_action'] ?? null),
            'likelihood' => $data['likelihood'] ?? null,
            'consequence' => $data['consequence'] ?? null,
            'risk_score' => $score,
            'risk_level' => $score ? Risk::level($score) : null,
            'cost_estimate' => $data['cost_estimate'] ?? null,
            'environmental_impact' => $this->clean($data['environmental_impact'] ?? null),
            'injured_count' => $victims->count(),
            'lost_days' => (int) $victims->sum(fn ($p) => (int) ($p['lost_days'] ?? 0)),
        ];
    }

    private function syncPersons(Incident $incident, array $persons): void
    {
        $incident->persons()->delete();

        foreach ($persons as $person) {
            // Korban/saksi dari HRIS: identitas diambil dari HRIS, bukan dari isian browser.
            $employee = $this->hris->find($person['nik'] ?? null);

            $incident->persons()->create([
                'role' => $person['role'],
                'nik' => $employee['nik'] ?? $this->clean($person['nik'] ?? null),
                'name' => $employee['name'] ?? trim($person['name']),
                'company' => $this->clean($person['company'] ?? null) ?? ($employee ? config('ssap.company') : null),
                'department' => $employee['department'] ?? $this->clean($person['department'] ?? null),
                'position' => $employee['position'] ?? $this->clean($person['position'] ?? null),
                'injury_type' => $this->clean($person['injury_type'] ?? null),
                'body_part' => $this->clean($person['body_part'] ?? null),
                'treatment' => $this->clean($person['treatment'] ?? null),
                'lost_days' => (int) ($person['lost_days'] ?? 0),
            ]);
        }
    }

    private function investigationDays(string $type): int
    {
        $days = config('ssap.investigation_days');

        return (int) ($days[$type] ?? $days['default']);
    }
}
