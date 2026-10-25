<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesRecords;
use App\Models\CorrectiveAction;
use App\Models\Hazard;
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

class HazardController extends Controller
{
    use ManagesRecords;

    public function __construct(private readonly Notifier $notifier, private readonly HrisEmployees $hris)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'max:20'],
            'risk' => ['nullable', 'string', 'max:10'],
            'location' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'scope' => ['nullable', 'in:open,late,unassigned'],
        ]);

        $query = Hazard::query()->visibleTo(portal_user())->with('location:id,name')
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(fn ($w) => $w->where('number', 'like', "%{$term}%")->orWhere('title', 'like', "%{$term}%")
                    ->orWhere('reporter_name', 'like', "%{$term}%")->orWhere('pic_name', 'like', "%{$term}%")->orWhere('area_detail', 'like', "%{$term}%"));
            })
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['risk'] ?? null, fn ($q, $v) => $v === 'severe' ? $q->whereIn('risk_level', ['high', 'extreme']) : $q->where('risk_level', $v))
            ->when($filters['location'] ?? null, fn ($q, $v) => $q->where('location_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('observed_at', '>=', $v.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('observed_at', '<=', $v.' 23:59:59'))
            ->when(($filters['scope'] ?? null) === 'open', fn ($q) => $q->open())
            ->when(($filters['scope'] ?? null) === 'late', fn ($q) => $q->open()->whereDate('due_date', '<', today()))
            ->when(($filters['scope'] ?? null) === 'unassigned', fn ($q) => $q->where('status', 'open')->whereNull('pic_name'));

        return view('ssap.hazards.index', [
            'hazards' => $query->orderByDesc('observed_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'locations' => Location::ordered()->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('ssap.hazards.form', $this->formData(new Hazard(['observed_at' => now(), 'likelihood' => 3, 'consequence' => 2])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $reporter = $this->reporterSnapshot($request);

        $hazard = Numbering::create('HZD', Hazard::class, fn (string $number) => Hazard::create(
            $this->attributes($data) + $reporter + [
                'number' => $number,
                'status' => 'open',
                'due_date' => now()->addDays(Risk::slaDays(Risk::level(Risk::score((int) $data['likelihood'], (int) $data['consequence']))))->toDateString(),
            ]
        ));

        $files = $this->storeAttachments($hazard, 'hazard', $request);
        $hazard->log('created', 'Laporan dibuat'.($files ? " dengan {$files} lampiran" : ''));

        $this->notifier->hazardReported($hazard->fresh());

        return redirect()->route('ssap.hazards.show', $hazard)
            ->with('success', "Hazard {$hazard->number} berhasil dilaporkan. Terima kasih sudah peduli keselamatan.");
    }

    public function show(Hazard $hazard)
    {
        $this->authorizeView($hazard);
        $hazard->load(['location', 'actions', 'attachments', 'logs']);

        return view('ssap.hazards.show', [
            'hazard' => $hazard,
            'canManage' => Access::canManage(),
            'canEdit' => $this->canEdit($hazard),
            'pendingActions' => $hazard->actions->whereIn('status', ['open', 'in_progress', 'done'])->count(),
        ]);
    }

    public function edit(Hazard $hazard)
    {
        abort_unless($this->canEdit($hazard), 403, 'Laporan ini tidak dapat diubah lagi.');

        return view('ssap.hazards.form', $this->formData($hazard));
    }

    public function update(Request $request, Hazard $hazard): RedirectResponse
    {
        abort_unless($this->canEdit($hazard), 403, 'Laporan ini tidak dapat diubah lagi.');

        $data = $this->validated($request);
        $hazard->update($this->attributes($data));

        $files = $this->storeAttachments($hazard, 'hazard', $request);
        $hazard->log('updated', 'Isi laporan diperbarui'.($files ? " (+{$files} lampiran)" : ''));

        return redirect()->route('ssap.hazards.show', $hazard)->with('success', 'Laporan diperbarui.');
    }

    /** HSE menetapkan PIC dan tenggat; sekaligus membuat tindakan korektif agar progresnya terlacak. */
    public function assign(Request $request, Hazard $hazard): RedirectResponse
    {
        abort_unless(Access::canManage(), 403);
        abort_unless($hazard->isActive(), 403, 'Hazard ini sudah ditutup.');

        $data = $request->validate($this->personRules('pic') + [
            'due_date' => ['required', 'date', 'after_or_equal:today'],
            'action' => ['required', 'string', 'max:3000'],
            'type' => ['required', $this->inList('ssap.action_types')],
        ]);

        $pic = $this->resolvePerson($data['pic_nik'] ?? null, $data['pic_name'] ?? null, $data['pic_department'] ?? null, $data['pic_email'] ?? null);

        if (blank($pic['name'])) {
            throw ValidationException::withMessages(['pic_name' => 'Tentukan PIC yang menangani hazard ini.']);
        }

        DB::transaction(function () use ($hazard, $data, $pic, &$action) {
            $hazard->update([
                'pic_nik' => $pic['nik'], 'pic_name' => $pic['name'], 'pic_department' => $pic['department'], 'pic_email' => $pic['email'],
                'due_date' => $data['due_date'], 'status' => 'in_progress',
            ]);

            $action = $hazard->actions()->create([
                'type' => $data['type'], 'description' => trim($data['action']),
                'pic_nik' => $pic['nik'], 'pic_name' => $pic['name'], 'pic_department' => $pic['department'], 'pic_email' => $pic['email'],
                'due_date' => $data['due_date'], 'status' => 'open', 'created_by' => portal_user()->name,
            ]);
        });

        $hazard->log('action_added', "PIC {$pic['name']} ditetapkan, tenggat ".\Carbon\Carbon::parse($data['due_date'])->translatedFormat('d F Y'));

        $fresh = $hazard->fresh();
        $this->notifier->hazardAssigned($fresh);
        if ($action instanceof CorrectiveAction) {
            $this->notifier->actionAssigned($action->load('source'));
        }

        return back()->with('success', "Hazard ditugaskan kepada {$pic['name']}.");
    }

    /** Tutup langsung (tanpa menunggu tindakan) atau tolak laporan. */
    public function resolve(Request $request, Hazard $hazard): RedirectResponse
    {
        abort_unless(Access::canManage(), 403);

        $data = $request->validate([
            'to' => ['required', Rule::in(['closed', 'rejected', 'open'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $note = $this->clean($data['note'] ?? null);

        switch ($data['to']) {
            case 'closed':
                abort_unless($hazard->isActive(), 403);
                if ($hazard->actions()->whereIn('status', ['open', 'in_progress', 'done'])->exists()) {
                    throw ValidationException::withMessages(['to' => 'Masih ada tindakan yang belum terverifikasi. Selesaikan atau hapus dulu.']);
                }
                if (blank($note)) {
                    throw ValidationException::withMessages(['note' => 'Tuliskan hasil penanganan sebagai catatan penutupan.']);
                }
                $hazard->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => portal_user()->name, 'closure_note' => $note, 'reject_reason' => null]);
                $hazard->log('closed', $note);
                $this->notifier->hazardClosed($hazard->fresh());
                break;

            case 'rejected':
                abort_unless($hazard->status === 'open', 403);
                if (blank($note)) {
                    throw ValidationException::withMessages(['note' => 'Alasan penolakan wajib diisi.']);
                }
                $hazard->update(['status' => 'rejected', 'reject_reason' => $note]);
                $hazard->log('cancelled', 'Ditolak: '.$note);
                $this->notifier->hazardClosed($hazard->fresh());
                break;

            case 'open':
                abort_if($hazard->isActive(), 403);
                $hazard->update(['status' => $hazard->pic_name ? 'in_progress' : 'open', 'closed_at' => null, 'closed_by' => null, 'closure_note' => null, 'reject_reason' => null]);
                $hazard->log('reopened', $note ?: 'Dibuka kembali');
                break;
        }

        return back()->with('success', 'Status hazard diperbarui.');
    }

    public function destroy(Hazard $hazard): RedirectResponse
    {
        abort_unless(Access::isAdmin(), 403);

        $number = $hazard->number;
        $hazard->log('cancelled', 'Laporan dihapus oleh administrator');
        $hazard->delete();

        return redirect()->route('ssap.hazards.index')->with('success', "Hazard {$number} dihapus.");
    }

    /** Admin/HSE selama masih aktif; pelapor hanya selama belum ada PIC. */
    private function canEdit(Hazard $hazard): bool
    {
        if (! $hazard->isActive()) {
            return false;
        }

        if (Access::canManage()) {
            return true;
        }

        return $hazard->status === 'open' && blank($hazard->pic_name) && (string) $hazard->created_by_id === (string) portal_user()->id;
    }

    private function formData(Hazard $hazard): array
    {
        return [
            'hazard' => $hazard,
            'locations' => Location::active()->ordered()->get(['id', 'name']),
            'me' => $this->hris->find(portal_user()->employee_no),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'observed_at' => ['required', 'date', 'before_or_equal:now'],
            'location_id' => ['required', Rule::exists('locations', 'id')->where('is_active', true)],
            'area_detail' => ['nullable', 'string', 'max:160'],
            'category' => ['required', $this->inList('ssap.hazard_categories')],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'min:15', 'max:5000'],
            'likelihood' => ['required', 'integer', 'between:1,5'],
            'consequence' => ['required', 'integer', 'between:1,5'],
            'immediate_action' => ['nullable', 'string', 'max:3000'],
            'suggestion' => ['nullable', 'string', 'max:3000'],
            'reporter_department' => ['nullable', 'string', 'max:120'],
            'reporter_position' => ['nullable', 'string', 'max:120'],
        ] + $this->fileRules());
    }

    private function attributes(array $data): array
    {
        $score = Risk::score((int) $data['likelihood'], (int) $data['consequence']);

        return [
            'observed_at' => $data['observed_at'],
            'location_id' => $data['location_id'],
            'area_detail' => $this->clean($data['area_detail'] ?? null),
            'category' => $data['category'],
            'title' => trim($data['title']),
            'description' => trim($data['description']),
            'likelihood' => (int) $data['likelihood'],
            'consequence' => (int) $data['consequence'],
            'risk_score' => $score,
            'risk_level' => Risk::level($score),
            'immediate_action' => $this->clean($data['immediate_action'] ?? null),
            'suggestion' => $this->clean($data['suggestion'] ?? null),
        ];
    }
}
