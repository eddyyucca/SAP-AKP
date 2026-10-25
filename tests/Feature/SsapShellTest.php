<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SsapShellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sso.portal_url' => 'http://portal.test', 'sso.app_code' => 'ssap-safety', 'sso.secret' => 'test-secret-test-secret-test-secret-123']);
    }

    public function test_guests_are_sent_to_the_portal_and_the_health_check_is_open(): void
    {
        foreach (['/', '/ssap', '/ssap/insiden', '/ssap/inspeksi'] as $path) {
            $res = $this->get($path);
            $res->assertRedirect();
            $this->assertStringStartsWith('http://portal.test/login', $res->headers->get('Location'), $path);
        }

        $this->get('/up')->assertOk();
    }

    public function test_every_declared_level_opens_the_dashboard_and_unknown_levels_are_refused(): void
    {
        foreach (['admin', 'hse', 'manager', 'user'] as $role) {
            $this->loginPortal(['id' => '2', 'name' => 'Budi', 'role' => $role, 'level' => 10])->get('/ssap')->assertOk()->assertSee('Dashboard SSAP Safety')->assertSee('Budi');
        }

        $this->loginPortal(['role' => 'tamu', 'level' => 1])->get('/ssap')->assertForbidden();
    }

    public function test_root_redirects_to_the_dashboard(): void
    {
        $this->loginPortal(['role' => 'user', 'level' => 10])->get('/')->assertRedirect('/ssap');
    }

    public function test_sidebar_shows_only_what_the_level_may_open(): void
    {
        $sidebar = function (string $role): string {
            $html = $this->loginPortal(['role' => $role, 'level' => 10])->get('/ssap')->getContent();
            preg_match('/<aside class="sidebar-desktop".*?<\/aside>/s', $html, $m);

            return $m[0] ?? '';
        };

        $admin = $sidebar('admin');
        foreach (['Dashboard', 'Lapor Insiden', 'Inspeksi Rutin', 'Dashboard Inspeksi', 'Laporan &amp; Grafik', 'Template Inspeksi', 'Jam Kerja', 'Lokasi / Site', 'Pengaturan', 'Standar Pelaporan'] as $label) {
            $this->assertStringContainsString($label, $admin, $label);
        }
        $this->assertStringContainsString('sidebar-link active', $admin);
        $this->assertStringNotContainsString('sidebar-title', $admin, 'logo saja, seperti HRIS');

        $hse = $sidebar('hse');
        $this->assertStringContainsString('Jam Kerja', $hse);
        $this->assertStringNotContainsString('Pengaturan', $hse);

        $manager = $sidebar('manager');
        $this->assertStringContainsString('Laporan &amp; Grafik', $manager);
        $this->assertStringNotContainsString('Jam Kerja', $manager);

        $user = $sidebar('user');
        $this->assertStringContainsString('Insiden Saya', $user);
        $this->assertStringNotContainsString('Laporan &amp; Grafik', $user);
        $this->assertStringNotContainsString('Pengaturan', $user);
    }

    public function test_declared_levels_are_exactly_the_ones_the_routes_use(): void
    {
        $this->assertSame(['admin', 'hse', 'manager', 'user'], array_keys(config('sso.roles')));
    }
}
