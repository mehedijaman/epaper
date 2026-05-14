<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import TipTapEditor from '@/components/TipTapEditor.vue';
import EpAdminLayout from '@/layouts/EpAdminLayout.vue';
import type { BreadcrumbItem } from '@/types';

type SiteSettings = {
    logo_path: string;
    favicon_path: string;
    site_name: string;
    footer_editor_info: string;
    footer_contact_info: string;
    footer_copyright: string;
};

const props = defineProps<{
    settings: SiteSettings;
    logo_url: string | null;
    favicon_url: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Site Settings', href: '/admin/settings' },
];

const logoInputKey = ref(0);
const logoInputRef = ref<HTMLInputElement | null>(null);
const selectedLogo = ref<File | null>(null);
const tempLogoUrl = ref<string | null>(null);
const storedLogoUrl = ref<string | null>(props.logo_url);
const isDropzoneActive = ref(false);
const clientLogoError = ref('');

const faviconInputKey = ref(0);
const faviconInputRef = ref<HTMLInputElement | null>(null);
const selectedFavicon = ref<File | null>(null);
const tempFaviconUrl = ref<string | null>(null);
const storedFaviconUrl = ref<string | null>(props.favicon_url);
const isFaviconDropzoneActive = ref(false);
const clientFaviconError = ref('');

const acceptedLogoMimeTypes = new Set<string>([
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/svg+xml',
]);

const acceptedLogoExtensions = ['.jpg', '.jpeg', '.png', '.webp', '.svg'];
const maxLogoSizeInBytes = 5 * 1024 * 1024;

const acceptedFaviconMimeTypes = new Set<string>([
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/svg+xml',
    'image/x-icon',
    'image/vnd.microsoft.icon',
]);

const acceptedFaviconExtensions = ['.jpg', '.jpeg', '.png', '.webp', '.svg', '.ico'];
const maxFaviconSizeInBytes = 2 * 1024 * 1024;

const form = useForm<{
    logo: File | null;
    remove_logo: boolean;
    favicon: File | null;
    remove_favicon: boolean;
    site_name: string;
    footer_editor_info: string;
    footer_contact_info: string;
    footer_copyright: string;
}>({
    logo: null,
    remove_logo: false,
    favicon: null,
    remove_favicon: false,
    site_name: props.settings.site_name,
    footer_editor_info: props.settings.footer_editor_info,
    footer_contact_info: props.settings.footer_contact_info,
    footer_copyright: props.settings.footer_copyright,
});

const logoPreviewUrl = computed(() => tempLogoUrl.value ?? storedLogoUrl.value);
const logoErrorMessage = computed(() => clientLogoError.value !== '' ? clientLogoError.value : form.errors.logo);
const hasStoredLogo = computed(() => storedLogoUrl.value !== null && storedLogoUrl.value !== '');

const faviconPreviewUrl = computed(() => tempFaviconUrl.value ?? storedFaviconUrl.value);
const faviconErrorMessage = computed(() => clientFaviconError.value !== '' ? clientFaviconError.value : form.errors.favicon);
const hasStoredFavicon = computed(() => storedFaviconUrl.value !== null && storedFaviconUrl.value !== '');

watch(
    () => props.logo_url,
    (value) => {
        storedLogoUrl.value = value;
    },
);

watch(
    () => props.favicon_url,
    (value) => {
        storedFaviconUrl.value = value;
    },
);

watch(selectedLogo, (file) => {
    if (tempLogoUrl.value !== null) {
        URL.revokeObjectURL(tempLogoUrl.value);
        tempLogoUrl.value = null;
    }

    if (file !== null) {
        tempLogoUrl.value = URL.createObjectURL(file);
    }
});

watch(selectedFavicon, (file) => {
    if (tempFaviconUrl.value !== null) {
        URL.revokeObjectURL(tempFaviconUrl.value);
        tempFaviconUrl.value = null;
    }

    if (file !== null) {
        tempFaviconUrl.value = URL.createObjectURL(file);
    }
});

onBeforeUnmount(() => {
    if (tempLogoUrl.value !== null) {
        URL.revokeObjectURL(tempLogoUrl.value);
    }

    if (tempFaviconUrl.value !== null) {
        URL.revokeObjectURL(tempFaviconUrl.value);
    }
});

function validateLogoFile(file: File): string | null {
    const fileName = file.name.toLowerCase();
    const hasAllowedExtension = acceptedLogoExtensions.some((extension) => fileName.endsWith(extension));
    const hasAllowedMimeType = file.type !== '' && acceptedLogoMimeTypes.has(file.type);

    if (!hasAllowedExtension && !hasAllowedMimeType) {
        return 'Only JPG, PNG, WEBP, or SVG files are allowed.';
    }

    if (file.size > maxLogoSizeInBytes) {
        return 'Logo size must be 5MB or less.';
    }

    return null;
}

function applyLogoFile(file: File | null): void {
    clientLogoError.value = '';
    form.remove_logo = false;

    if (file === null) {
        selectedLogo.value = null;
        form.logo = null;
        return;
    }

    const validationError = validateLogoFile(file);

    if (validationError !== null) {
        clientLogoError.value = validationError;
        selectedLogo.value = null;
        form.logo = null;
        logoInputKey.value += 1;
        return;
    }

    selectedLogo.value = file;
    form.logo = file;
    form.clearErrors('logo');
}

function onLogoSelected(event: Event): void {
    const input = event.target as HTMLInputElement | null;
    const file = input?.files?.[0] ?? null;

    applyLogoFile(file);
}

function openLogoPicker(): void {
    logoInputRef.value?.click();
}

function onDropzoneDragEnter(): void {
    isDropzoneActive.value = true;
}

function onDropzoneDragOver(event: DragEvent): void {
    isDropzoneActive.value = true;

    if (event.dataTransfer !== null) {
        event.dataTransfer.dropEffect = 'copy';
    }
}

function onDropzoneDragLeave(event: DragEvent): void {
    const currentTarget = event.currentTarget;
    const relatedTarget = event.relatedTarget;

    if (
        currentTarget instanceof HTMLElement
        && relatedTarget instanceof Node
        && currentTarget.contains(relatedTarget)
    ) {
        return;
    }

    isDropzoneActive.value = false;
}

function onDropzoneDrop(event: DragEvent): void {
    isDropzoneActive.value = false;
    const file = event.dataTransfer?.files?.[0] ?? null;
    applyLogoFile(file);
}

function clearSelectedLogo(): void {
    clientLogoError.value = '';
    selectedLogo.value = null;
    form.logo = null;
    form.remove_logo = false;
    logoInputKey.value += 1;
}

function validateFaviconFile(file: File): string | null {
    const fileName = file.name.toLowerCase();
    const hasAllowedExtension = acceptedFaviconExtensions.some((extension) => fileName.endsWith(extension));
    const hasAllowedMimeType = file.type !== '' && acceptedFaviconMimeTypes.has(file.type);

    if (!hasAllowedExtension && !hasAllowedMimeType) {
        return 'Only JPG, PNG, WEBP, SVG, or ICO files are allowed.';
    }

    if (file.size > maxFaviconSizeInBytes) {
        return 'Favicon size must be 2MB or less.';
    }

    return null;
}

function applyFaviconFile(file: File | null): void {
    clientFaviconError.value = '';
    form.remove_favicon = false;

    if (file === null) {
        selectedFavicon.value = null;
        form.favicon = null;
        return;
    }

    const validationError = validateFaviconFile(file);

    if (validationError !== null) {
        clientFaviconError.value = validationError;
        selectedFavicon.value = null;
        form.favicon = null;
        faviconInputKey.value += 1;
        return;
    }

    selectedFavicon.value = file;
    form.favicon = file;
    form.clearErrors('favicon');
}

function onFaviconSelected(event: Event): void {
    const input = event.target as HTMLInputElement | null;
    const file = input?.files?.[0] ?? null;

    applyFaviconFile(file);
}

function openFaviconPicker(): void {
    faviconInputRef.value?.click();
}

function onFaviconDropzoneDragEnter(): void {
    isFaviconDropzoneActive.value = true;
}

function onFaviconDropzoneDragOver(event: DragEvent): void {
    isFaviconDropzoneActive.value = true;

    if (event.dataTransfer !== null) {
        event.dataTransfer.dropEffect = 'copy';
    }
}

function onFaviconDropzoneDragLeave(event: DragEvent): void {
    const currentTarget = event.currentTarget;
    const relatedTarget = event.relatedTarget;

    if (
        currentTarget instanceof HTMLElement
        && relatedTarget instanceof Node
        && currentTarget.contains(relatedTarget)
    ) {
        return;
    }

    isFaviconDropzoneActive.value = false;
}

function onFaviconDropzoneDrop(event: DragEvent): void {
    isFaviconDropzoneActive.value = false;
    const file = event.dataTransfer?.files?.[0] ?? null;
    applyFaviconFile(file);
}

function clearSelectedFavicon(): void {
    clientFaviconError.value = '';
    selectedFavicon.value = null;
    form.favicon = null;
    form.remove_favicon = false;
    faviconInputKey.value += 1;
}

function submit(): void {
    if (clientLogoError.value !== '' || clientFaviconError.value !== '') {
        return;
    }

    form.remove_logo = false;
    form.remove_favicon = false;

    form.put('/admin/settings', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('logo', 'remove_logo', 'favicon', 'remove_favicon');
            selectedLogo.value = null;
            selectedFavicon.value = null;
            clientLogoError.value = '';
            clientFaviconError.value = '';
            logoInputKey.value += 1;
            faviconInputKey.value += 1;
        },
    });
}

function removeSavedLogo(): void {
    if (!hasStoredLogo.value) {
        return;
    }

    if (!window.confirm('Remove current logo?')) {
        return;
    }

    clientLogoError.value = '';
    selectedLogo.value = null;
    form.logo = null;
    form.remove_logo = true;

    form.put('/admin/settings', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            storedLogoUrl.value = null;
            form.reset('logo', 'remove_logo');
            logoInputKey.value += 1;
        },
    });
}

function removeSavedFavicon(): void {
    if (!hasStoredFavicon.value) {
        return;
    }

    if (!window.confirm('Remove current favicon?')) {
        return;
    }

    clientFaviconError.value = '';
    selectedFavicon.value = null;
    form.favicon = null;
    form.remove_favicon = true;

    form.put('/admin/settings', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            storedFaviconUrl.value = null;
            form.reset('favicon', 'remove_favicon');
            faviconInputKey.value += 1;
        },
    });
}
</script>

<template>
    <Head title="Site Settings" />

    <EpAdminLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-6xl space-y-5 p-4 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="space-y-1">
                    <h1 class="text-2xl font-semibold tracking-tight">Site Settings</h1>
                    <p class="text-sm text-muted-foreground">
                        Manage brand identity and footer metadata used across the public ePaper views.
                    </p>
                </div>
                <Button :disabled="form.processing || clientLogoError !== '' || clientFaviconError !== ''" @click="submit">
                    {{ form.processing ? 'Saving...' : 'Save Settings' }}
                </Button>
            </div>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
                <Card class="border-border/70">
                    <CardHeader class="pb-2">
                        <CardTitle>Branding</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Site Name</label>
                            <Input
                                v-model="form.site_name"
                                placeholder="e.g. The Daily Newspaper"
                            />
                            <InputError :message="form.errors.site_name" />
                        </div>

                        <div class="space-y-3">
                            <p class="text-sm font-medium">Logo</p>
                            <input
                                :key="logoInputKey"
                                ref="logoInputRef"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,.svg,image/jpeg,image/png,image/webp,image/svg+xml"
                                class="sr-only"
                                @change="onLogoSelected"
                            />

                            <div
                                role="button"
                                tabindex="0"
                                class="rounded-xl border-2 border-dashed p-5 text-center transition-colors"
                                :class="isDropzoneActive
                                    ? 'border-primary bg-primary/5'
                                    : 'border-slate-300 bg-slate-50/80 hover:border-primary/70 hover:bg-primary/5'"
                                @click="openLogoPicker"
                                @keydown.enter.prevent="openLogoPicker"
                                @keydown.space.prevent="openLogoPicker"
                                @dragenter.prevent="onDropzoneDragEnter"
                                @dragover.prevent="onDropzoneDragOver"
                                @dragleave.prevent="onDropzoneDragLeave"
                                @drop.prevent="onDropzoneDrop"
                            >
                                <p class="text-sm font-medium">Drop logo here or click to browse</p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Supported: SVG, JPG, PNG, WEBP (max 5MB)
                                </p>
                                <p v-if="selectedLogo" class="mt-2 text-xs text-slate-700">
                                    Selected: {{ selectedLogo.name }}
                                </p>
                            </div>

                            <InputError :message="logoErrorMessage" />

                            <div v-if="logoPreviewUrl" class="space-y-2">
                                <p class="text-sm font-medium">Logo Preview</p>
                                <div class="rounded-lg border border-border/70 bg-muted/40 p-3">
                                    <img :src="logoPreviewUrl" alt="Site logo" class="max-h-24 w-auto object-contain" />
                                </div>
                            </div>

                            <div class="flex flex-wrap justify-end gap-2">
                                <Button v-if="selectedLogo !== null" variant="ghost" size="sm" @click="clearSelectedLogo">
                                    Remove selected file
                                </Button>
                                <Button
                                    v-if="hasStoredLogo"
                                    variant="destructive"
                                    size="sm"
                                    :disabled="form.processing"
                                    @click="removeSavedLogo"
                                >
                                    Remove logo
                                </Button>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <p class="text-sm font-medium">Favicon</p>
                            <input
                                :key="faviconInputKey"
                                ref="faviconInputRef"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,.svg,.ico,image/jpeg,image/png,image/webp,image/svg+xml,image/x-icon"
                                class="sr-only"
                                @change="onFaviconSelected"
                            />

                            <div
                                role="button"
                                tabindex="0"
                                class="rounded-xl border-2 border-dashed p-5 text-center transition-colors"
                                :class="isFaviconDropzoneActive
                                    ? 'border-primary bg-primary/5'
                                    : 'border-slate-300 bg-slate-50/80 hover:border-primary/70 hover:bg-primary/5'"
                                @click="openFaviconPicker"
                                @keydown.enter.prevent="openFaviconPicker"
                                @keydown.space.prevent="openFaviconPicker"
                                @dragenter.prevent="onFaviconDropzoneDragEnter"
                                @dragover.prevent="onFaviconDropzoneDragOver"
                                @dragleave.prevent="onFaviconDropzoneDragLeave"
                                @drop.prevent="onFaviconDropzoneDrop"
                            >
                                <p class="text-sm font-medium">Drop favicon here or click to browse</p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Supported: ICO, SVG, JPG, PNG, WEBP (max 2MB)
                                </p>
                                <p v-if="selectedFavicon" class="mt-2 text-xs text-slate-700">
                                    Selected: {{ selectedFavicon.name }}
                                </p>
                            </div>

                            <InputError :message="faviconErrorMessage" />

                            <div v-if="faviconPreviewUrl" class="space-y-2">
                                <p class="text-sm font-medium">Favicon Preview</p>
                                <div class="rounded-lg border border-border/70 bg-muted/40 p-3">
                                    <img :src="faviconPreviewUrl" alt="Site favicon" class="h-8 w-8 object-contain" />
                                </div>
                            </div>

                            <div class="flex flex-wrap justify-end gap-2">
                                <Button v-if="selectedFavicon !== null" variant="ghost" size="sm" @click="clearSelectedFavicon">
                                    Remove selected file
                                </Button>
                                <Button
                                    v-if="hasStoredFavicon"
                                    variant="destructive"
                                    size="sm"
                                    :disabled="form.processing"
                                    @click="removeSavedFavicon"
                                >
                                    Remove favicon
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border-border/70">
                    <CardHeader class="pb-2">
                        <CardTitle>Footer Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Editor Info</label>
                            <TipTapEditor
                                v-model="form.footer_editor_info"
                                placeholder="Editorial information shown in the public footer"
                            />
                            <InputError :message="form.errors.footer_editor_info" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Contact Info</label>
                            <TipTapEditor
                                v-model="form.footer_contact_info"
                                placeholder="Contact addresses, phone, email, website"
                            />
                            <InputError :message="form.errors.footer_contact_info" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Copyright</label>
                            <TipTapEditor
                                v-model="form.footer_copyright"
                                placeholder="e.g. Copyright The Daily Newspaper ©2026"
                            />
                            <InputError :message="form.errors.footer_copyright" />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </EpAdminLayout>
</template>
