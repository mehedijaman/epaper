<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import EpAdminLayout from '@/layouts/EpAdminLayout.vue';
import type { BreadcrumbItem } from '@/types';

type SlotAd = {
    id: number | null;
    type: 'image' | 'embed';
    image_url: string;
    image_path: string;
    click_url: string;
    embed_code: string;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
};

type SlotItem = {
    id: number;
    slot_no: number;
    title: string | null;
    ad: SlotAd;
};

type SlotFormState = {
    slot_no: number;
    title: string | null;
    slot_title: string;
    type: 'image' | 'embed';
    image_url: string;
    image_path: string;
    image_source: 'url' | 'upload';
    selected_image_file: File | null;
    temp_image_url: string | null;
    image_file_error: string;
    remove_image_file: boolean;
    click_url: string;
    embed_code: string;
    is_active: boolean;
    starts_at: string;
    ends_at: string;
};

const props = defineProps<{
    slots: SlotItem[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Ads', href: '/admin/ads' },
];

const PLACE_OPTIONS = [
    { value: 'Top Banner', label: 'Top Banner' },
    { value: 'Footer Banner', label: 'Footer Banner' },
    { value: 'Sidebar Left', label: 'Sidebar Left' },
    { value: 'Sidebar Right', label: 'Sidebar Right' },
];

const PLACE_OPTION_VALUES = new Set(PLACE_OPTIONS.map((o) => o.value));

const savingSlotNo = ref<number | null>(null);
const errorSlotNo = ref<number | null>(null);
const slotForms = ref<SlotFormState[]>(props.slots.map(mapSlotToState));

const form = useForm<{
    type: 'image' | 'embed';
    image_source: 'url' | 'upload' | null;
    image_file: File | null;
    remove_image_file: boolean;
    image_url: string | null;
    click_url: string | null;
    embed_code: string | null;
    slot_title: string | null;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
}>({
    type: 'image',
    image_source: null,
    image_file: null,
    remove_image_file: false,
    image_url: null,
    click_url: null,
    embed_code: null,
    slot_title: null,
    is_active: true,
    starts_at: null,
    ends_at: null,
});

watch(
    () => props.slots,
    (value) => {
        slotForms.value = value.map(mapSlotToState);
    },
    { deep: true },
);

function mapSlotToState(slot: SlotItem): SlotFormState {
    const hasSavedFile = slot.ad.image_path !== null && slot.ad.image_path !== '';

    return {
        slot_no: slot.slot_no,
        title: slot.title,
        slot_title: PLACE_OPTION_VALUES.has(slot.title ?? '') ? (slot.title ?? '') : '',
        type: slot.ad.type,
        image_url: slot.ad.image_url ?? '',
        image_path: slot.ad.image_path ?? '',
        image_source: hasSavedFile ? 'upload' : 'url',
        selected_image_file: null,
        temp_image_url: null,
        image_file_error: '',
        remove_image_file: false,
        click_url: slot.ad.click_url ?? '',
        embed_code: slot.ad.embed_code ?? '',
        is_active: slot.ad.is_active,
        starts_at: toDateTimeLocal(slot.ad.starts_at),
        ends_at: toDateTimeLocal(slot.ad.ends_at),
    };
}

function imagePreviewUrl(slot: SlotFormState): string | null {
    if (slot.temp_image_url !== null) {
        return slot.temp_image_url;
    }

    if (slot.image_source === 'upload') {
        // Show the stored image URL from server when no new file is selected
        return slot.image_url.trim() !== '' ? slot.image_url : null;
    }

    // URL mode
    return slot.image_url.trim() !== '' ? slot.image_url : null;
}

const acceptedImageMimeTypes = new Set<string>([
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
]);

const acceptedImageExtensions = ['.jpg', '.jpeg', '.png', '.webp', '.gif'];
const maxAdImageSizeInBytes = 5 * 1024 * 1024;

function validateAdImageFile(file: File): string | null {
    const fileName = file.name.toLowerCase();
    const hasAllowedExtension = acceptedImageExtensions.some((ext) => fileName.endsWith(ext));
    const hasAllowedMimeType = file.type !== '' && acceptedImageMimeTypes.has(file.type);

    if (!hasAllowedExtension && !hasAllowedMimeType) {
        return 'Only JPG, PNG, WEBP, or GIF files are allowed.';
    }

    if (file.size > maxAdImageSizeInBytes) {
        return 'Image size must be 5MB or less.';
    }

    return null;
}

function applyAdImageFile(slot: SlotFormState, file: File | null): void {
    slot.image_file_error = '';
    slot.remove_image_file = false;

    if (file === null) {
        if (slot.temp_image_url !== null) {
            URL.revokeObjectURL(slot.temp_image_url);
            slot.temp_image_url = null;
        }

        slot.selected_image_file = null;
        return;
    }

    const error = validateAdImageFile(file);

    if (error !== null) {
        slot.image_file_error = error;
        slot.selected_image_file = null;
        return;
    }

    if (slot.temp_image_url !== null) {
        URL.revokeObjectURL(slot.temp_image_url);
    }

    slot.selected_image_file = file;
    slot.temp_image_url = URL.createObjectURL(file);
}

function onAdImageSelected(slot: SlotFormState, event: Event): void {
    const input = event.target as HTMLInputElement | null;
    const file = input?.files?.[0] ?? null;
    applyAdImageFile(slot, file);
}

function onAdImageDrop(slot: SlotFormState, event: DragEvent): void {
    const file = event.dataTransfer?.files?.[0] ?? null;
    applyAdImageFile(slot, file);
}

function clearAdImageFile(slot: SlotFormState): void {
    if (slot.temp_image_url !== null) {
        URL.revokeObjectURL(slot.temp_image_url);
        slot.temp_image_url = null;
    }

    slot.selected_image_file = null;
    slot.image_file_error = '';
    // Note: intentionally does NOT reset remove_image_file
}

function removeStoredAdImage(slot: SlotFormState): void {
    if (!window.confirm('Remove current ad image?')) {
        return;
    }

    if (slot.temp_image_url !== null) {
        URL.revokeObjectURL(slot.temp_image_url);
        slot.temp_image_url = null;
    }

    slot.selected_image_file = null;
    slot.image_file_error = '';
    slot.image_url = '';
    slot.image_path = '';
    slot.remove_image_file = true;
}

const slotFileInputKeys = ref<Record<number, number>>({});

function getFileInputKey(slotNo: number): number {
    return slotFileInputKeys.value[slotNo] ?? 0;
}

function bumpFileInputKey(slotNo: number): void {
    slotFileInputKeys.value[slotNo] = (slotFileInputKeys.value[slotNo] ?? 0) + 1;
}

function openAdImagePicker(event: MouseEvent): void {
    const input = (event.currentTarget as HTMLElement)?.previousElementSibling as HTMLInputElement | null;
    input?.click();
}

function toDateTimeLocal(value: string | null): string {
    if (value === null || value === '') {
        return '';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return '';
    }

    const timezoneOffset = parsed.getTimezoneOffset() * 60000;
    const localDate = new Date(parsed.getTime() - timezoneOffset);

    return localDate.toISOString().slice(0, 16);
}

function toUtcIso(value: string | null): string | null {
    if (value === null || value.trim() === '') {
        return null;
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    return parsed.toISOString();
}

function normalizeNullable(value: string): string | null {
    const normalized = value.trim();

    return normalized === '' ? null : normalized;
}

function saveSlot(slot: SlotFormState): void {
    if (slot.image_file_error !== '') {
        return;
    }

    savingSlotNo.value = slot.slot_no;
    errorSlotNo.value = slot.slot_no;
    form.clearErrors();

    form.type = slot.type;
    form.slot_title = normalizeNullable(slot.slot_title);
    form.image_source = slot.type === 'image' ? slot.image_source : null;
    form.image_file = slot.selected_image_file ?? null;
    form.remove_image_file = slot.remove_image_file;
    form.image_url = slot.type === 'image' && slot.image_source === 'url' ? normalizeNullable(slot.image_url) : null;
    form.click_url = slot.type === 'image' ? normalizeNullable(slot.click_url) : null;
    form.embed_code = slot.type === 'embed' ? normalizeNullable(slot.embed_code) : null;
    form.is_active = slot.is_active;
    form.starts_at = toUtcIso(slot.starts_at);
    form.ends_at = toUtcIso(slot.ends_at);

    form.put(`/admin/ads/${slot.slot_no}`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            savingSlotNo.value = null;
            errorSlotNo.value = null;
            slot.selected_image_file = null;
            slot.remove_image_file = false;
            if (slot.temp_image_url !== null) {
                URL.revokeObjectURL(slot.temp_image_url);
                slot.temp_image_url = null;
            }
            bumpFileInputKey(slot.slot_no);
        },
        onError: () => {
            savingSlotNo.value = null;
        },
        onFinish: () => {
            if (savingSlotNo.value === slot.slot_no) {
                savingSlotNo.value = null;
            }
        },
    });
}

function fieldError(field: keyof typeof form.errors, slotNo: number): string | undefined {
    if (errorSlotNo.value !== slotNo) {
        return undefined;
    }

    return form.errors[field];
}

function isSavingSlot(slotNo: number): boolean {
    return form.processing && savingSlotNo.value === slotNo;
}
</script>

<template>
    <Head title="Ads" />

    <EpAdminLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-7xl space-y-5 p-4 sm:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div class="space-y-1">
                    <h1 class="text-2xl font-semibold tracking-tight">Ads Management</h1>
                    <p class="text-sm text-muted-foreground">
                        Configure ad content, status, and schedule for each slot.
                    </p>
                </div>
                <p class="text-xs text-muted-foreground">
                    Each slot saves independently.
                </p>
            </div>

            <Card class="border-border/70">
                <CardContent class="grid gap-2 pt-6 text-sm text-muted-foreground sm:grid-cols-3">
                    <p>Total slots: {{ slotForms.length }}</p>
                    <p>Supported content: image or embed code</p>
                    <p>Use active window to schedule visibility</p>
                </CardContent>
            </Card>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Card v-for="slot in slotForms" :key="slot.slot_no" class="border-border/70 shadow-sm">
                    <CardHeader class="pb-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <CardTitle class="text-base">Slot {{ slot.slot_no }}</CardTitle>
                                <p class="text-xs text-muted-foreground">
                                    {{ slot.title ?? `Slot ${slot.slot_no}` }}
                                </p>
                            </div>
                            <Badge :variant="slot.is_active ? 'default' : 'secondary'">
                                {{ slot.is_active ? 'Active' : 'Inactive' }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Place / Position</label>
                            <Select v-model="slot.slot_title">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select place" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="opt in PLACE_OPTIONS" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Type</label>
                            <Select v-model="slot.type">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="image">Image</SelectItem>
                                    <SelectItem value="embed">Embed</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="fieldError('type', slot.slot_no)" />
                        </div>

                        <div v-if="slot.type === 'image'" class="space-y-3">
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <label class="text-sm font-medium">Ad Image</label>
                                    <div class="flex rounded-md border border-input text-xs overflow-hidden">
                                        <button
                                            type="button"
                                            class="px-2 py-1 transition-colors"
                                            :class="slot.image_source === 'url' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                                            @click="slot.image_source = 'url'; slot.image_url = slot.image_path !== '' ? '' : slot.image_url; clearAdImageFile(slot); slot.remove_image_file = false"
                                        >
                                            URL
                                        </button>
                                        <button
                                            type="button"
                                            class="px-2 py-1 transition-colors"
                                            :class="slot.image_source === 'upload' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                                            @click="slot.image_source = 'upload'"
                                        >
                                            Upload
                                        </button>
                                    </div>
                                </div>

                                <div v-if="slot.image_source === 'url'">
                                    <Input v-model="slot.image_url" placeholder="https://example.com/banner.jpg" />
                                    <InputError :message="fieldError('image_url', slot.slot_no)" />
                                </div>

                                <div v-else class="space-y-2">
                                    <input
                                        :key="getFileInputKey(slot.slot_no)"
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif"
                                        class="sr-only"
                                        @change="onAdImageSelected(slot, $event)"
                                    />
                                    <div
                                        role="button"
                                        tabindex="0"
                                        class="rounded-xl border-2 border-dashed p-4 text-center transition-colors cursor-pointer border-slate-300 bg-slate-50/80 hover:border-primary/70 hover:bg-primary/5"
                                        @click="openAdImagePicker($event)"
                                        @keydown.enter.prevent="openAdImagePicker($event)"
                                        @dragover.prevent
                                        @drop.prevent="onAdImageDrop(slot, $event)"
                                    >
                                        <p class="text-sm font-medium">Drop image or click to browse</p>
                                        <p class="mt-0.5 text-xs text-muted-foreground">JPG, PNG, WEBP, GIF (max 5MB)</p>
                                        <p v-if="slot.selected_image_file" class="mt-1 text-xs text-slate-700">
                                            {{ slot.selected_image_file.name }}
                                        </p>
                                    </div>
                                    <InputError :message="slot.image_file_error || fieldError('image_file', slot.slot_no)" />

                                    <div v-if="slot.image_path !== '' && slot.selected_image_file === null && !slot.remove_image_file" class="flex items-center justify-between">
                                        <span class="text-xs text-muted-foreground">Current image saved</span>
                                        <button type="button" class="text-xs text-destructive hover:underline" @click="removeStoredAdImage(slot)">
                                            Remove
                                        </button>
                                    </div>
                                    <div v-if="slot.selected_image_file !== null" class="flex justify-end">
                                        <button type="button" class="text-xs text-muted-foreground hover:underline" @click="clearAdImageFile(slot); bumpFileInputKey(slot.slot_no)">
                                            Clear selection
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="text-sm font-medium">Click URL (optional)</label>
                                <Input v-model="slot.click_url" placeholder="https://example.com/landing-page" />
                                <InputError :message="fieldError('click_url', slot.slot_no)" />
                            </div>

                            <div v-if="imagePreviewUrl(slot) !== null" class="space-y-2">
                                <p class="text-xs font-medium text-muted-foreground">Preview</p>
                                <div class="overflow-hidden rounded-md border border-border/70 bg-muted/30 p-2">
                                    <img
                                        :src="imagePreviewUrl(slot)!"
                                        alt="Ad preview"
                                        class="max-h-28 w-auto rounded object-contain"
                                        loading="lazy"
                                    />
                                </div>
                            </div>
                        </div>

                        <div v-else class="space-y-2">
                            <label class="text-sm font-medium">Embed Code</label>
                            <textarea
                                v-model="slot.embed_code"
                                rows="4"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] focus-visible:outline-none"
                                placeholder="Paste embed HTML"
                            />
                            <InputError :message="fieldError('embed_code', slot.slot_no)" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium">Active window (optional)</label>
                            <div class="grid gap-2">
                                <Input v-model="slot.starts_at" type="datetime-local" />
                                <Input v-model="slot.ends_at" type="datetime-local" />
                            </div>
                            <InputError :message="fieldError('starts_at', slot.slot_no)" />
                            <InputError :message="fieldError('ends_at', slot.slot_no)" />
                        </div>

                        <div class="flex items-center justify-between rounded-md border border-border/70 bg-muted/20 px-3 py-2">
                            <div>
                                <p class="text-sm font-medium">Slot status</p>
                                <p class="text-xs text-muted-foreground">Toggle visibility for this slot.</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <Switch v-model="slot.is_active" />
                                <span class="text-sm">{{ slot.is_active ? 'Active' : 'Inactive' }}</span>
                            </div>
                        </div>
                        <InputError :message="fieldError('is_active', slot.slot_no)" />

                        <Button
                            class="w-full"
                            :disabled="isSavingSlot(slot.slot_no)"
                            @click="saveSlot(slot)"
                        >
                            {{ isSavingSlot(slot.slot_no) ? 'Saving...' : `Save Slot ${slot.slot_no}` }}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </EpAdminLayout>
</template>
