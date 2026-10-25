<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /** @var array<string, ?string>|null */
    private static ?array $memo = null;

    /** Nilai pengaturan; bila belum pernah disimpan, memakai bawaan di config/ssap.php. */
    public static function value(string $key, ?string $default = null): ?string
    {
        self::$memo ??= self::loadAll();

        return self::$memo[$key] ?? config("ssap.defaults.$key", $default);
    }

    public static function put(string $key, ?string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        self::$memo = null;
    }

    public static function forget(): void
    {
        self::$memo = null;
    }

    /** Daftar alamat email dari pengaturan (dipisah koma/titik koma/baris baru). */
    public static function emails(string $key): array
    {
        $raw = (string) self::value($key, '');

        return collect(preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->map(fn ($e) => strtolower($e))
            ->unique()->values()->all();
    }

    private static function loadAll(): array
    {
        try {
            return Schema::hasTable('settings') ? self::query()->pluck('value', 'key')->all() : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
