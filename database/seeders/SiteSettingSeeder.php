<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaults = [
            SiteSetting::LOGO_PATH => '',
            SiteSetting::FOOTER_EDITOR_INFO => '',
            SiteSetting::FOOTER_CONTACT_INFO => '',
            SiteSetting::FOOTER_COPYRIGHT => '',
        ];

        foreach ($defaults as $key => $value) {
            SiteSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }
}
