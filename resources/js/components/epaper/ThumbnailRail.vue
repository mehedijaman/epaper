<script setup lang="ts">
import { computed, type CSSProperties } from 'vue';
import type { ViewerPageListItem } from '@/types';

const props = withDefaults(defineProps<{
    pages: ViewerPageListItem[];
    activePageNo: number;
    mode?: 'strip' | 'grid';
    railHeight?: number | null;
}>(), {
    mode: 'strip',
    railHeight: null,
});

const emit = defineEmits<{
    select: [pageNo: number];
}>();

const gridClass = computed(() => {
    if (props.mode === 'grid') {
        return 'grid grid-cols-2 gap-2';
    }

    return 'flex flex-col gap-2';
});

const railStyle = computed<CSSProperties>(() => {
    if (props.railHeight === null || props.railHeight <= 0) {
        return {};
    }

    return {
        height: `${props.railHeight}px`,
    };
});

function onSelect(pageNo: number): void {
    emit('select', pageNo);
}
</script>

<template>
    <aside class="h-full min-h-0 overflow-y-auto border-r border-slate-300 bg-[#edf0f7] p-2" :style="railStyle">
        <div :class="gridClass">
            <button
                v-for="item in pages"
                :key="item.id"
                type="button"
                class="w-full overflow-hidden rounded border border-slate-300 bg-white p-1 text-left transition hover:border-sky-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
                :class="item.page_no === activePageNo ? 'ring-2 ring-sky-500 border-sky-500' : ''"
                :title="`Go to page ${item.page_no}`"
                @click="onSelect(item.page_no)"
            >
                <img
                    :src="item.image_thumb_url"
                    :alt="`Page ${item.page_no}`"
                    loading="lazy"
                    class="h-auto w-full rounded"
                />
                <p class="mt-1 text-center text-[11px] font-semibold text-slate-700">
                    Page {{ item.page_no }}
                </p>
            </button>
        </div>
    </aside>
</template>
