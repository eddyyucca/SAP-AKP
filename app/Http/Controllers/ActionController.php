<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesRecords;
use App\Models\CorrectiveAction;
use App\Models\Hazard;
use App\Services\Notifier;
use App\Support\Access;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Tindakan korektif/pencegahan: ditetapkan HSE, dikerjakan PIC, diverifikasi dan ditutup oleh PELAPOR. */
class ActionController extends Controller
{
    use ManagesRecords;

    public function __construct(private readonly Notifier $notifier)
    {
    }

    public function index(Request $request)
    {
        $user = portal_user();
        $canViewAll = Access::canViewAll();

        $filters = $request->validate([
            'view' => ['nullable', 'in:mine,all,verify,overdue'],
            'status' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        // Pengguna biasa hanya melihat tindakan yang ditugaskan kepadanya, dan yang menunggu verifikasinya sebagai pelapor.
        $view = $canViewAll ? ($filters['view'] ?? 'all') : (($filters['view'] ?? null) === 'verify' ? 'verify' : 'mine');

        // Hazard yang saya laporkan: tindakan di dalamnya saya yang memverifikasi.
        $mineHazards = Hazard::query()->where(fn ($q) => $q->where('created_by_id', (string) $user->id)
            ->when(filled($user->employee_no), fn ($w) => $w->orWhere('reporter_nik', $user->employee_no)))->select('id');
        $verifyScope = fn ($q) => Access::isAdmin() ? $q : $q->where('source_type', 'hazard')->whereIn('source_id', $mineHazards);

        $query = CorrectiveAction::query()->with('source:id,number,title')
            ->when($view === 'mine', fn ($q) => $q->where('pic_nik', $user->employee_no ?: '__none__'))
            ->when($view === 'verify', fn ($q) => $verifyScope($q->where('status', 'done')))
            ->when($view === 'overdue', fn ($q) => $q->overdue())
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('description', 'like', "%{$term}%")->orWhere('pic_name', 'like', "%{$term}%")));

        $counts = [
            'mine' => CorrectiveAction::pending()->where('pic_nik', $user->employee_no ?: '__none__')->count(),
            'verify' => $verifyScope(CorrectiveAction::where('status', 'done'))->count(),
            'overdue' => $canViewAll ? CorrectiveAction::overdue()->count() : 0,
        ];

        return view('ssap.actions.index', [
            'actions' => $query->orderByRaw("case status when 'done' then 0 when 'open' then 1 when 'in_progress' then 1 else 2 end")->orderBy('due_date')->paginate(20)->withQueryString(),
            'view' => $view,
            'filters' => $filters,
            'counts' => $counts,
            'canViewAll' => $canViewAll,
        ]);
    }

    /** HSE menambah tindakan pada hazard. */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(Access::canManage(), 403);

        $data = $request->validate($this->personRules('pic') + [
            'source_type' => ['required', 'in:hazard'],
            'source_id' => ['required', 'integer'],
            'type' => ['required', $this->inList('ssap.action_types')],
            'description' => ['required', 'string', 'min:5', 'max:3000'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $source = Hazard::findOrFail($data['source_id']);

        if (! $source->isActive()) {
            throw ValidationException::withMessages(['description' => 'Laporan sumber sudah ditutup. Buka kembali sebelum menambah tindakan.']);
        }

        $pic = $this->resolvePerson($data['pic_nik'] ?? null, $data['pic_name'] ?? null, $data['pic_department'] ?? null, $data['pic_email'] ?? null);
        if (blank($pic['name'])) {
            throw ValidationException::withMessages(['pic_name' => 'Tentukan PIC yang mengerjakan tindakan ini.']);
        }

        $action = $source->actions()->create([
            'type' => $data['type'],
            'description' => trim($data['description']),
            'pic_nik' => $pic['nik'], 'pic_name' => $pic['name'], 'pic_department' => $pic['department'], 'pic_email' => $pic['email'],
            'due_date' => $data['due_date'],
            'status' => 'open',
            'created_by' => portal_user()->name,
        ]);

        // Hazard yang sudah punya tindakan = sedang ditangani.
        if ($source->status === 'open') {
            $source->update(['status' => 'in_progress', 'pic_nik' => $pic['nik'], 'pic_name' => $pic['name'], 'pic_department' => $pic['department'], 'pic_email' => $pic['email'], 'due_date' => $data['due_date']]);
        }

        $source->log('action_added', "Tindakan untuk {$pic['name']}, tenggat ".$action->due_date->translatedFormat('d F Y'));
        $this->notifier->actionAssigned($action->load('source'));

        return back()->with('success', "Tindakan ditugaskan kepada {$pic['name']}.");
    }

    /** PIC (atau HSE) memperbarui progres: dikerjakan atau selesai (dengan bukti). */
    public function progress(Request $request, CorrectiveAction $action): RedirectResponse
    {
        abort_unless($this->isPic($action) || Access::canManage(), 403);
        abort_unless(in_array($action->status, ['open', 'in_progress'], true), 403, 'Tindakan ini sudah diselesaikan.');

        $data = $request->validate([
            'to' => ['required', 'in:in_progress,done'],
            'completion_note' => ['nullable', 'string', 'max:3000'],
        ] + $this->fileRules());

        if ($data['to'] === 'done' && blank($data['completion_note'] ?? null)) {
            throw ValidationException::withMessages(['completion_note' => 'Jelaskan apa yang sudah dikerjakan.']);
        }

        $action->update([
            'status' => $data['to'],
            'completion_note' => $this->clean($data['completion_note'] ?? null) ?? $action->completion_note,
            'completed_at' => $data['to'] === 'done' ? now() : null,
        ]);

        $source = $action->source;
        $files = $source ? $this->storeAttachments($source, $action->source_type, $request) : 0;
        $source?->log('action_updated', ($data['to'] === 'done' ? 'Tindakan selesai dikerjakan' : 'Tindakan mulai dikerjakan').": {$action->pic_name}".($files ? " (+{$files} bukti)" : ''));

        if ($data['to'] === 'done') {
            $this->notifier->actionDone($action->fresh('source'));
        }

        return back()->with('success', $data['to'] === 'done' ? 'Tindakan ditandai selesai dan menunggu verifikasi pelapor.' : 'Status tindakan diperbarui.');
    }

    /** Pelapor memverifikasi bukti: terima (selesai) atau kembalikan ke PIC. */
    public function verify(Request $request, CorrectiveAction $action): RedirectResponse
    {
        abort_unless(Access::canClose($action->source), 403, 'Hanya pelapor hazard ini yang dapat memverifikasi tindakan.');
        abort_unless($action->status === 'done', 403, 'Hanya tindakan yang menunggu verifikasi yang dapat diverifikasi.');

        $data = $request->validate([
            'decision' => ['required', 'in:accept,reject'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $accepted = $data['decision'] === 'accept';
        $note = $this->clean($data['note'] ?? null);

        if (! $accepted && blank($note)) {
            throw ValidationException::withMessages(['note' => 'Tuliskan alasan pengembalian agar PIC tahu yang harus diperbaiki.']);
        }

        $action->update($accepted
            ? ['status' => 'verified', 'verified_at' => now(), 'verified_by' => portal_user()->name, 'verification_note' => $note]
            : ['status' => 'in_progress', 'completed_at' => null, 'verification_note' => $note]);

        $source = $action->source;
        $source?->log('action_verified', ($accepted ? 'Tindakan terverifikasi' : 'Tindakan dikembalikan').": {$action->pic_name}".($note ? " - {$note}" : ''));

        // Hazard otomatis selesai bila seluruh tindakannya sudah terverifikasi.
        if ($accepted && $source && $source->isActive() && ! $source->actions()->whereIn('status', ['open', 'in_progress', 'done'])->exists()) {
            $source->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => portal_user()->name, 'closure_note' => 'Seluruh tindakan telah dikerjakan dan diverifikasi pelapor.']);
            $source->log('closed', 'Ditutup otomatis: seluruh tindakan terverifikasi');
            $this->notifier->hazardClosed($source->fresh());
        }

        $this->notifier->actionVerified($action->fresh('source'), $accepted);

        return back()->with('success', $accepted ? 'Tindakan diverifikasi.' : 'Tindakan dikembalikan ke PIC.');
    }

    public function destroy(CorrectiveAction $action): RedirectResponse
    {
        abort_unless(Access::canManage(), 403);

        $action->source?->log('action_updated', 'Tindakan dihapus: '.str($action->description)->limit(60));
        $action->delete();

        return back()->with('success', 'Tindakan dihapus.');
    }
}
