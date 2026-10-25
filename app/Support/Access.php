<?php

namespace App\Support;

use Akp\PortalSso\PortalUser;

/**
 * Hak akses SSAP Safety. Semua pemeriksaan memakai KODE level (config/sso.php), bukan angka tingkat.
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

    /** Admin/HSE/Manajer: melihat seluruh data dan dashboard keseluruhan. */
    public static function canViewAll(?PortalUser $user = null): bool
    {
        $user ??= self::user();

        return $user?->hasRole('admin', 'hse', 'manager') ?? false;
    }
}
