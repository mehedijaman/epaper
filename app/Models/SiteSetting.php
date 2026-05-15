<?php

namespace App\Models;

use Database\Factories\SiteSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    /** @use HasFactory<SiteSettingFactory> */
    use HasFactory;

    public const LOGO_PATH = 'logo_path';

    public const FAVICON_PATH = 'favicon_path';

    public const SITE_NAME = 'site_name';

    public const FOOTER_EDITOR_INFO = 'footer_editor_info';

    public const FOOTER_CONTACT_INFO = 'footer_contact_info';

    public const FOOTER_COPYRIGHT = 'footer_copyright';

    public const SITE_URL = 'site_url';

    public const SOCIAL_FACEBOOK = 'social_facebook';

    public const SOCIAL_X = 'social_x';

    public const SOCIAL_YOUTUBE = 'social_youtube';

    public const SOCIAL_LINKEDIN = 'social_linkedin';

    public const SOCIAL_INSTAGRAM = 'social_instagram';

    public const SOCIAL_PINTEREST = 'social_pinterest';

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
            self::FAVICON_PATH,
            self::SITE_NAME,
            self::SITE_URL,
            self::FOOTER_EDITOR_INFO,
            self::FOOTER_CONTACT_INFO,
            self::FOOTER_COPYRIGHT,
            self::SOCIAL_FACEBOOK,
            self::SOCIAL_X,
            self::SOCIAL_YOUTUBE,
            self::SOCIAL_LINKEDIN,
            self::SOCIAL_INSTAGRAM,
            self::SOCIAL_PINTEREST,
        ];
    }
}
