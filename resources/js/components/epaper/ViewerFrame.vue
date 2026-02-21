<script setup lang="ts">
import Panzoom, { type PanzoomObject } from '@panzoom/panzoom';
import { ChevronLeft, ChevronRight, Minus, Plus, RotateCcw } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import type { Hotspot, Page } from '@/types';

const props = withDefaults(defineProps<{
    page: Page;
    totalPages: number;
    prevPageNo: number | null;
    nextPageNo: number | null;
    enablePanzoom?: boolean;
}>(), {
    enablePanzoom: true,
});

const emit = defineEmits<{
    previous: [];
    next: [];
    hotspotClick: [payload: { hotspot: Hotspot; image: HTMLImageElement | null }];
}>();

const viewportRef = ref<HTMLDivElement | null>(null);
const stageRef = ref<HTMLDivElement | null>(null);
const imageRef = ref<HTMLImageElement | null>(null);
const panzoom = ref<PanzoomObject | null>(null);

function onHotspotClick(hotspot: Hotspot): void {
    emit('hotspotClick', {
        hotspot,
        image: imageRef.value,
    });
}

function imageUrlForPage(page: Page): string {
    return page.image_large_url || page.image_original_url;
}

function resetZoom(): void {
    panzoom.value?.reset();
}

function zoomIn(): void {
    panzoom.value?.zoomIn();
}

function zoomOut(): void {
    panzoom.value?.zoomOut();
}

onMounted(() => {
    if (!props.enablePanzoom || stageRef.value === null) {
        return;
    }

    panzoom.value = Panzoom(stageRef.value, {
        maxScale: 6,
        minScale: 1,
        contain: 'outside',
    });
});

onBeforeUnmount(() => {
    panzoom.value?.destroy();
});
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-slate-300 bg-white shadow-sm">
        <div ref="viewportRef" class="flex h-[calc(100vh-250px)] min-h-[320px] items-center justify-center overflow-auto bg-[#f9fafc] p-2 sm:p-3">
            <div ref="stageRef" class="relative mx-auto inline-block max-h-full max-w-full touch-none select-none">
                <img
                    ref="imageRef"
                    :src="imageUrlForPage(page)"
                    :alt="`Page ${page.page_no}`"
                    draggable="false"
                    class="block h-auto w-auto max-h-full max-w-full object-contain"
                />

                <button
                    v-for="hotspot in page.hotspots"
                    :key="hotspot.id"
                    type="button"
                    class="hotspot-overlay absolute z-20 rounded-[2px] border border-transparent bg-transparent transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
                    :style="{
                        left: `${hotspot.x * 100}%`,
                        top: `${hotspot.y * 100}%`,
                        width: `${hotspot.w * 100}%`,
                        height: `${hotspot.h * 100}%`,
                    }"
                    :aria-label="`Hotspot to page ${hotspot.target_page_no}`"
                    @click="onHotspotClick(hotspot)"
                />
            </div>
        </div>

        <div class="grid grid-cols-1 gap-2 border-t border-slate-300 bg-slate-50 px-3 py-2 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
            <p class="order-1 text-center text-sm font-semibold text-slate-700 sm:order-2">
                Page {{ page.page_no }} / {{ totalPages }}
            </p>

            <div class="order-2 flex items-center justify-center gap-2 sm:order-1 sm:justify-start">
                <Button
                    variant="outline"
                    size="sm"
                    class="flex-1 sm:flex-none"
                    :disabled="prevPageNo === null"
                    @click="emit('previous')"
                >
                    <ChevronLeft class="mr-1 size-4" />
                    Prev
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="flex-1 sm:flex-none"
                    :disabled="nextPageNo === null"
                    @click="emit('next')"
                >
                    Next
                    <ChevronRight class="ml-1 size-4" />
                </Button>
            </div>

            <div class="order-3 flex items-center justify-center gap-2 sm:justify-end">
                <Button variant="outline" size="sm" title="Zoom out" @click="zoomOut">
                    <Minus class="size-4" />
                </Button>
                <Button variant="outline" size="sm" title="Zoom in" @click="zoomIn">
                    <Plus class="size-4" />
                </Button>
                <Button variant="outline" size="sm" @click="resetZoom">
                    <RotateCcw class="mr-1 size-4" />
                    Reset
                </Button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.hotspot-overlay:focus-visible {
    border-color: rgb(14 165 233 / 0.85);
    background-color: rgb(14 165 233 / 0.22);
}

@media (hover: hover) and (pointer: fine) {
    .hotspot-overlay:hover {
        cursor: pointer;
        border-color: rgb(14 165 233 / 0.85);
        background-color: rgb(14 165 233 / 0.22);
    }
}
</style>
