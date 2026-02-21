<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    /** @use HasFactory<\Database\Factories\SiteSettingFactory> */
    use HasFactory;

    public const LOGO_PATH = 'logo_path';

    public const FOOTER_EDITOR_INFO = 'footer_editor_info';

    public const FOOTER_CONTACT_INFO = 'footer_contact_info';

    public const FOOTER_COPYRIGHT = 'footer_copyright';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => 'string',
            'value' => 'string',
        ];
    }

    public static function getValue(string $key, ?string $default = null): ?string
    {
        return self::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function setValue(string $key, ?string $value): self
    {
        /** @var self $setting */
        $setting = self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        return $setting;
    }

    /**
     * @param  array<int, string>|null  $keys
     * @return array<string, string|null>
     */
    public static function toKeyValueArray(?array $keys = null): array
    {
        $query = self::query();

        if ($keys !== null) {
            $query->whereIn('key', $keys);
        }

        /** @var array<string, string|null> $items */
        $items = $query->pluck('value', 'key')->toArray();

        return $items;
    }

    /**
     * @return list<string>
     */
    public static function defaultKeys(): array
    {
        return [
            self::LOGO_PATH,
            self::FOOTER_EDITOR_INFO,
            self::FOOTER_CONTACT_INFO,
            self::FOOTER_COPYRIGHT,
        ];
    }
}
