<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesRecords;
use App\Models\Hazard;
use App\Models\Location;
use App\Services\HrisEmployees;
use App\Services\Notifier;
use App\Support\Access;
use App\Support\Numbering;
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
            'location' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'scope' => ['nullable', 'in:open,late'],
        ]);

        $query = Hazard::query()->visibleTo(portal_user())->with('location:id,name')
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where(fn ($w) => $w->where('number', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('reporter_name', 'like', "%{$term}%")->orWhere('pic_name', 'like', "%{$term}%")->orWhere('area_detail', 'like', "%{$term}%"));
            })
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['location'] ?? null, fn ($q, $v) => $q->where('location_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('observed_at', '>=', $v.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('observed_at', '<=', $v.' 23:59:59'))
            ->when(($filters['scope'] ?? null) === 'open', fn ($q) => $q->open())
            ->when(($filters['scope'] ?? null) === 'late', fn ($q) => $q->open()->whereDate('due_date', '<', today()));

        return view('ssap.hazards.index', [
            'hazards' => $query->orderByDesc('observed_at')->orderByDesc('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'locations' => Location::ordered()->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('ssap.hazards.form', $this->formData(new Hazard(['observed_at' => now(), 'category' => 'kta'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $reporter = $this->reporterSnapshot($request);

        $hazard = Numbering::create('HZD', Hazard::class, fn (string $number) => Hazard::create(
            $this->attributes($data) + $reporter + [
                'number' => $number,
                'status' => 'open',
                'due_date' => now()->addDays((int) config('ssap.hazard_sla_days'))->toDateString(),
            ]
        ));

        $files = $this->storeAttachments($hazard, 'hazard', $request, 'files', 'before');
        $hazard->log('created', 'Laporan dibuat'.($files ? " dengan {$files} lampiran" : ''));

        $this->notifier->hazardReported($hazard->fresh());

        return redirect()->route('ssap.hazards.show', $hazard)
            ->with('success', "Hazard {$hazard->number} berhasil dilaporkan. Terima kasih sudah peduli keselamatan.");
    }

    public function show(Hazard $hazard)
    {
        $this->authorizeView($hazard);
        $hazard->load(['location', 'attachments', 'logs']);

        return view('ssap.hazards.show', [
            'hazard' => $hazard,
            'canClose' => Access::canClose($hazard),
            'canEdit' => $this->canEdit($hazard),
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

        $files = $this->storeAttachments($hazard, 'hazard', $request, 'files', 'before');
        $hazard->log('updated', 'Isi laporan diperbarui'.($files ? " (+{$files} lampiran)" : ''));

        return redirect()->route('ssap.hazards.show', $hazard)->with('success', 'Laporan diperbarui.');
    }

    /**
     * Pelapor (= PIC) mencatat perkembangan perbaikan. Perbaikan itu sendiri dikerjakan secara manual
     * di lapangan; sistem hanya mencatat. Mencatat tindakan perbaikan selesai = laporan ditutup. Safety hanya memantau.
     *  in_progress : perbaikan sedang berjalan (catatan opsional)
     *  closed      : perbaikan selesai (tindakan perbaikan wajib dicatat, foto bukti disarankan)
     *  open        : buka kembali bila perbaikan ternyata belum tuntas
     */
    public function resolve(Request $request, Hazard $hazard): RedirectResponse
    {
        abort_unless(Access::canClose($hazard), 403, 'Hanya pelapor hazard ini yang dapat memperbarui tindakan perbaikannya.');

        $data = $request->validate([
            'to' => ['required', Rule::in(['in_progress', 'closed', 'open'])],
            'note' => ['nullable', 'string', 'max:3000'],
        ] + $this->fileRules());
        $note = $this->clean($data['note'] ?? null);

        switch ($data['to']) {
            case 'in_progress':
                abort_unless($hazard->status === 'open', 403);
                $hazard->update(['status' => 'in_progress']);
                $files = $this->storeAttachments($hazard, 'hazard', $request, 'files', 'after');
                $hazard->log('updated', 'Perbaikan sedang dikerjakan'.($note ? ': '.$note : '').($files ? " (+{$files} foto)" : ''));
                $message = 'Status diperbarui: perbaikan sedang dikerjakan.';
                break;

            case 'closed':
                abort_unless($hazard->isActive(), 403);
                if (blank($note)) {
                    throw ValidationException::withMessages(['note' => 'Tuliskan tindakan perbaikan yang sudah dilakukan.']);
                }
                $hazard->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => portal_user()->name, 'closure_note' => $note, 'reject_reason' => null]);
                $files = $this->storeAttachments($hazard, 'hazard', $request, 'files', 'after');
                $hazard->log('closed', 'Tindakan perbaikan: '.$note.($files ? " (+{$files} foto)" : ''));
                $this->notifier->hazardClosed($hazard->fresh());
                $message = 'Tindakan perbaikan dicatat dan hazard ditutup. Terima kasih.';
                break;

            default:   // open
                abort_if($hazard->isActive(), 403);
                $hazard->update(['status' => 'open', 'closed_at' => null, 'closed_by' => null, 'closure_note' => null, 'reject_reason' => null]);
                $hazard->log('reopened', $note ?: 'Dibuka kembali oleh pelapor');
                $message = 'Hazard dibuka kembali.';
        }

        return back()->with('success', $message);
    }

    public function destroy(Hazard $hazard): RedirectResponse
    {
        abort_unless(Access::isAdmin(), 403);

        $number = $hazard->number;
        $hazard->log('cancelled', 'Laporan dihapus oleh administrator');
        $hazard->delete();

        return redirect()->route('ssap.hazards.index')->with('success', "Hazard {$number} dihapus.");
    }

    /** Pelapor (atau administrator) selama laporan masih aktif. */
    private function canEdit(Hazard $hazard): bool
    {
        return $hazard->isActive() && Access::canClose($hazard);
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
            'description' => ['required', 'string', 'min:5', 'max:5000'],
            'immediate_action' => ['nullable', 'string', 'max:3000'],
            'suggestion' => ['nullable', 'string', 'max:3000'],
        ] + $this->fileRules());
    }

    private function attributes(array $data): array
    {
        return [
            'observed_at' => $data['observed_at'],
            'location_id' => $data['location_id'],
            'area_detail' => $this->clean($data['area_detail'] ?? null),
            'category' => $data['category'],
            'title' => \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', $data['description'])), 150),
            'description' => trim($data['description']),
            'immediate_action' => $this->clean($data['immediate_action'] ?? null),
            'suggestion' => $this->clean($data['suggestion'] ?? null),
        ];
    }
}
