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

        $disk = (string) config('epaper.disk');

        $logoPath = $settings[SiteSetting::LOGO_PATH] ?? null;
        $logoUrl = $logoPath !== null && $logoPath !== ''
            ? DiskUrl::fromPath($disk, (string) $logoPath)
            : null;

        $faviconPath = $settings[SiteSetting::FAVICON_PATH] ?? null;
        $faviconUrl = $faviconPath !== null && $faviconPath !== ''
            ? DiskUrl::fromPath($disk, (string) $faviconPath)
            : null;

        return Inertia::render('EpAdmin/Settings/Index', [
            'settings' => [
                SiteSetting::LOGO_PATH => (string) ($settings[SiteSetting::LOGO_PATH] ?? ''),
                SiteSetting::FAVICON_PATH => (string) ($settings[SiteSetting::FAVICON_PATH] ?? ''),
                SiteSetting::SITE_NAME => (string) ($settings[SiteSetting::SITE_NAME] ?? ''),
                SiteSetting::SITE_URL => (string) ($settings[SiteSetting::SITE_URL] ?? ''),
                SiteSetting::FOOTER_EDITOR_INFO => (string) ($settings[SiteSetting::FOOTER_EDITOR_INFO] ?? ''),
                SiteSetting::FOOTER_CONTACT_INFO => (string) ($settings[SiteSetting::FOOTER_CONTACT_INFO] ?? ''),
                SiteSetting::FOOTER_COPYRIGHT => (string) ($settings[SiteSetting::FOOTER_COPYRIGHT] ?? ''),
                SiteSetting::SOCIAL_FACEBOOK => (string) ($settings[SiteSetting::SOCIAL_FACEBOOK] ?? ''),
                SiteSetting::SOCIAL_X => (string) ($settings[SiteSetting::SOCIAL_X] ?? ''),
                SiteSetting::SOCIAL_YOUTUBE => (string) ($settings[SiteSetting::SOCIAL_YOUTUBE] ?? ''),
                SiteSetting::SOCIAL_LINKEDIN => (string) ($settings[SiteSetting::SOCIAL_LINKEDIN] ?? ''),
                SiteSetting::SOCIAL_INSTAGRAM => (string) ($settings[SiteSetting::SOCIAL_INSTAGRAM] ?? ''),
                SiteSetting::SOCIAL_PINTEREST => (string) ($settings[SiteSetting::SOCIAL_PINTEREST] ?? ''),
            ],
            'logo_url' => $logoUrl,
            'favicon_url' => $faviconUrl,
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

        $oldFaviconPath = SiteSetting::getValue(SiteSetting::FAVICON_PATH);

        if ($request->hasFile('favicon')) {
            $newFaviconPath = ltrim($request->file('favicon')->store('epaper/settings/favicon', config('epaper.disk')), '/');

            SiteSetting::setValue(SiteSetting::FAVICON_PATH, $newFaviconPath);

            if ($oldFaviconPath !== null && $oldFaviconPath !== '' && $oldFaviconPath !== $newFaviconPath && $disk->exists($oldFaviconPath)) {
                $disk->delete($oldFaviconPath);
            }
        } elseif ($request->boolean('remove_favicon')) {
            SiteSetting::setValue(SiteSetting::FAVICON_PATH, null);

            if ($oldFaviconPath !== null && $oldFaviconPath !== '' && $disk->exists($oldFaviconPath)) {
                $disk->delete($oldFaviconPath);
            }
        }

        SiteSetting::setValue(SiteSetting::SITE_NAME, $this->nullableString($validated['site_name'] ?? null));
        SiteSetting::setValue(SiteSetting::SITE_URL, $this->nullableString($validated['site_url'] ?? null));
        SiteSetting::setValue(SiteSetting::FOOTER_EDITOR_INFO, $this->nullableString($validated['footer_editor_info'] ?? null));
        SiteSetting::setValue(SiteSetting::FOOTER_CONTACT_INFO, $this->nullableString($validated['footer_contact_info'] ?? null));
        SiteSetting::setValue(SiteSetting::FOOTER_COPYRIGHT, $this->nullableString($validated['footer_copyright'] ?? null));
        SiteSetting::setValue(SiteSetting::SOCIAL_FACEBOOK, $this->nullableString($validated['social_facebook'] ?? null));
        SiteSetting::setValue(SiteSetting::SOCIAL_X, $this->nullableString($validated['social_x'] ?? null));
        SiteSetting::setValue(SiteSetting::SOCIAL_YOUTUBE, $this->nullableString($validated['social_youtube'] ?? null));
        SiteSetting::setValue(SiteSetting::SOCIAL_LINKEDIN, $this->nullableString($validated['social_linkedin'] ?? null));
        SiteSetting::setValue(SiteSetting::SOCIAL_INSTAGRAM, $this->nullableString($validated['social_instagram'] ?? null));
        SiteSetting::setValue(SiteSetting::SOCIAL_PINTEREST, $this->nullableString($validated['social_pinterest'] ?? null));

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
