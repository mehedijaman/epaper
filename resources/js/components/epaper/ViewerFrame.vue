<script setup lang="ts">
import Panzoom, { type PanzoomObject } from '@panzoom/panzoom';
import {
    ChevronLeft,
    ChevronRight,
    Maximize2,
    Minus,
    Plus,
} from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import type { Hotspot, Page } from '@/types';

const props = withDefaults(
    defineProps<{
        page: Page;
        editionDate: string;
        selectedEditionId?: number | null;
        totalPages: number;
        prevPageNo: number | null;
        nextPageNo: number | null;
        enablePanzoom?: boolean;
    }>(),
    {
        selectedEditionId: null,
        enablePanzoom: true,
    },
);

const emit = defineEmits<{
    previous: [];
    next: [];
}>();

const viewportRef = ref<HTMLDivElement | null>(null);
const stageRef = ref<HTMLDivElement | null>(null);
const imageRef = ref<HTMLImageElement | null>(null);
const panzoom = ref<PanzoomObject | null>(null);

function imageUrlForPage(page: Page): string {
    return page.image_large_url || page.image_original_url;
}

function hotspotUrl(hotspot: Hotspot): string {
    const baseUrl = `/epaper/${props.editionDate}/page/${props.page.page_no}/hotspot/${hotspot.id}`;

    if (props.selectedEditionId === null || props.selectedEditionId <= 0) {
        return baseUrl;
    }

    return `${baseUrl}?edition=${props.selectedEditionId}`;
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
    <div
        class="flex flex-col rounded-xl border border-slate-200 bg-white shadow-md"
    >
        <div
            ref="viewportRef"
            class="flex items-start justify-center overflow-visible bg-slate-50 p-2 sm:p-3"
        >
            <div
                ref="stageRef"
                class="relative mx-auto inline-block w-full touch-none select-none"
            >
                <img
                    ref="imageRef"
                    :src="imageUrlForPage(page)"
                    :alt="`Page ${page.page_no}`"
                    draggable="false"
                    class="block h-auto w-full object-contain"
                />

                <a
                    v-for="hotspot in page.hotspots"
                    :key="hotspot.id"
                    :href="hotspotUrl(hotspot)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="hotspot-overlay absolute z-20 block rounded-sm border border-transparent bg-transparent transition-all duration-150 focus-visible:ring-2 focus-visible:ring-sky-400/80 focus-visible:outline-none"
                    :style="{
                        left: `${hotspot.x * 100}%`,
                        top: `${hotspot.y * 100}%`,
                        width: `${hotspot.w * 100}%`,
                        height: `${hotspot.h * 100}%`,
                    }"
                    :aria-label="`Hotspot to page ${hotspot.target_page_no}`"
                />
            </div>
        </div>

        <div
            class="flex shrink-0 items-center justify-between gap-2 border-t border-slate-200 bg-white px-3 py-2"
        >
            <div class="flex items-center gap-1.5">
                <Button
                    variant="ghost"
                    size="icon-sm"
                    :disabled="prevPageNo === null"
                    title="Previous page"
                    @click="emit('previous')"
                >
                    <ChevronLeft class="size-4" />
                </Button>
                <span
                    class="min-w-[4rem] text-center text-sm font-medium text-slate-700 tabular-nums"
                >
                    {{ page.page_no }} / {{ totalPages }}
                </span>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    :disabled="nextPageNo === null"
                    title="Next page"
                    @click="emit('next')"
                >
                    <ChevronRight class="size-4" />
                </Button>
            </div>

            <div class="flex items-center gap-1">
                <Button
                    variant="ghost"
                    size="icon-sm"
                    title="Zoom out"
                    @click="zoomOut"
                >
                    <Minus class="size-4" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    title="Zoom in"
                    @click="zoomIn"
                >
                    <Plus class="size-4" />
                </Button>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    title="Reset zoom"
                    @click="resetZoom"
                >
                    <Maximize2 class="size-4" />
                </Button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.hotspot-overlay:focus-visible {
    border-color: rgb(14 165 233 / 0.7);
    background-color: rgb(14 165 233 / 0.12);
}

@media (hover: hover) and (pointer: fine) {
    .hotspot-overlay:hover {
        cursor: pointer;
        border-color: rgb(14 165 233 / 0.6);
        background-color: rgb(14 165 233 / 0.1);
    }
}
</style>
