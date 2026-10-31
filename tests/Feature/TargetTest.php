<?php

namespace Tests\Feature;

use App\Models\Hazard;
use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\Location;
use App\Models\Setting;
use App\Services\TargetStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Hierarki HRIS (grade, atasan), target bulanan grade 3-5, dan cakupan data: grade 3-4 sendiri, grade 5 tim, grade 6 semua. */
class TargetTest extends TestCase
{
    use RefreshDatabase;

    private Location $site;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sso.portal_url' => 'http://portal.test', 'sso.app_code' => 'ssap-safety', 'sso.secret' => 'test-secret-test-secret-test-secret-123']);
        config(['hris.url' => 'http://hris.test', 'hris.token' => 'tok', 'mailsvc.token' => null]);
        Http::preventStrayRequests();
        Setting::forget();

        // 600 = grade 6 (kepala dept) | 500 = grade 5 (atasan tim A) | 301, 401 = bawahan 500 | 302 = grade 3 di tim B (atasan 501)
        $emp = fn ($nik, $name, $dept, $grade, $boss) => ['employee_no' => $nik, 'name' => $name, 'department' => $dept, 'position' => 'Staf', 'is_active' => true, 'grade' => $grade, 'supervisor_nik' => $boss];
        Http::fake(['hris.test/*' => Http::response(['data' => [
            $emp('600', 'Kepala Dept', 'Produksi', 6, null),
            $emp('500', 'Atasan A', 'Produksi', 5, '600'),
            $emp('501', 'Atasan B', 'Logistik', 5, '600'),
            $emp('301', 'Anggota Tiga', 'Produksi', 3, '500'),
            $emp('401', 'Anggota Empat', 'Produksi', 4, '500'),
            $emp('302', 'Anggota Lain', 'Logistik', 3, '501'),
        ], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 6]])]);

        $this->site = Location::create(['code' => 'PIT', 'name' => 'Pit Utara', 'is_active' => true]);
    }

    private function as(string $nik): static
    {
        return $this->loginPortal(['id' => 'u'.$nik, 'role' => 'user', 'level' => 10, 'name' => 'U'.$nik, 'employee_no' => $nik, 'email' => $nik.'@test.test']);
    }

    private function hazard(string $nik, array $over = []): Hazard
    {
        static $n = 0;
        $n++;

        return Hazard::create($over + [
            'number' => 'HZD-T-'.$n, 'observed_at' => now()->startOfWeek()->addHours(2), 'location_id' => $this->site->id, 'category' => 'tta',
            'description' => 'Keterangan uji '.$n, 'reporter_nik' => $nik, 'reporter_name' => 'U'.$nik, 'status' => 'open', 'created_by_id' => 'u'.$nik,
        ]);
    }

    public function test_grade_3_and_4_see_only_their_own_reports(): void
    {
        $mine = $this->hazard('301');
        $teammate = $this->hazard('401');
        $other = $this->hazard('302');

        $this->as('301')->get('/ssap/hazard')->assertOk()->assertSee($mine->number)->assertDontSee($teammate->number)->assertDontSee($other->number);
        $this->as('301')->get('/ssap/hazard/'.$teammate->id)->assertForbidden();
    }

    public function test_grade_5_sees_the_team_and_grade_6_sees_every_department(): void
    {
        $own = $this->hazard('500');
        $member = $this->hazard('301');
        $outsider = $this->hazard('302');

        $this->as('500')->get('/ssap/hazard')->assertOk()->assertSee($own->number)->assertSee($member->number)->assertDontSee($outsider->number);
        $this->as('500')->get('/ssap/hazard/'.$member->id)->assertOk();
        $this->as('500')->get('/ssap/hazard/'.$outsider->id)->assertForbidden();

        $this->as('600')->get('/ssap/hazard')->assertOk()->assertSee($own->number)->assertSee($member->number)->assertSee($outsider->number);
    }

    public function test_targets_count_own_reports_but_not_findings_created_by_inspections(): void
    {
        Setting::put('target_period_3', 'week');
        Setting::put('target_hazard_3', '2');
        Setting::put('target_inspection_3', '1');

        $this->hazard('301');
        $auto = $this->hazard('301');
        $this->hazard('301', ['status' => 'rejected']);                              // ditolak: tidak dihitung
        $this->hazard('301', ['observed_at' => now()->subWeeks(2)]);               // minggu lain

        $inspection = Inspection::create([
            'number' => 'INS-T-1', 'template_name' => 'Uji', 'category' => 'other', 'location_id' => $this->site->id, 'inspected_at' => now()->startOfWeek()->addHours(3),
            'inspector_nik' => '301', 'inspector_name' => 'U301', 'total_items' => 1, 'ok_count' => 0, 'nok_count' => 1, 'na_count' => 0, 'score' => 0,
        ]);
        InspectionItem::create(['inspection_id' => $inspection->id, 'position' => 1, 'question' => 'Q', 'result' => 'nok', 'note' => 'x', 'hazard_id' => $auto->id]);

        $row = collect(app(TargetStats::class)->rows(now(), ['301']))->first();

        $this->assertSame(1, $row['hazard']);          // hanya 1 laporan sendiri
        $this->assertSame(1, $row['inspection']);
        $this->assertFalse($row['achieved']);            // 1 dari 2 hazard
        $this->assertSame(67, $row['pct']);              // (1 + 1) dari (2 + 1)

        $this->hazard('301');
        $this->assertTrue(collect(app(TargetStats::class)->rows(now(), ['301']))->first()['achieved']);
    }

    public function test_the_target_page_is_scoped_by_grade(): void
    {
        $this->as('301')->get('/ssap/target')->assertOk()->assertSee('Target Saya')->assertSee('Capaian saya')->assertDontSee('Anggota Empat');
        $this->as('500')->get('/ssap/target')->assertOk()->assertSee('Target Tim')->assertSee('Anggota Tiga')->assertSee('Anggota Empat')->assertDontSee('Anggota Lain');
        $this->as('600')->get('/ssap/target')->assertOk()->assertSee('Target Departemen')->assertSee('Anggota Lain')->assertSee('Logistik')->assertSee('Produksi');
        $this->as('600')->get('/ssap/target?department=Logistik&only=behind')->assertOk()->assertSee('Anggota Lain')->assertDontSee('Anggota Tiga');
        $this->as('600')->get('/ssap/target?date=2026-13-40')->assertSessionHasErrors('date');
        $this->as('600')->get('/ssap/target?date='.now()->subWeek()->toDateString())->assertOk()->assertSee('Anggota Lain');
    }

    public function test_the_member_list_is_paginated_and_departments_show_target_vs_actual(): void
    {
        $many = [['employee_no' => '600', 'name' => 'Kepala Dept', 'department' => 'Produksi', 'is_active' => true, 'grade' => 6, 'supervisor_nik' => null]];
        foreach (range(1, 60) as $i) {
            $many[] = ['employee_no' => (string) (7000 + $i), 'name' => sprintf('Karyawan %02d', $i), 'department' => $i <= 40 ? 'Produksi' : 'Logistik', 'position' => 'Staf', 'is_active' => true, 'grade' => 3, 'supervisor_nik' => '600'];
        }
        config(['hris.url' => 'http://hris-besar.test']);   // stub baru (stub setUp memakai hris.test)
        Http::fake(['hris-besar.test/*' => Http::response(['data' => $many, 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 61]])]);
        \Illuminate\Support\Facades\Cache::flush();

        $this->hazard('7001');   // satu orang sudah membuat 1 dari target 2

        $page1 = $this->as('600')->get('/ssap/target')->assertOk();
        $page1->assertSee('60 orang')->assertSee('halaman 1 dari 3')->assertSee('Target per departemen');
        $this->as('600')->get('/ssap/target?page=3')->assertOk()->assertSee('halaman 3 dari 3');

        $rows = app(TargetStats::class)->rows(now());
        $dept = collect(app(TargetStats::class)->byDepartment($rows))->keyBy('name');
        $this->assertSame(80, $dept['Produksi']['target_hazard']);    // 40 orang x 2
        $this->assertSame(1, $dept['Produksi']['hazard']);
        $this->assertSame(40, $dept['Produksi']['members']);
        $this->assertSame(1, $dept['Produksi']['volume_pct']);        // 1 dari 80 = 1%
        $this->assertSame(40, $dept['Logistik']['target_hazard']);
        $this->assertSame(0, $dept['Logistik']['volume_pct']);
    }

    public function test_the_head_sees_who_has_not_met_the_target(): void
    {
        foreach ([3 => 1, 4 => 1, 5 => 0] as $g => $h) {
            Setting::put("target_period_$g", 'week');
            Setting::put("target_hazard_$g", (string) $h);
            Setting::put("target_inspection_$g", '0');
        }
        $this->hazard('301');   // 301 mencapai; 401 dan 302 belum

        $rows = collect(app(TargetStats::class)->rows(now()))->keyBy('nik');
        $this->assertTrue($rows['301']['achieved']);
        $this->assertFalse($rows['401']['achieved']);
        $this->assertFalse($rows['302']['achieved']);
        $this->assertArrayNotHasKey('500', $rows->all());   // target 0 + 0 = tidak bertarget
        $this->assertSame('Atasan A', $rows['301']['supervisor_name']);
        $this->assertSame(['302', '401'], collect(app(TargetStats::class)->rows(now()))->where('achieved', false)->pluck('nik')->sort()->values()->all());

        $depts = collect(app(TargetStats::class)->byDepartment(array_values($rows->all())))->keyBy('name');
        $this->assertSame(1, $depts['Logistik']['behind']);
        $this->assertSame(1, $depts['Produksi']['behind']);
    }

    public function test_each_grade_can_use_a_weekly_or_monthly_period(): void
    {
        Setting::put('target_period_3', 'week');
        Setting::put('target_hazard_3', '1');
        Setting::put('target_period_4', 'month');
        Setting::put('target_hazard_4', '1');
        Setting::put('target_hazard_5', '0');

        // Laporan 3 minggu lalu: di luar minggu ini, tetapi (kadang) masih dalam bulan ini.
        $old = now()->subWeeks(3)->startOfWeek()->addHours(2);
        $this->hazard('301', ['observed_at' => $old]);
        $this->hazard('401', ['observed_at' => $old]);

        $rows = collect(app(TargetStats::class)->rows(now()))->keyBy('nik');
        $this->assertSame('week', $rows['301']['period']);
        $this->assertSame(0, $rows['301']['hazard']);
        $this->assertFalse($rows['301']['achieved']);
        $this->assertSame('month', $rows['401']['period']);
        $this->assertSame($old->isSameMonth(now()) ? 1 : 0, $rows['401']['hazard']);
        $this->assertArrayNotHasKey('500', $rows->all());    // grade 5: target 0 + 0 = tidak bertarget

        [$from, $to] = TargetStats::range('week', now());
        $this->assertTrue($from->isMonday());
        $this->assertTrue($to->isSunday());
    }

    public function test_grades_come_from_hris_and_the_settings_page_lists_them(): void
    {
        $this->assertSame([3, 4, 5, 6], TargetStats::grades());
        $this->assertSame(['hazard' => 2, 'inspection' => 0, 'period' => 'week'], TargetStats::targets()[3]);   // contoh: grade 3, 2 per minggu

        $this->loginPortal(['id' => 'adm', 'role' => 'admin', 'level' => 100, 'name' => 'Admin', 'employee_no' => '1', 'email' => 'a@test.test']);
        $this->get('/ssap/pengaturan')->assertOk()->assertSee('Grade 6')->assertSee('per minggu')->assertSee('Produksi');
    }

    public function test_admin_sets_targets_in_settings(): void
    {
        $this->loginPortal(['id' => 'adm', 'role' => 'admin', 'level' => 100, 'name' => 'Admin', 'employee_no' => '1', 'email' => 'a@test.test']);

        $payload = ['remind_days_before' => 2, 'target_period' => [3 => 'week', 4 => 'month', 5 => 'week', 6 => 'week'],
            'target_hazard' => [3 => 4, 4 => 5, 5 => 6, 6 => 0], 'target_inspection' => [3 => 0, 4 => 1, 5 => 2, 6 => 0]];
        $this->put('/ssap/pengaturan', $payload)->assertSessionHasNoErrors();

        $this->assertSame([
            3 => ['hazard' => 4, 'inspection' => 0, 'period' => 'week'], 4 => ['hazard' => 5, 'inspection' => 1, 'period' => 'month'],
            5 => ['hazard' => 6, 'inspection' => 2, 'period' => 'week'], 6 => ['hazard' => 0, 'inspection' => 0, 'period' => 'week'],
        ], TargetStats::targets());
        $this->put('/ssap/pengaturan', ['target_period' => [3 => 'year']] + $payload)->assertSessionHasErrors('target_period.*');
        $this->put('/ssap/pengaturan', ['target_hazard' => [3 => -1, 4 => 1, 5 => 1, 6 => 0]] + $payload)->assertSessionHasErrors('target_hazard.*');
    }
}
