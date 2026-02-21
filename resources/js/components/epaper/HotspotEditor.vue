<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Page } from '@/types';

type Rect = {
    x: number;
    y: number;
    w: number;
    h: number;
};

const props = defineProps<{
    page: Page;
    pageNumbers: number[];
}>();

const drawingAreaRef = ref<HTMLDivElement | null>(null);
const drawing = ref(false);
const startPoint = reactive({ x: 0, y: 0 });
const draftRect = ref<Rect | null>(null);
const relationKind = ref<'next' | 'previous'>('next');
const targetPageNo = ref<number>(props.pageNumbers.find((no) => no !== props.page.page_no) ?? props.page.page_no);

const currentHotspots = computed(() => props.page.hotspots);

function clamp(value: number, min = 0, max = 1): number {
    return Math.min(Math.max(value, min), max);
}

function toNormalizedCoordinates(event: PointerEvent): { x: number; y: number } {
    const area = drawingAreaRef.value;

    if (!area) {
        return { x: 0, y: 0 };
    }

    const rect = area.getBoundingClientRect();
    const x = clamp((event.clientX - rect.left) / rect.width);
    const y = clamp((event.clientY - rect.top) / rect.height);

    return { x, y };
}

function onPointerDown(event: PointerEvent): void {
    if (event.button !== 0) {
        return;
    }

    const point = toNormalizedCoordinates(event);
    startPoint.x = point.x;
    startPoint.y = point.y;
    draftRect.value = { x: point.x, y: point.y, w: 0, h: 0 };
    drawing.value = true;
}

function onPointerMove(event: PointerEvent): void {
    if (!drawing.value || !draftRect.value) {
        return;
    }

    const point = toNormalizedCoordinates(event);
    const x = Math.min(startPoint.x, point.x);
    const y = Math.min(startPoint.y, point.y);
    const w = Math.abs(point.x - startPoint.x);
    const h = Math.abs(point.y - startPoint.y);

    draftRect.value = {
        x: clamp(x),
        y: clamp(y),
        w: clamp(w),
        h: clamp(h),
    };
}

function onPointerUp(): void {
    drawing.value = false;

    if (!draftRect.value) {
        return;
    }

    if (draftRect.value.w < 0.01 || draftRect.value.h < 0.01) {
        draftRect.value = null;
    }
}

function createHotspot(): void {
    if (!draftRect.value) {
        return;
    }

    router.post(
        `/admin/pages/${props.page.id}/hotspots`,
        {
            relation_kind: relationKind.value,
            target_page_no: targetPageNo.value,
            x: draftRect.value.x,
            y: draftRect.value.y,
            w: draftRect.value.w,
            h: draftRect.value.h,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                draftRect.value = null;
            },
        },
    );
}

function removeHotspot(hotspotId: number): void {
    router.delete(`/admin/hotspots/${hotspotId}`, {
        preserveScroll: true,
    });
}

</script>

<template>
    <div class="space-y-4">
        <div class="grid gap-3 rounded-lg border border-border/60 p-3 md:grid-cols-3">
            <div class="space-y-2">
                <label class="text-sm font-medium">Relation kind</label>
                <Select v-model="relationKind">
                    <SelectTrigger>
                        <SelectValue placeholder="Select relation" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="next">next</SelectItem>
                        <SelectItem value="previous">previous</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium">Target page no</label>
                <Select v-model="targetPageNo">
                    <SelectTrigger>
                        <SelectValue placeholder="Target page" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="pageNo in pageNumbers" :key="pageNo" :value="pageNo">
                            {{ pageNo }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="flex items-end">
                <Button class="w-full" :disabled="!draftRect" @click="createHotspot">Save Hotspot</Button>
            </div>
        </div>

        <div
            ref="drawingAreaRef"
            class="relative w-full overflow-hidden rounded-lg border border-border/60 bg-black/5"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointerleave="onPointerUp"
        >
            <img :src="page.image_large_url" :alt="`Page ${page.page_no}`" class="h-auto w-full select-none" draggable="false" />

            <button
                v-for="hotspot in currentHotspots"
                :key="hotspot.id"
                type="button"
                class="absolute border-2 border-emerald-500/90 bg-emerald-500/15"
                :style="{
                    left: `${hotspot.x * 100}%`,
                    top: `${hotspot.y * 100}%`,
                    width: `${hotspot.w * 100}%`,
                    height: `${hotspot.h * 100}%`,
                }"
                @dblclick.prevent="removeHotspot(hotspot.id)"
                :title="`Double click to delete (target: ${hotspot.target_page_no})`"
            />

            <div
                v-if="draftRect"
                class="absolute border-2 border-blue-500 bg-blue-500/15"
                :style="{
                    left: `${draftRect.x * 100}%`,
                    top: `${draftRect.y * 100}%`,
                    width: `${draftRect.w * 100}%`,
                    height: `${draftRect.h * 100}%`,
                }"
            />
        </div>

        <div class="rounded-lg border border-border/60 p-3">
            <p class="mb-3 text-sm font-medium">Existing hotspots</p>
            <div v-if="currentHotspots.length" class="space-y-3">
                <div v-for="hotspot in currentHotspots" :key="hotspot.id" class="flex items-center justify-between rounded border p-2 text-sm">
                    <div class="space-x-2">
                        <span class="font-medium">#{{ hotspot.id }}</span>
                        <span class="text-muted-foreground">{{ hotspot.relation_kind }} -> page {{ hotspot.target_page_no }}</span>
                    </div>
                    <Button size="sm" variant="destructive" @click="removeHotspot(hotspot.id)">Delete</Button>
                </div>
            </div>
            <p v-else class="text-sm text-muted-foreground">No hotspots added yet.</p>
        </div>

        <p class="text-xs text-muted-foreground">Draw a rectangle by click-dragging the page image. Double click a hotspot to remove it.</p>
    </div>
</template>
