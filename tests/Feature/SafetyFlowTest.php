<?php

namespace Tests\Feature;

use App\Models\CorrectiveAction;
use App\Models\Hazard;
use App\Models\Incident;
use App\Models\Inspection;
use App\Models\InspectionTemplate;
use App\Models\Location;
use App\Models\ManHour;
use App\Models\Setting;
use App\Services\SafetyStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    private function incidentPayload(array $over = []): array
    {
        return $over + [
            'type' => 'medical_treatment', 'severity' => 3, 'occurred_at' => now()->subHours(3)->format('Y-m-d\TH:i'),
            'location_id' => $this->site->id, 'title' => 'Jari terjepit saat ganti komponen',
            'description' => 'Pekerja terjepit komponen saat penggantian dan dibawa ke klinik.',
            'persons' => [['role' => 'korban', 'name' => 'Andi', 'lost_days' => 0, 'injury_type' => 'Terjepit / tergencet']],
        ];
    }

    public function test_a_reporter_files_an_incident_and_sees_only_their_own(): void
    {
        $res = $this->as('user')->post('/ssap/insiden', $this->incidentPayload() + ['files' => [UploadedFile::fake()->image('foto.jpg')]]);

        $incident = Incident::firstOrFail();
        $res->assertRedirect(route('ssap.incidents.show', $incident));
        $this->assertMatchesRegularExpression('/^INC-\d{4}-0001$/', $incident->number);
        $this->assertSame('submitted', $incident->status);
        $this->assertSame(1, $incident->injured_count);
        $this->assertSame(1, $incident->attachments()->count());
        Storage::disk('local')->assertExists($incident->attachments->first()->path);

        $this->as('user')->get('/ssap/insiden')->assertOk()->assertSee($incident->number);
        $this->as('user', ['id' => 'other', 'employee_no' => '777'])->get('/ssap/insiden')->assertOk()->assertDontSee($incident->number);
        $this->as('user', ['id' => 'other', 'employee_no' => '777'])->get('/ssap/insiden/'.$incident->id)->assertForbidden();
        $this->as('hse')->get('/ssap/insiden/'.$incident->id)->assertOk()->assertSee('Jari terjepit');
    }

    public function test_injury_types_require_a_victim_and_future_dates_are_refused(): void
    {
        $this->as('user')->post('/ssap/insiden', $this->incidentPayload(['persons' => []]))->assertSessionHasErrors('persons');
        $this->as('user')->post('/ssap/insiden', $this->incidentPayload(['occurred_at' => now()->addDay()->format('Y-m-d\TH:i')]))->assertSessionHasErrors('occurred_at');
        $this->assertSame(0, Incident::count());
    }

    public function test_only_hse_can_investigate_and_an_incident_cannot_close_without_root_cause_and_verified_actions(): void
    {
        $this->as('user')->post('/ssap/insiden', $this->incidentPayload());
        $incident = Incident::firstOrFail();

        $this->as('user')->put("/ssap/insiden/{$incident->id}/investigasi", ['investigator_name' => 'X'])->assertForbidden();
        $this->as('manager')->post("/ssap/insiden/{$incident->id}/status", ['to' => 'investigating'])->assertForbidden();

        $hse = fn () => $this->as('hse', ['name' => 'Petugas HSE']);

        $hse()->put("/ssap/insiden/{$incident->id}/investigasi", ['investigator_name' => 'Sari', 'why' => ['a', 'b']])->assertSessionHasNoErrors();
        $this->assertSame('investigating', $incident->fresh()->status);

        $hse()->post("/ssap/insiden/{$incident->id}/status", ['to' => 'follow_up'])->assertSessionHasErrors('to');   // belum ada akar masalah
        $hse()->put("/ssap/insiden/{$incident->id}/investigasi", ['investigator_name' => 'Sari', 'root_cause' => 'Tidak ada LOTO', 'root_cause_category' => 'procedure']);
        $hse()->post("/ssap/insiden/{$incident->id}/status", ['to' => 'follow_up'])->assertSessionHasNoErrors();

        $hse()->post('/ssap/tindakan', ['source_type' => 'incident', 'source_id' => $incident->id, 'type' => 'corrective', 'description' => 'Terapkan LOTO', 'pic_name' => 'Budi', 'pic_nik' => '555', 'due_date' => now()->addWeek()->toDateString()])->assertSessionHasNoErrors();
        $action = CorrectiveAction::firstOrFail();

        $hse()->post("/ssap/insiden/{$incident->id}/status", ['to' => 'closed', 'note' => 'ok'])->assertSessionHasErrors('to');   // tindakan belum terverifikasi

        // PIC mengerjakan, HSE memverifikasi
        $this->as('user', ['employee_no' => '555', 'id' => 'pic'])->put("/ssap/tindakan/{$action->id}/progres", ['to' => 'done', 'completion_note' => 'Sudah diterapkan'])->assertSessionHasNoErrors();
        $this->assertSame('done', $action->fresh()->status);
        $this->as('user', ['employee_no' => '999', 'id' => 'rnd'])->put("/ssap/tindakan/{$action->id}/progres", ['to' => 'done', 'completion_note' => 'x'])->assertForbidden();
        $hse()->post("/ssap/tindakan/{$action->id}/verifikasi", ['decision' => 'accept'])->assertSessionHasNoErrors();
        $this->assertSame('verified', $action->fresh()->status);

        $hse()->post("/ssap/insiden/{$incident->id}/status", ['to' => 'closed'])->assertSessionHasErrors('note');
        $hse()->post("/ssap/insiden/{$incident->id}/status", ['to' => 'closed', 'note' => 'Selesai'])->assertSessionHasNoErrors();
        $this->assertSame('closed', $incident->fresh()->status);
        $this->assertNotNull($incident->fresh()->closed_at);
        $this->as('hse')->get("/ssap/insiden/{$incident->id}/cetak")->assertOk()->assertSee($incident->number);
    }

    public function test_hazard_risk_is_computed_and_closes_automatically_when_actions_are_verified(): void
    {
        $this->as('user')->post('/ssap/hazard', [
            'observed_at' => now()->subHour()->format('Y-m-d\TH:i'), 'location_id' => $this->site->id, 'category' => 'electrical',
            'title' => 'Kabel terkelupas', 'description' => 'Kabel ekstensi terkelupas di area basah.', 'likelihood' => 4, 'consequence' => 5,
        ])->assertSessionHasNoErrors();

        $hazard = Hazard::firstOrFail();
        $this->assertSame(20, $hazard->risk_score);
        $this->assertSame('extreme', $hazard->risk_level);
        $this->assertSame(now()->addDay()->toDateString(), $hazard->due_date->toDateString());   // SLA ekstrem 1 hari

        $this->as('hse')->post("/ssap/hazard/{$hazard->id}/tugaskan", ['pic_nik' => '55', 'pic_name' => 'Rudi', 'due_date' => now()->addDay()->toDateString(), 'action' => 'Ganti kabel', 'type' => 'immediate'])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $hazard->fresh()->status);

        $action = CorrectiveAction::firstOrFail();
        $this->as('user', ['employee_no' => '55', 'id' => 'p'])->put("/ssap/tindakan/{$action->id}/progres", ['to' => 'done', 'completion_note' => 'Kabel diganti'])->assertSessionHasNoErrors();
        $this->as('hse')->post("/ssap/tindakan/{$action->id}/verifikasi", ['decision' => 'accept']);

        $this->assertSame('closed', $hazard->fresh()->status);
    }

    public function test_rejecting_a_done_action_needs_a_reason_and_returns_it_to_the_pic(): void
    {
        $this->as('user')->post('/ssap/hazard', ['observed_at' => now()->subHour()->format('Y-m-d\TH:i'), 'location_id' => $this->site->id, 'category' => 'ppe', 'title' => 'APD', 'description' => 'Tidak memakai kacamata gerinda.', 'likelihood' => 3, 'consequence' => 3]);
        $hazard = Hazard::firstOrFail();
        $this->as('hse')->post("/ssap/hazard/{$hazard->id}/tugaskan", ['pic_nik' => '55', 'pic_name' => 'Rudi', 'due_date' => now()->addDay()->toDateString(), 'action' => 'Sosialisasi', 'type' => 'corrective']);
        $action = CorrectiveAction::firstOrFail();
        $this->as('user', ['employee_no' => '55', 'id' => 'p'])->put("/ssap/tindakan/{$action->id}/progres", ['to' => 'done', 'completion_note' => 'Selesai']);

        $this->as('hse')->post("/ssap/tindakan/{$action->id}/verifikasi", ['decision' => 'reject'])->assertSessionHasErrors('note');
        $this->as('hse')->post("/ssap/tindakan/{$action->id}/verifikasi", ['decision' => 'reject', 'note' => 'Bukti kurang'])->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $action->fresh()->status);
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

    public function test_dashboards_and_reports_compute_and_render(): void
    {
        ManHour::create(['year' => now()->year, 'month' => now()->month, 'location_id' => $this->site->id, 'hours' => 500_000]);
        $this->as('user')->post('/ssap/insiden', $this->incidentPayload(['type' => 'lost_time', 'persons' => [['role' => 'korban', 'name' => 'A', 'lost_days' => 4]]]));
        $this->as('user')->post('/ssap/insiden', $this->incidentPayload(['type' => 'near_miss', 'persons' => []]));

        $kpi = SafetyStats::forYear((int) now()->year)->kpis();
        $this->assertSame(2, $kpi['incidents']);
        $this->assertSame(1, $kpi['lti']);
        $this->assertSame(4, $kpi['lost_days']);
        $this->assertSame(2.0, $kpi['ltifr']);   // 1 x 1.000.000 / 500.000
        $this->assertSame(0, $kpi['days_without_lti']);

        foreach (['/ssap', '/ssap/inspeksi-dashboard', '/ssap/laporan', '/ssap/laporan/ringkasan-bulanan', '/ssap/standar', '/ssap/tindakan', '/ssap/hazard', '/ssap/inspeksi', '/ssap/inspeksi/baru'] as $url) {
            $this->as('manager')->get($url)->assertOk();
        }
        foreach (['/ssap/jam-kerja', '/ssap/inspeksi-template'] as $url) {
            $this->as('hse')->get($url)->assertOk();
        }
        foreach (['/ssap/lokasi', '/ssap/pengaturan'] as $url) {
            $this->as('admin')->get($url)->assertOk();
        }

        $csv = $this->as('manager')->get('/ssap/laporan/ekspor/incidents')->assertOk();
        $this->assertStringContainsString('INC-', $csv->streamedContent());
    }

    public function test_restricted_pages_refuse_lower_levels(): void
    {
        foreach (['/ssap/laporan', '/ssap/inspeksi-dashboard'] as $url) {
            $this->as('user')->get($url)->assertForbidden();
        }
        foreach (['/ssap/jam-kerja', '/ssap/inspeksi-template'] as $url) {
            $this->as('manager')->get($url)->assertForbidden();
        }
        foreach (['/ssap/lokasi', '/ssap/pengaturan'] as $url) {
            $this->as('hse')->get($url)->assertForbidden();
        }
        $this->as('hse')->post('/ssap/lokasi', ['code' => 'X', 'name' => 'X'])->assertForbidden();
    }

    public function test_attachments_are_only_served_to_people_who_may_see_the_report(): void
    {
        $this->as('user')->post('/ssap/insiden', $this->incidentPayload() + ['files' => [UploadedFile::fake()->image('foto.jpg')]]);
        $attachment = Incident::firstOrFail()->attachments()->firstOrFail();

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
        \App\Models\Setting::put('notify_hse', 'hse@akp.test');
        Http::fake(['mail.test/*' => Http::sequence()->push(['data' => ['id' => 'abc']], 202)->push(['data' => ['id' => 'abd']], 202)->whenEmpty(Http::response('boom', 500))]);

        $this->as('user')->post('/ssap/insiden', $this->incidentPayload(['type' => 'fatality']))->assertSessionHasNoErrors();

        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/v1/emails') && $r->hasHeader('Authorization', 'Bearer akpm_x') && in_array('hse@akp.test', $r['to']) && str_starts_with($r['subject'], '[SEGERA]'));

        // AKP Mail mati: laporan tetap tersimpan.
        $this->as('user')->post('/ssap/insiden', $this->incidentPayload())->assertSessionHasNoErrors();
        $this->assertSame(2, Incident::count());
        $this->assertSame('failed', \App\Models\EmailLog::latest('id')->first()->status);
    }
}
