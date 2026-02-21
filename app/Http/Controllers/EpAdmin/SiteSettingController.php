<?php

namespace App\Http\Controllers\EpAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EpAdmin\SiteSettingUpdateRequest;
use App\Models\SiteSetting;
use App\Support\DiskUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SiteSettingController extends Controller
{
    public function index(): Response
    {
        $keys = SiteSetting::defaultKeys();
        $settings = SiteSetting::toKeyValueArray($keys);

        $logoPath = $settings[SiteSetting::LOGO_PATH] ?? null;
        $disk = (string) config('epaper.disk');
        $logoUrl = $logoPath !== null && $logoPath !== ''
            ? DiskUrl::fromPath($disk, (string) $logoPath)
            : null;

        return Inertia::render('EpAdmin/Settings/Index', [
            'settings' => [
                SiteSetting::LOGO_PATH => (string) ($settings[SiteSetting::LOGO_PATH] ?? ''),
                SiteSetting::FOOTER_EDITOR_INFO => (string) ($settings[SiteSetting::FOOTER_EDITOR_INFO] ?? ''),
                SiteSetting::FOOTER_CONTACT_INFO => (string) ($settings[SiteSetting::FOOTER_CONTACT_INFO] ?? ''),
                SiteSetting::FOOTER_COPYRIGHT => (string) ($settings[SiteSetting::FOOTER_COPYRIGHT] ?? ''),
            ],
            'logo_url' => $logoUrl,
        ]);
    }

    public function update(SiteSettingUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $disk = Storage::disk(config('epaper.disk'));
        $oldLogoPath = SiteSetting::getValue(SiteSetting::LOGO_PATH);

        if ($request->hasFile('logo')) {
            $newLogoPath = ltrim($request->file('logo')->store('epaper/settings/logo', config('epaper.disk')), '/');

            SiteSetting::setValue(SiteSetting::LOGO_PATH, $newLogoPath);

            if ($oldLogoPath !== null && $oldLogoPath !== '' && $oldLogoPath !== $newLogoPath && $disk->exists($oldLogoPath)) {
                $disk->delete($oldLogoPath);
            }
        } elseif ($request->boolean('remove_logo')) {
            SiteSetting::setValue(SiteSetting::LOGO_PATH, null);

            if ($oldLogoPath !== null && $oldLogoPath !== '' && $disk->exists($oldLogoPath)) {
                $disk->delete($oldLogoPath);
            }
        }

        SiteSetting::setValue(SiteSetting::FOOTER_EDITOR_INFO, $this->nullableString($validated['footer_editor_info'] ?? null));
        SiteSetting::setValue(SiteSetting::FOOTER_CONTACT_INFO, $this->nullableString($validated['footer_contact_info'] ?? null));
        SiteSetting::setValue(SiteSetting::FOOTER_COPYRIGHT, $this->nullableString($validated['footer_copyright'] ?? null));

        return redirect()
            ->route('epadmin.settings.index')
            ->with('success', 'Site settings updated successfully.');
    }

    private function nullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
