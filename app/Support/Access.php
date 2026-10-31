<?php

namespace App\Support;

use Akp\PortalSso\PortalUser;
use App\Services\HrisEmployees;

/**
 * Hak akses SAP Safety. Semua pemeriksaan memakai KODE level (config/sso.php), bukan angka tingkat.
 *
 *  admin   : semua, termasuk lokasi, jam kerja, pengaturan, hapus data
 *  hse     : mengelola laporan (investigasi, penutupan, tindakan, verifikasi)
 *  manager : melihat semua data, dashboard, dan laporan
 *  user    : membuat laporan, melihat laporan sendiri, mengerjakan tindakan yang ditugaskan
 */
class Access
{
    public static function user(): ?PortalUser
    {
        return portal_user();
    }

    public static function isAdmin(?PortalUser $user = null): bool
    {
        $user ??= self::user();

        return $user?->hasRole('admin') ?? false;
    }

    /** Admin/HSE: boleh mengubah status, menginvestigasi, menutup, menetapkan tindakan. */
    public static function canManage(?PortalUser $user = null): bool
    {
        $user ??= self::user();

        return $user?->hasRole('admin', 'hse') ?? false;
    }

    /**
     * Cakupan data menurut jabatan di HRIS:
     *  all  = admin/HSE/manajer, atau grade 6+ (kepala departemen): seluruh departemen
     *  team = grade 5: dirinya dan seluruh bawahan (menurut atasan langsung di HRIS)
     *  self = grade 3-4 dan lainnya: hanya miliknya sendiri
     *
     * @return array{mode:string,grade:?int,nik:?string,niks:list<string>}
     */
    public static function scope(?PortalUser $user = null): array
    {
        $user ??= self::user();
        $nik = filled($user?->employee_no) ? (string) $user->employee_no : null;
        $key = 'ssap.scope.'.($user?->id ?? '-').'.'.$nik;
        $bag = request()->attributes;

        if ($bag->has($key)) {
            return $bag->get($key);
        }

        $grade = $nik ? app(HrisEmployees::class)->gradeOf($nik) : null;

        $scope = match (true) {
            $user === null => ['mode' => 'self', 'niks' => []],
            self::canViewAll($user) || ($grade !== null && $grade >= 6) => ['mode' => 'all', 'niks' => []],
            $grade === 5 && $nik !== null => ['mode' => 'team', 'niks' => array_merge([$nik], app(HrisEmployees::class)->subordinateNiks($nik))],
            default => ['mode' => 'self', 'niks' => $nik ? [$nik] : []],
        };
        $scope += ['grade' => $grade, 'nik' => $nik];
        $bag->set($key, $scope);

        return $scope;
    }

    /** Admin/HSE/Manajer: melihat seluruh data dan dashboard keseluruhan. */
    public static function canViewAll(?PortalUser $user = null): bool
    {
        $user ??= self::user();

        return $user?->hasRole('admin', 'hse', 'manager') ?? false;
    }
}
