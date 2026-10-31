<?php

namespace Tests\Feature;

use App\Models\CorrectiveAction;
use App\Models\EmailLog;
use App\Models\Hazard;
use App\Models\Inspection;
use App\Models\InspectionTemplate;
use App\Models\Location;
use App\Models\Setting;
use App\Services\InspectionStats;
use App\Services\SafetyStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SafetyFlowTest extends TestCase
{
    use RefreshDatabase;

    private Location $site;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sso.portal_url' => 'http://portal.test', 'sso.app_code' => 'ssap-safety', 'sso.secret' => 'test-secret-test-secret-test-secret-123']);
        config(['hris.token' => null, 'mailsvc.token' => null]);   // tes tidak boleh memanggil HRIS / AKP Mail sungguhan
        Http::preventStrayRequests();
        Storage::fake('local');
        Setting::forget();

        $this->site = Location::create(['code' => 'PIT', 'name' => 'Pit Utara', 'is_active' => true]);
    }

    private function as(string $role, array $extra = []): static
    {
        return $this->loginPortal($extra + ['id' => $role.'-1', 'role' => $role, 'level' => 10, 'name' => ucfirst($role), 'employee_no' => '1000'.strlen($role), 'email' => $role.'@test.test']);
    }

    private function hazardPayload(array $over = []): array
    {
        return $over + [
            'observed_at' => now()->subHour()->format('Y-m-d\TH:i'), 'location_id' => $this->site->id, 'category' => 'kta',
            'description' => 'Kabel terkelupas: kabel ekstensi di area basah.',
        ];
    }

    /** Hazard langsung ke database dengan tanggal terkendali (untuk menguji perhitungan). */
    private function hazard(array $over = []): Hazard
    {
        static $seq = 0;
        $seq++;

        return Hazard::create($over + [
            'number' => 'HZD-TEST-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT), 'observed_at' => now()->subDays(10), 'location_id' => $this->site->id, 'category' => 'tta',
            'title' => 'Hazard uji', 'description' => 'Keterangan hazard uji.',
            'reporter_name' => 'Pelapor', 'reporter_department' => 'Produksi', 'status' => 'open', 'created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10),
        ]);
    }

    public function test_a_reporter_files_a_hazard_and_sees_only_their_own(): void
    {
        // Departemen/jabatan pelapor hanya dari HRIS: isian dari browser diabaikan.
        $res = $this->as('user')->post('/ssap/hazard', $this->hazardPayload(['reporter_department' => 'Palsu', 'reporter_position' => 'Direktur']) + ['files' => [UploadedFile::fake()->image('foto.jpg')]]);

        $hazard = Hazard::firstOrFail();
        $res->assertRedirect(route('ssap.hazards.show', $hazard));
        $this->assertMatchesRegularExpression('/^HZD-\d{4}-0001$/', $hazard->number);
        $this->assertNull($hazard->reporter_department);
        $this->assertNull($hazard->reporter_position);
        $this->assertSame('open', $hazard->status);
        $this->assertSame('kta', $hazard->category);
        $this->assertStringStartsWith('Kabel terkelupas', $hazard->title);   // ringkasan dari keterangan
        $this->assertSame(now()->addDays(config('ssap.hazard_sla_days'))->toDateString(), $hazard->due_date->toDateString());
        $this->assertSame(1, $hazard->attachments()->count());
        Storage::disk('local')->assertExists($hazard->attachments->first()->path);

        $this->as('user')->get('/ssap/hazard')->assertOk()->assertSee($hazard->number);
        $this->as('user', ['id' => 'other', 'employee_no' => '777'])->get('/ssap/hazard')->assertOk()->assertDontSee($hazard->number);
        $this->as('user', ['id' => 'other', 'employee_no' => '777'])->get('/ssap/hazard/'.$hazard->id)->assertForbidden();
        $this->as('hse')->get('/ssap/hazard/'.$hazard->id)->assertOk()->assertSee('Kabel terkelupas')->assertSee('Keterangan')->assertDontSee('Penilaian risiko');
    }

    public function test_the_hazard_form_has_no_risk_matrix_title_or_hazard_description(): void
    {
        $this->as('user')->get('/ssap/hazard/baru')->assertOk()
            ->assertSee('Jenis hazard')->assertSee('TTA')->assertSee('KTA')->assertSee('Keterangan')
            ->assertDontSee('Seberapa berisiko')->assertDontSee('Uraian bahaya')->assertDontSee('Judul singkat');
    }

    public function test_hazard_validation_rejects_bad_input(): void
    {
        $this->as('user')->post('/ssap/hazard', $this->hazardPayload(['category' => 'electrical']))->assertSessionHasErrors('category');
        $this->as('user')->post('/ssap/hazard', $this->hazardPayload(['observed_at' => now()->addDay()->format('Y-m-d\TH:i')]))->assertSessionHasErrors('observed_at');
        $this->as('user')->post('/ssap/hazard', $this->hazardPayload(['description' => 'dik']))->assertSessionHasErrors('description');
        $this->as('user')->post('/ssap/hazard', $this->hazardPayload(['location_id' => 999]))->assertSessionHasErrors('location_id');
        $this->assertSame(0, Hazard::count());
    }

    public function test_the_reporter_is_the_pic_and_closes_the_hazard_by_recording_the_fix(): void
    {
        $this->as('user')->post('/ssap/hazard', $this->hazardPayload())->assertSessionHasNoErrors();
        $hazard = Hazard::firstOrFail();

        // Pelapor otomatis menjadi PIC.
        $this->assertSame('10004', $hazard->pic_nik);
        $this->assertSame('User', $hazard->pic_name);
        $this->assertSame('open', $hazard->status);

        // Hanya pelapor yang mencatat perbaikan; HSE dan orang lain tidak (safety hanya memantau).
        $fix = ['to' => 'closed', 'note' => 'Kabel diganti oleh tim maintenance'];
        $this->as('hse')->post("/ssap/hazard/{$hazard->id}/status", $fix)->assertForbidden();
        $this->as('manager')->post("/ssap/hazard/{$hazard->id}/status", $fix)->assertForbidden();
        $this->as('user', ['employee_no' => '999', 'id' => 'rnd'])->post("/ssap/hazard/{$hazard->id}/status", $fix)->assertForbidden();
        $this->as('hse')->get('/ssap/hazard/'.$hazard->id)->assertOk()->assertDontSee('Selesai &amp; tutup', false);

        // Perbaikan sedang berjalan, lalu selesai (catatan wajib, foto bukti ikut tersimpan).
        $this->as('user')->post("/ssap/hazard/{$hazard->id}/status", ['to' => 'in_progress'])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $hazard->fresh()->status);
        $this->as('user')->post("/ssap/hazard/{$hazard->id}/status", ['to' => 'closed'])->assertSessionHasErrors('note');
        $this->as('user')->post("/ssap/hazard/{$hazard->id}/status", $fix + ['files' => [UploadedFile::fake()->image('bukti.jpg')]])->assertSessionHasNoErrors();

        $hazard->refresh();
        $this->assertSame('closed', $hazard->status);
        $this->assertSame('Kabel diganti oleh tim maintenance', $hazard->closure_note);
        $this->assertNotNull($hazard->closed_at);
        $this->assertSame(1, $hazard->attachments()->count());
        $this->as('hse')->get('/ssap/hazard/'.$hazard->id)->assertOk()->assertSee('Kabel diganti oleh tim maintenance');

        // Perbaikan belum tuntas: pelapor membuka kembali; HSE tidak bisa.
        $this->as('hse')->post("/ssap/hazard/{$hazard->id}/status", ['to' => 'open'])->assertForbidden();
        $this->as('user')->post("/ssap/hazard/{$hazard->id}/status", ['to' => 'open'])->assertSessionHasNoErrors();
        $this->assertSame('open', $hazard->fresh()->status);
    }

    public function test_the_assign_verify_and_reject_flows_are_gone(): void
    {
        $this->as('user')->post('/ssap/hazard', $this->hazardPayload());
        $hazard = Hazard::firstOrFail();

        $this->as('hse')->post("/ssap/hazard/{$hazard->id}/tugaskan", ['pic_name' => 'Rudi'])->assertNotFound();
        $this->as('hse')->post("/ssap/hazard/{$hazard->id}/status", ['to' => 'rejected', 'note' => 'x'])->assertForbidden();
        $this->as('hse')->get('/ssap/tindakan')->assertNotFound();
        $this->as('hse')->get('/ssap/laporan/ekspor/actions')->assertNotFound();
    }

    public function test_inspection_findings_become_hazards_and_the_score_ignores_na(): void
    {
        $template = InspectionTemplate::active()->with('items')->firstOrFail();
        $items = $template->items;
        $results = [];
        $notes = [];
        foreach ($items as $i => $item) {
            $results[$item->id] = $i === 0 ? 'nok' : ($i === 1 ? 'na' : 'ok');
            if ($i === 0) {
                $notes[$item->id] = 'Helm tidak dipakai';
            }
        }

        $this->as('user')->post('/ssap/inspeksi', [
            'template_id' => $template->id, 'location_id' => $this->site->id, 'inspected_at' => now()->subHour()->format('Y-m-d\TH:i'), 'result' => $results, 'note' => $notes,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $inspection = Inspection::firstOrFail();
        $total = $items->count();
        $this->assertSame(1, $inspection->nok_count);
        $this->assertSame(1, $inspection->na_count);
        $this->assertSame($total - 2, $inspection->ok_count);
        $this->assertEqualsWithDelta(round(($total - 2) * 100 / ($total - 1), 1), $inspection->score, 0.05);
        $this->assertSame(1, Hazard::count());
        $this->assertSame($inspection->items()->whereNotNull('hazard_id')->first()->hazard_id, Hazard::first()->id);

        $this->as('user')->get("/ssap/inspeksi/{$inspection->id}")->assertOk()->assertSee('Helm tidak dipakai');
        $this->as('user', ['id' => 'x', 'employee_no' => '1'])->get("/ssap/inspeksi/{$inspection->id}")->assertForbidden();
    }

    public function test_inspection_needs_every_item_rated_and_a_note_for_findings(): void
    {
        $template = InspectionTemplate::active()->with('items')->firstOrFail();
        $base = ['template_id' => $template->id, 'location_id' => $this->site->id, 'inspected_at' => now()->subHour()->format('Y-m-d\TH:i')];

        $partial = [$template->items->first()->id => 'ok'];
        $this->as('user')->post('/ssap/inspeksi', $base + ['result' => $partial])->assertSessionHasErrors('result');

        $all = $template->items->mapWithKeys(fn ($i) => [$i->id => 'nok'])->all();
        $this->as('user')->post('/ssap/inspeksi', $base + ['result' => $all])->assertSessionHasErrors('note');
        $this->assertSame(0, Inspection::count());
    }

    public function test_hazard_statistics_measure_accountability_correctly(): void
    {
        // A: selesai tepat waktu. B: selesai terlambat. C: terbuka & lewat tenggat. D: terbuka, belum ada PIC.
        $pic = ['pic_name' => 'Rudi', 'pic_department' => 'Plant', 'pic_email' => 'rudi@test.test'];
        $a = $this->hazard($pic + ['status' => 'closed', 'observed_at' => now()->subDays(10), 'due_date' => now()->subDays(5)->toDateString(), 'closed_at' => now()->subDays(6)]);
        $b = $this->hazard($pic + ['status' => 'closed', 'observed_at' => now()->subDays(12), 'created_at' => now()->subDays(12), 'due_date' => now()->subDays(8)->toDateString(), 'closed_at' => now()->subDays(2)]);
        $c = $this->hazard($pic + ['status' => 'in_progress', 'observed_at' => now()->subDays(10), 'due_date' => now()->subDay()->toDateString(), 'category' => 'kta']);
        $d = $this->hazard(['status' => 'open', 'observed_at' => now()->subDay(), 'created_at' => now()->subDay(), 'due_date' => now()->addDays(6)->toDateString()]);
        $this->hazard(['status' => 'rejected', 'observed_at' => now()->subDays(3)]);   // ditolak: tidak dihitung

        $stats = new SafetyStats(now()->subMonth()->startOfDay(), now()->endOfDay());
        $k = $stats->kpis();

        $this->assertSame(4, $k['hazards']);
        $this->assertSame(2, $k['closed']);
        $this->assertSame(50, $k['closure_pct']);
        $this->assertSame(50, $k['sla_pct']);          // 1 dari 2 selesai tepat waktu
        $this->assertSame(2, $k['open_now']);
        $this->assertSame(1, $k['overdue_now']);
        $this->assertSame(1, $k['in_progress']);
        $this->assertSame(1, $k['kta_open_now']);
        $this->assertSame(1, $k['kta']);
        $this->assertSame(3, $k['tta']);

        $board = collect($stats->board('pic'))->keyBy('name')['Rudi'];
        $this->assertSame(3, $board['assigned']);
        $this->assertSame(1, $board['open']);
        $this->assertSame(1, $board['overdue']);
        $this->assertSame(2, $board['closed']);
        $this->assertSame(50, $board['sla_pct']);

        $aging = $stats->aging();
        $this->assertSame(1, $aging['data'][0]);   // D: 0-3 hari
        $this->assertSame(1, $aging['data'][2]);   // C: 8-14 hari
        $this->assertSame(1, $aging['overdue'][2]);

        $cat = collect($stats->categoryTable())->keyBy('key');
        $this->assertSame(1, $cat['kta']['total']);
        $this->assertSame(1, $cat['kta']['overdue']);
        $this->assertSame(3, $cat['tta']['total']);

        $this->assertSame([3, 1], array_map(fn ($v) => $v, $stats->byCategory()['data']));
    }

    public function test_inspection_statistics_compare_against_targets(): void
    {
        $template = InspectionTemplate::active()->orderBy('id')->firstOrFail();
        InspectionTemplate::query()->where('id', '!=', $template->id)->update(['is_active' => false]);
        $template->update(['target_per_month' => 4]);

        foreach ([[95.0, 0], [80.0, 2], [60.0, 5]] as [$score, $nok]) {
            Inspection::create([
                'number' => 'INS-T-'.(int) $score, 'template_id' => $template->id, 'template_name' => $template->name, 'category' => 'ppe', 'location_id' => $this->site->id,
                'inspected_at' => now()->subHours(2), 'inspector_nik' => '1', 'inspector_name' => 'Sari', 'total_items' => 10, 'ok_count' => 10 - $nok, 'nok_count' => $nok, 'na_count' => 0, 'score' => $score,
            ]);
        }

        $stats = new InspectionStats(now()->startOfMonth(), now()->endOfMonth());
        $k = $stats->kpis();

        $this->assertSame(3, $k['count']);
        $this->assertSame(4, $k['target']);
        $this->assertSame(75, $k['compliance']);
        $this->assertEqualsWithDelta(78.3, $k['avg_score'], 0.05);
        $this->assertSame(7, $k['findings']);
        $this->assertSame(33, $stats->cleanPct());
        $this->assertSame('Sari', $stats->inspectorBoard()[0]['name']);
        $this->assertSame([['name' => $template->name, 'done' => 3, 'target' => 4]], $stats->behindThisMonth());
    }

    public function test_every_page_renders_and_levels_are_enforced(): void
    {
        $this->hazard(['pic_name' => 'Rudi', 'status' => 'in_progress', 'due_date' => now()->addDay()->toDateString()]);
        $hazard = Hazard::firstOrFail();
        $this->assertSame('Pelapor', Hazard::create(['number' => 'HZD-PIC', 'observed_at' => now(), 'location_id' => $this->site->id, 'description' => 'x', 'reporter_name' => 'Pelapor', 'status' => 'open'])->pic_name);

        foreach (['/ssap', '/ssap/hazard-dashboard', '/ssap/inspeksi-dashboard', '/ssap/akuntabilitas', '/ssap/laporan', '/ssap/laporan?preset=last_year', '/ssap/laporan/ringkasan-bulanan', '/ssap/standar', '/ssap/hazard', '/ssap/hazard/'.$hazard->id, '/ssap/inspeksi', '/ssap/inspeksi/baru', '/ssap/inspeksi/baru?template=1'] as $url) {
            $this->as('manager')->get($url)->assertOk();
        }
        foreach (['/ssap/inspeksi-template'] as $url) {
            $this->as('hse')->get($url)->assertOk();
        }
        foreach (['/ssap/lokasi', '/ssap/pengaturan'] as $url) {
            $this->as('admin')->get($url)->assertOk();
        }

        foreach (['/ssap/laporan', '/ssap/hazard-dashboard', '/ssap/inspeksi-dashboard', '/ssap/akuntabilitas'] as $url) {
            $this->as('user')->get($url)->assertForbidden();
        }
        foreach (['/ssap/inspeksi-template'] as $url) {
            $this->as('manager')->get($url)->assertForbidden();
        }
        foreach (['/ssap/lokasi', '/ssap/pengaturan'] as $url) {
            $this->as('hse')->get($url)->assertForbidden();
        }
        $this->as('hse')->post('/ssap/lokasi', ['code' => 'X', 'name' => 'X'])->assertForbidden();
        $this->as('hse')->delete("/ssap/hazard/{$hazard->id}")->assertForbidden();
    }

    public function test_csv_exports_contain_the_data_and_neutralise_formulas(): void
    {
        $this->hazard(['description' => '=HYPERLINK("http://x")', 'pic_name' => 'Rudi']);

        foreach (['hazards' => 'HZD-TEST', 'inspections' => 'Nomor', 'findings' => 'Nomor inspeksi'] as $dataset => $needle) {
            $csv = $this->as('manager')->get("/ssap/laporan/ekspor/{$dataset}?preset=year")->assertOk()->streamedContent();
            $this->assertStringContainsString($needle, $csv, $dataset);
        }

        $this->assertStringContainsString("'=HYPERLINK", $this->as('manager')->get('/ssap/laporan/ekspor/hazards?preset=year')->streamedContent());
        $this->as('manager')->get('/ssap/laporan/ekspor/incidents')->assertNotFound();
        $this->as('user')->get('/ssap/laporan/ekspor/hazards')->assertForbidden();
    }

    public function test_attachments_are_only_served_to_people_who_may_see_the_report(): void
    {
        $this->as('user')->post('/ssap/hazard', $this->hazardPayload() + ['files' => [UploadedFile::fake()->image('foto.jpg')]]);
        $attachment = Hazard::firstOrFail()->attachments()->firstOrFail();

        $this->as('user')->get('/ssap/lampiran/'.$attachment->id)->assertOk();
        $this->as('user', ['id' => 'other', 'employee_no' => '777'])->get('/ssap/lampiran/'.$attachment->id)->assertForbidden();
    }

    public function test_hris_employee_search_is_read_only_and_falls_back_when_not_configured(): void
    {
        $this->as('user')->getJson('/ssap/karyawan?q=bu')->assertOk()->assertJson(['configured' => false, 'data' => []]);

        config(['hris.url' => 'http://hris.test', 'hris.token' => 'tok']);
        Http::fake(['hris.test/*' => Http::response(['data' => [
            ['employee_no' => '100', 'name' => 'Budi Santoso', 'department' => 'Produksi', 'position' => 'Operator', 'email' => 'budi@akp.test', 'is_active' => true, 'ktp' => 'RAHASIA'],
            ['employee_no' => '101', 'name' => 'Budi Lama', 'department' => 'HSE', 'position' => 'Staf', 'email' => null, 'is_active' => false],
        ], 'meta' => ['last_page' => 1, 'total' => 2]])]);

        $res = $this->as('user')->getJson('/ssap/karyawan?q=budi')->assertOk();
        $res->assertJsonCount(1, 'data')->assertJsonPath('data.0.nik', '100');
        $this->assertStringNotContainsString('RAHASIA', $res->getContent());
    }

    public function test_notifications_go_through_akp_mail_and_never_break_reporting(): void
    {
        config(['mailsvc.url' => 'http://mail.test', 'mailsvc.token' => 'akpm_x']);
        Setting::put('notify_hse', 'hse@akp.test');
        Http::fake(['mail.test/*' => Http::sequence()->push(['data' => ['id' => 'abc']], 202)->push(['data' => ['id' => 'abd']], 202)->whenEmpty(Http::response('boom', 500))]);

        $this->as('user')->post('/ssap/hazard', $this->hazardPayload())->assertSessionHasNoErrors();   // ekstrem

        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/v1/emails') && $r->hasHeader('Authorization', 'Bearer akpm_x') && in_array('hse@akp.test', $r['to']) && str_starts_with($r['subject'], 'Hazard baru'));

        // AKP Mail mati: laporan tetap tersimpan.
        $this->as('user')->post('/ssap/hazard', $this->hazardPayload())->assertSessionHasNoErrors();
        $this->assertSame(2, Hazard::count());
        $this->assertSame('failed', EmailLog::latest('id')->first()->status);
    }

    public function test_daily_reminder_command_mails_pics_and_the_hse_digest(): void
    {
        config(['mailsvc.url' => 'http://mail.test', 'mailsvc.token' => 'akpm_x']);
        Setting::put('notify_hse', 'hse@akp.test');
        Http::fake(['mail.test/*' => Http::response(['data' => ['id' => 'r1']], 202)]);

        $h = $this->hazard(['pic_name' => 'Rudi', 'pic_email' => 'rudi@test.test', 'status' => 'in_progress', 'due_date' => now()->subDay()->toDateString()]);

        $this->artisan('ssap:remind')->assertSuccessful();

        Http::assertSent(fn ($r) => $r->method() === 'POST' && in_array('rudi@test.test', $r['to']) && str_starts_with($r['subject'], '[TERLAMBAT]'));
        Http::assertSent(fn ($r) => $r->method() === 'POST' && in_array('hse@akp.test', $r['to']) && str_contains($r['subject'], 'Ringkasan harian'));

        // Hari yang sama tidak mengirim pengingat ganda ke PIC.
        $before = EmailLog::where('category', 'reminder')->count();
        $this->artisan('ssap:remind')->assertSuccessful();
        $this->assertSame($before, EmailLog::where('category', 'reminder')->count());
    }

    public function test_the_standard_page_reflects_the_configuration(): void
    {
        $this->as('user')->get('/ssap/standar')->assertOk()->assertSee('TTA')->assertSee('KTA')->assertSee((string) config('ssap.hazard_sla_days').' hari');
        $this->assertSame(['tta', 'kta'], array_keys(config('ssap.hazard_categories')));
    }
}
