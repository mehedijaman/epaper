<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import EpAdminLayout from '@/layouts/EpAdminLayout.vue';
import type { BreadcrumbItem } from '@/types';

type SlotAd = {
    id: number | null;
    type: 'image' | 'embed';
    image_url: string;
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
    type: 'image' | 'embed';
    image_url: string;
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

const savingSlotNo = ref<number | null>(null);
const errorSlotNo = ref<number | null>(null);
const slotForms = ref<SlotFormState[]>(props.slots.map(mapSlotToState));

const form = useForm<{
    type: 'image' | 'embed';
    image_url: string | null;
    click_url: string | null;
    embed_code: string | null;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
}>({
    type: 'image',
    image_url: null,
    click_url: null,
    embed_code: null,
    is_active: true,
    starts_at: null,
    ends_at: null,
});

watch(
    () => props.slots,
    (value) => {
        slotForms.value = value.map(mapSlotToState);
    },
);

function mapSlotToState(slot: SlotItem): SlotFormState {
    return {
        slot_no: slot.slot_no,
        title: slot.title,
        type: slot.ad.type,
        image_url: slot.ad.image_url ?? '',
        click_url: slot.ad.click_url ?? '',
        embed_code: slot.ad.embed_code ?? '',
        is_active: slot.ad.is_active,
        starts_at: toDateTimeLocal(slot.ad.starts_at),
        ends_at: toDateTimeLocal(slot.ad.ends_at),
    };
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

function normalizeNullable(value: string): string | null {
    const normalized = value.trim();

    return normalized === '' ? null : normalized;
}

function saveSlot(slot: SlotFormState): void {
    savingSlotNo.value = slot.slot_no;
    errorSlotNo.value = slot.slot_no;
    form.clearErrors();

    form.type = slot.type;
    form.image_url = slot.type === 'image' ? normalizeNullable(slot.image_url) : null;
    form.click_url = slot.type === 'image' ? normalizeNullable(slot.click_url) : null;
    form.embed_code = slot.type === 'embed' ? normalizeNullable(slot.embed_code) : null;
    form.is_active = slot.is_active;
    form.starts_at = normalizeNullable(slot.starts_at);
    form.ends_at = normalizeNullable(slot.ends_at);

    form.put(`/admin/ads/${slot.slot_no}`, {
        preserveScroll: true,
        onSuccess: () => {
            savingSlotNo.value = null;
            errorSlotNo.value = null;
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

function updateIsActive(slot: SlotFormState, checked: boolean | 'indeterminate'): void {
    slot.is_active = checked === true;
}

function onActiveToggle(slot: SlotFormState, checked: boolean | 'indeterminate'): void {
    updateIsActive(slot, checked);
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
                            <CardTitle class="text-base">Slot {{ slot.slot_no }}</CardTitle>
                            <Badge :variant="slot.is_active ? 'default' : 'secondary'">
                                {{ slot.is_active ? 'Active' : 'Inactive' }}
                            </Badge>
                        </div>
                        <p v-if="slot.title" class="text-xs text-muted-foreground">
                            {{ slot.title }}
                        </p>
                    </CardHeader>
                    <CardContent class="space-y-4">
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
                                <label class="text-sm font-medium">Image URL</label>
                                <Input v-model="slot.image_url" placeholder="https://example.com/banner.jpg" />
                                <InputError :message="fieldError('image_url', slot.slot_no)" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium">Click URL (optional)</label>
                                <Input v-model="slot.click_url" placeholder="https://example.com/landing-page" />
                                <InputError :message="fieldError('click_url', slot.slot_no)" />
                            </div>

                            <div v-if="slot.image_url.trim() !== ''" class="space-y-2">
                                <p class="text-xs font-medium text-muted-foreground">Preview</p>
                                <div class="overflow-hidden rounded-md border border-border/70 bg-muted/30 p-2">
                                    <img
                                        :src="slot.image_url"
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
                                <Checkbox
                                    :checked="slot.is_active"
                                    @update:checked="onActiveToggle(slot, $event)"
                                />
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
