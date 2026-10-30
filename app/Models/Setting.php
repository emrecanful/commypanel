<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Small key-value store for panel-wide settings.
 *
 * @property string $key
 * @property string|null $value
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public static function read(string $key): ?string
    {
        return self::query()->whereKey($key)->value('value');
    }

    public static function write(string $key, ?string $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
