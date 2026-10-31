<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Pembaca data karyawan HRIS (API baca-saja, STANDAR-APLIKASI bagian "Data karyawan").
 * SAP Safety tidak menyimpan tabel karyawan: daftar diambil dari HRIS lalu disimpan sementara di cache.
 * Bila HRIS tidak dapat dihubungi, aplikasi memakai salinan cache terakhir; bila tidak ada, formulir diisi manual.
 */
class HrisEmployees
{
    private const CACHE_KEY = 'hris.employees';

    private const STALE_KEY = 'hris.employees.stale';

    private const DOWN_KEY = 'hris.down';

    public static function configured(): bool
    {
        return filled(config('hris.token')) && filled(config('hris.url'));
    }

    /**
     * Semua karyawan aktif, dikunci NIK. Tidak pernah melempar galat: kembali [] bila HRIS tidak tersedia.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        if (! self::configured()) {
            return [];
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        // HRIS sedang bermasalah: jangan menahan setiap permintaan pengguna, pakai salinan lama dulu.
        if (Cache::has(self::DOWN_KEY)) {
            return Cache::get(self::STALE_KEY, []);
        }

        try {
            $employees = $this->fetch();
        } catch (\Throwable $e) {
            report($e);
            Cache::put(self::DOWN_KEY, true, 60);

            return Cache::get(self::STALE_KEY, []);
        }

        Cache::put(self::CACHE_KEY, $employees, (int) config('hris.cache_seconds', 600));
        Cache::forever(self::STALE_KEY, $employees);

        return $employees;
    }

    /** Ambil ulang dari HRIS sekarang (tombol "Sinkronkan" dan jadwal). Melempar RuntimeException bila gagal. */
    public function refresh(): int
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::DOWN_KEY);

        $employees = $this->fetch();
        Cache::put(self::CACHE_KEY, $employees, (int) config('hris.cache_seconds', 600));
        Cache::forever(self::STALE_KEY, $employees);
        Cache::forever('hris.synced_at', now()->toIso8601String());

        return count($employees);
    }

    public function syncedAt(): ?string
    {
        return Cache::get('hris.synced_at');
    }

    public function find(?string $nik): ?array
    {
        if (blank($nik)) {
            return null;
        }

        $row = $this->all()[(string) $nik] ?? null;

        return $row ? $this->present($row) : null;
    }

    /**
     * Cari karyawan aktif lewat NIK atau nama.
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $term, int $limit = 12): array
    {
        $term = Str::lower(trim($term));
        if (mb_strlen($term) < 2) {
            return [];
        }

        $found = [];
        foreach ($this->all() as $row) {
            if (! ($row['is_active'] ?? true)) {
                continue;
            }

            $nik = Str::lower((string) ($row['employee_no'] ?? ''));
            $name = Str::lower((string) ($row['name'] ?? ''));

            if (str_starts_with($nik, $term)) {
                $rank = 0;
            } elseif (str_contains($name, $term)) {
                $rank = str_starts_with($name, $term) ? 1 : 2;
            } else {
                continue;
            }

            $found[] = [$rank, $name, $this->present($row)];
        }

        usort($found, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_map(fn ($f) => $f[2], array_slice($found, 0, $limit));
    }

    /** Grade (jenjang) karyawan menurut HRIS, null bila tidak diketahui. */
    public function gradeOf(?string $nik): ?int
    {
        return $this->find($nik)['grade'] ?? null;
    }

    /** Jumlah karyawan aktif per grade: [grade => ['count' => n, 'category' => 'SUPERVISOR']]. @return array<int, array{count:int,category:?string}> */
    public function gradeCounts(): array
    {
        $out = [];
        foreach ($this->all() as $row) {
            if (($row['is_active'] ?? true) && isset($row['grade'])) {
                $g = (int) $row['grade'];
                $out[$g]['count'] = ($out[$g]['count'] ?? 0) + 1;
                $out[$g]['category'] ??= $row['grade_category'] ?? null;
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * Ringkasan data HRIS yang dipakai SAP: karyawan aktif, per departemen, jabatan, divisi, dan grade.
     *
     * @return array{total:int,with_grade:int,with_supervisor:int,departments:array<string,int>,positions:array<string,int>,divisions:array<string,int>}
     */
    public function overview(): array
    {
        $o = ['total' => 0, 'with_grade' => 0, 'with_supervisor' => 0, 'departments' => [], 'positions' => [], 'divisions' => []];
        foreach ($this->all() as $row) {
            if (! ($row['is_active'] ?? true)) {
                continue;
            }
            $o['total']++;
            $o['with_grade'] += isset($row['grade']) ? 1 : 0;
            $o['with_supervisor'] += filled($row['supervisor_nik'] ?? null) ? 1 : 0;
            foreach (['departments' => 'department', 'positions' => 'position', 'divisions' => 'division'] as $key => $field) {
                $name = $row[$field] ?? null;
                if (filled($name)) {
                    $o[$key][$name] = ($o[$key][$name] ?? 0) + 1;
                }
            }
        }
        foreach (['departments', 'positions', 'divisions'] as $key) {
            arsort($o[$key]);
        }

        return $o;
    }

    /**
     * NIK seluruh bawahan (langsung maupun tidak langsung) menurut atasan langsung di HRIS. Hanya karyawan aktif.
     *
     * @return list<string>
     */
    public function subordinateNiks(string $nik): array
    {
        $children = [];
        foreach ($this->all() as $row) {
            if (($row['is_active'] ?? true) && filled($row['supervisor_nik'] ?? null)) {
                $children[(string) $row['supervisor_nik']][] = (string) $row['employee_no'];
            }
        }

        $found = [];
        $queue = [$nik];
        while ($queue) {
            foreach ($children[array_shift($queue)] ?? [] as $child) {
                if ($child !== $nik && ! isset($found[$child])) {
                    $found[$child] = true;
                    $queue[] = $child;
                }
            }
        }

        return array_keys($found);
    }

    /**
     * Karyawan aktif ber-grade tertentu (untuk daftar target), dengan nama atasan.
     *
     * @param  list<int>  $grades
     * @param  list<string>|null  $niks  batasi ke NIK ini (null = semua)
     * @return list<array<string, mixed>>
     */
    public function members(array $grades, ?array $niks = null): array
    {
        $all = $this->all();
        $allow = $niks === null ? null : array_flip($niks);
        $out = [];

        foreach ($all as $nik => $row) {
            if (! ($row['is_active'] ?? true) || ($allow !== null && ! isset($allow[(string) $nik]))) {
                continue;
            }
            $p = $this->present($row);
            if (in_array($p['grade'], $grades, true)) {
                $p['supervisor_name'] = $p['supervisor_nik'] ? ($all[$p['supervisor_nik']]['name'] ?? null) : null;
                $out[] = $p;
            }
        }

        return $out;
    }

    /** Hanya bidang yang dibutuhkan SAP Safety (tanpa data sensitif). */
    private function present(array $row): array
    {
        return [
            'nik' => (string) ($row['employee_no'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'department' => $row['department'] ?? null,
            'division' => $row['division'] ?? null,
            'position' => $row['position'] ?? null,
            'section' => $row['section'] ?? null,
            'employment_status' => $row['employment_status'] ?? null,
            'grade' => isset($row['grade']) ? (int) $row['grade'] : null,
            'grade_category' => $row['grade_category'] ?? null,
            'supervisor_nik' => filled($row['supervisor_nik'] ?? null) ? (string) $row['supervisor_nik'] : null,
            'email' => $row['email'] ?? null,
            'is_active' => (bool) ($row['is_active'] ?? true),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function fetch(): array
    {
        $byNik = [];
        $page = 1;

        do {
            $response = Http::withToken((string) config('hris.token'))->acceptJson()->timeout(20)
                ->get(rtrim((string) config('hris.url'), '/').'/api/integrations/employees', ['page' => $page, 'per_page' => 500]);

            if (! $response->successful()) {
                throw new \RuntimeException("HRIS menjawab HTTP {$response->status()}");
            }

            foreach ($response->json('data', []) as $employee) {
                if (filled($employee['employee_no'] ?? null)) {
                    $byNik[(string) $employee['employee_no']] = $employee;
                }
            }

            $last = (int) $response->json('meta.last_page', 1);
        } while (++$page <= $last);

        return $byNik;
    }

    /** Pemeriksaan sambungan untuk halaman Pengaturan. */
    public function health(): array
    {
        if (! self::configured()) {
            return ['ok' => false, 'message' => 'HRIS_API_URL / HRIS_API_TOKEN belum diisi di .env. Pencarian karyawan nonaktif; formulir diisi manual.'];
        }

        try {
            $res = Http::withToken((string) config('hris.token'))->acceptJson()->timeout(6)
                ->get(rtrim((string) config('hris.url'), '/').'/api/integrations/employees', ['per_page' => 1]);

            return match (true) {
                $res->successful() => ['ok' => true, 'message' => 'Tersambung ke HRIS ('.number_format((int) $res->json('meta.total', 0), 0, ',', '.').' karyawan).'],
                in_array($res->status(), [401, 403], true) => ['ok' => false, 'message' => 'HRIS menolak token (HTTP '.$res->status().'). Periksa HRIS_API_TOKEN.'],
                default => ['ok' => false, 'message' => 'HRIS menjawab HTTP '.$res->status().'.'],
            };
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'HRIS tidak terjangkau: '.Str::limit($e->getMessage(), 140)];
        }
    }
}
