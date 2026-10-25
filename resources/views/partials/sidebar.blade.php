{{--
    Sidebar mengikuti pola HRIS: Dashboard tunggal di atas, lalu grup yang bisa dilipat.
    Menu hanya muncul bila pengguna berhak (level dicek lagi di route dan controller).
    Grup yang berisi halaman aktif terbuka, lainnya tertutup.
--}}
@php
    $canManage = \App\Support\Access::canManage();
    $canViewAll = \App\Support\Access::canViewAll();
    $isAdmin = \App\Support\Access::isAdmin();

    $item = fn (string $title, string $icon, string $route, array $match, array $params = []) => [
        'title' => $title, 'icon' => $icon, 'href' => route($route, $params), 'active' => request()->routeIs(...$match),
    ];

    $menus = [
        $item('Dashboard', 'bi bi-grid-1x2-fill', 'ssap.home', ['ssap.home']),

        ['title' => 'Buat Laporan', 'children' => [
            $item('Lapor Insiden', 'bi bi-exclamation-octagon', 'ssap.incidents.create', ['ssap.incidents.create']),
            $item('Lapor Hazard', 'bi bi-cone-striped', 'ssap.hazards.create', ['ssap.hazards.create']),
        ]],

        ['title' => 'Data Safety', 'children' => array_values(array_filter([
            $item($canViewAll ? 'Daftar Insiden' : 'Insiden Saya', 'bi bi-journal-text', 'ssap.incidents.index', ['ssap.incidents.index', 'ssap.incidents.show', 'ssap.incidents.edit']),
            $item($canViewAll ? 'Daftar Hazard' : 'Hazard Saya', 'bi bi-shield-exclamation', 'ssap.hazards.index', ['ssap.hazards.index', 'ssap.hazards.show', 'ssap.hazards.edit']),
            $item($canViewAll ? 'Tindakan Korektif' : 'Tindakan Saya', 'bi bi-list-check', 'ssap.actions.index', ['ssap.actions.*']),
        ]))],
    ];

    if ($canViewAll) {
        $menus[] = ['title' => 'Analisis & Laporan', 'children' => [
            $item('Laporan & Grafik', 'bi bi-bar-chart-line', 'ssap.reports.index', ['ssap.reports.index']),
            $item('Ringkasan Bulanan', 'bi bi-file-earmark-text', 'ssap.reports.monthly', ['ssap.reports.monthly']),
        ]];
    }

    if ($canManage) {
        $admin = [$item('Jam Kerja', 'bi bi-clock-history', 'ssap.manhours.index', ['ssap.manhours.*'])];
        if ($isAdmin) {
            $admin[] = $item('Lokasi / Site', 'bi bi-geo-alt', 'ssap.locations.index', ['ssap.locations.*']);
            $admin[] = $item('Pengaturan', 'bi bi-gear', 'ssap.settings.index', ['ssap.settings.*']);
        }
        $menus[] = ['title' => 'Pengelolaan', 'children' => $admin];
    }

    $menus[] = $item('Standar Pelaporan', 'bi bi-journal-check', 'ssap.standard', ['ssap.standard']);

    foreach ($menus as &$menu) {
        if (isset($menu['children'])) {
            $menu['active'] = collect($menu['children'])->contains('active', true);
        }
    }
    unset($menu);
@endphp

<div class="sidebar-section">
    @foreach ($menus as $menu)
        @if (isset($menu['children']))
            <div class="sidebar-group mt-2">
                <div class="sidebar-group-toggle {{ $menu['active'] ? 'has-active' : 'collapsed' }}" data-sidebar-toggle>
                    <span>{{ $menu['title'] }}</span>
                    <i class="bi bi-chevron-down sidebar-chevron"></i>
                </div>
                <div class="sidebar-collapse-content {{ $menu['active'] ? '' : 'closed' }}">
                    <div class="sidebar-collapse-inner">
                        @foreach ($menu['children'] as $child)
                            @include('partials.sidebar-link', ['item' => $child])
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            @include('partials.sidebar-link', ['item' => $menu])
        @endif
    @endforeach
</div>
