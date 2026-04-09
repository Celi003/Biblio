<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function getString(string $key, string $default = ''): string
    {
        /** @var self|null $row */
        $row = self::query()->find($key);
        return $row ? (string) $row->value : $default;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $v = self::getString($key, (string) $default);
        return (int) $v;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $v = strtolower(self::getString($key, $default ? '1' : '0'));
        return in_array($v, ['1', 'true', 'yes', 'on'], true);
    }

    public static function put(string $key, string|int|bool $value): void
    {
        self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]
        );
    }
}
