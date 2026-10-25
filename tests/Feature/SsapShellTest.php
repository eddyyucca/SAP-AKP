<?php

namespace Tests\Feature;

use Tests\TestCase;

class SsapShellTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['sso.portal_url' => 'http://portal.test', 'sso.app_code' => 'ssap-safety', 'sso.secret' => 'test-secret-test-secret-test-secret-123']);
    }

    public function test_guests_are_sent_to_the_portal_and_the_health_check_is_open(): void
    {
        foreach (['/', '/ssap'] as $path) {
            $res = $this->get($path);
            $res->assertRedirect();
            $this->assertStringStartsWith('http://portal.test/login', $res->headers->get('Location'), $path);
        }

        $this->get('/up')->assertOk();
    }

    public function test_known_levels_open_the_dashboard_and_unknown_levels_are_refused(): void
    {
        foreach (['admin', 'user'] as $role) {
            $this->loginPortal(['id' => '2', 'name' => 'Budi', 'role' => $role, 'level' => 10])->get('/ssap')->assertOk()->assertSee('Dashboard SSAP Safety')->assertSee('Halo, Budi');
        }

        $this->loginPortal(['role' => 'tamu', 'level' => 1])->get('/ssap')->assertForbidden();
    }

    public function test_root_redirects_to_the_dashboard(): void
    {
        $this->loginPortal(['role' => 'user', 'level' => 10])->get('/')->assertRedirect('/ssap');
    }

    public function test_sidebar_follows_the_hris_pattern(): void
    {
        $html = $this->loginPortal(['role' => 'admin', 'level' => 100])->get('/ssap')->getContent();
        preg_match('/<aside class="sidebar-desktop".*?<\/aside>/s', $html, $m);
        $sidebar = $m[0] ?? '';

        $this->assertStringContainsString('Dashboard', $sidebar);
        $this->assertStringContainsString('sidebar-link active', $sidebar);
        $this->assertStringNotContainsString('sidebar-title', $sidebar, 'logo saja, seperti HRIS');
        $this->assertStringNotContainsString('Menu Utama', $sidebar);
    }

    public function test_declared_levels_are_exactly_the_ones_the_routes_use(): void
    {
        $this->assertSame(['admin', 'user'], array_keys(config('sso.roles')));
    }
}
