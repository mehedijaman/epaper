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
import EpAdminLayout from '@/layouts/EpAdminLayout.vue';
import type { BreadcrumbItem } from '@/types';

type SiteSettings = {
    logo_path: string;
    footer_editor_info: string;
    footer_contact_info: string;
    footer_copyright: string;
};

const props = defineProps<{
    settings: SiteSettings;
    logo_url: string | null;
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

const acceptedLogoMimeTypes = new Set<string>([
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/svg+xml',
]);

const acceptedLogoExtensions = ['.jpg', '.jpeg', '.png', '.webp', '.svg'];
const maxLogoSizeInBytes = 5 * 1024 * 1024;

const form = useForm<{
    logo: File | null;
    remove_logo: boolean;
    footer_editor_info: string;
    footer_contact_info: string;
    footer_copyright: string;
}>({
    logo: null,
    remove_logo: false,
    footer_editor_info: props.settings.footer_editor_info,
    footer_contact_info: props.settings.footer_contact_info,
    footer_copyright: props.settings.footer_copyright,
});

const logoPreviewUrl = computed(() => tempLogoUrl.value ?? storedLogoUrl.value);
const logoErrorMessage = computed(() => clientLogoError.value !== '' ? clientLogoError.value : form.errors.logo);
const hasStoredLogo = computed(() => storedLogoUrl.value !== null && storedLogoUrl.value !== '');

watch(
    () => props.logo_url,
    (value) => {
        storedLogoUrl.value = value;
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

onBeforeUnmount(() => {
    if (tempLogoUrl.value !== null) {
        URL.revokeObjectURL(tempLogoUrl.value);
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

function submit(): void {
    if (clientLogoError.value !== '') {
        return;
    }

    form.remove_logo = false;

    form.put('/admin/settings', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('logo', 'remove_logo');
            selectedLogo.value = null;
            clientLogoError.value = '';
            logoInputKey.value += 1;
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
                <Button :disabled="form.processing || clientLogoError !== ''" @click="submit">
                    {{ form.processing ? 'Saving...' : 'Save Settings' }}
                </Button>
            </div>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
                <Card class="border-border/70">
                    <CardHeader class="pb-2">
                        <CardTitle>Branding</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
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
                    </CardContent>
                </Card>

                <Card class="border-border/70">
                    <CardHeader class="pb-2">
                        <CardTitle>Footer Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Editor Info</label>
                            <textarea
                                v-model="form.footer_editor_info"
                                rows="4"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none"
                                placeholder="Editorial information shown in the public footer"
                            />
                            <InputError :message="form.errors.footer_editor_info" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Contact Info</label>
                            <textarea
                                v-model="form.footer_contact_info"
                                rows="4"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none"
                                placeholder="Contact addresses, phone, email, website"
                            />
                            <InputError :message="form.errors.footer_contact_info" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Copyright</label>
                            <Input
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
