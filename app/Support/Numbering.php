<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/** Nomor laporan berurutan per tahun: INC-2026-0001, HZD-2026-0001. */
class Numbering
{
    /**
     * Jalankan $create dengan nomor berikutnya; bila dua orang melapor bersamaan (nomor kembar),
     * ulangi dengan nomor baru.
     *
     * @param  class-string<Model>  $model
     * @param  callable(string):mixed  $create
     */
    public static function create(string $prefix, string $model, callable $create, ?int $year = null): mixed
    {
        $attempts = 0;

        while (true) {
            try {
                return $create(self::next($prefix, $model, $year));
            } catch (QueryException $e) {
                if (++$attempts >= 5 || ! str_contains(strtolower($e->getMessage()), 'unique') && ! str_contains(strtolower($e->getMessage()), 'duplicate')) {
                    throw $e;
                }
            }
        }
    }

    /** @param  class-string<Model>  $model */
    public static function next(string $prefix, string $model, ?int $year = null): string
    {
        $year ??= (int) now()->year;
        $head = "{$prefix}-{$year}-";

        $last = $model::withTrashed()->where('number', 'like', $head.'%')->max('number');
        $seq = $last ? ((int) substr($last, strlen($head))) + 1 : 1;

        return $head.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
