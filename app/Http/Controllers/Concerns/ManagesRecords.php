<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CorrectiveAction;
use App\Services\HrisEmployees;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Bagian yang sama untuk hazard, inspeksi, dan tindakan: identitas pelapor/PIC, lampiran, aturan berkas. */
trait ManagesRecords
{
    /** Identitas pelapor = pengguna Portal yang login; nama, departemen, jabatan, email diambil dari HRIS dan tidak dapat diubah. */
    protected function reporterSnapshot(Request $request): array
    {
        $user = portal_user();
        $employee = app(HrisEmployees::class)->find($user->employee_no);

        return [
            'reporter_nik' => $user->employee_no,
            'reporter_name' => $employee['name'] ?? $user->name,
            // Departemen dan jabatan HANYA dari HRIS; isian dari browser diabaikan (tidak dapat diubah pelapor).
            'reporter_department' => $employee['department'] ?? null,
            'reporter_position' => $employee['position'] ?? null,
            'reporter_email' => $employee['email'] ?? $user->email,
            'created_by_id' => (string) $user->id,
        ];
    }

    /**
     * Orang yang ditunjuk (PIC/investigator). Bila NIK dikenal HRIS, nama/departemen/email diambil dari HRIS
     * (bukan dari isian browser); bila tidak, isian manual dipakai.
     *
     * @return array{nik:?string,name:?string,department:?string,email:?string}
     */
    protected function resolvePerson(?string $nik, ?string $name, ?string $department = null, ?string $email = null): array
    {
        $employee = app(HrisEmployees::class)->find($nik);

        if ($employee) {
            return ['nik' => $employee['nik'], 'name' => $employee['name'], 'department' => $employee['department'], 'email' => $employee['email']];
        }

        return [
            'nik' => $this->clean($nik),
            'name' => $this->clean($name),
            'department' => $this->clean($department),
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null,
        ];
    }

    protected function clean(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    /** Aturan validasi untuk unggahan foto/dokumen. */
    protected function fileRules(string $field = 'files'): array
    {
        $cfg = config('ssap.attachments');

        return [
            $field => ['nullable', 'array', 'max:'.$cfg['max_files']],
            "$field.*" => ['file', 'max:'.$cfg['max_kb'], 'mimes:'.implode(',', $cfg['mimes'])],
        ];
    }

    /** Simpan berkas unggahan ke penyimpanan privat. $phase: before/after untuk foto hazard. Kembali: jumlah berkas tersimpan. */
    protected function storeAttachments(Model $record, string $type, Request $request, string $field = 'files', ?string $phase = null): int
    {
        $count = 0;

        foreach ((array) $request->file($field, []) as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $record->attachments()->create([
                'path' => $file->store("attachments/{$type}/{$record->getKey()}", 'local'),
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                'mime' => $file->getMimeType(),
                'phase' => $phase,
                'size' => $file->getSize(),
                'uploaded_by' => portal_user()?->name,
            ]);
            $count++;
        }

        return $count;
    }

    /** Tolak bila pengguna tidak boleh melihat data ini (403). */
    protected function authorizeView(Model $record): void
    {
        abort_unless($record->canBeSeenBy(portal_user()), 403, 'Anda tidak berhak melihat laporan ini.');
    }

    /** Apakah pengguna login adalah PIC (NIK sama) tindakan ini? */
    protected function isPic(CorrectiveAction $action): bool
    {
        $nik = portal_user()?->employee_no;

        return filled($nik) && $nik === $action->pic_nik;
    }

    /** Aturan umum isian orang yang ditunjuk. */
    protected function personRules(string $prefix, bool $required = true): array
    {
        return [
            "{$prefix}_nik" => ['nullable', 'string', 'max:30'],
            "{$prefix}_name" => [$required ? 'required' : 'nullable', 'string', 'max:120'],
            "{$prefix}_department" => ['nullable', 'string', 'max:120'],
            "{$prefix}_email" => ['nullable', 'email', 'max:160'],
        ];
    }

    protected function inList(string $configKey): \Illuminate\Validation\Rules\In
    {
        return Rule::in(array_keys(config($configKey)));
    }
}
