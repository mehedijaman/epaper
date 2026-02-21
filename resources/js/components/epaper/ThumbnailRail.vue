<script setup lang="ts">
import { computed, type CSSProperties } from 'vue';
import type { ViewerPageListItem } from '@/types';

const props = withDefaults(
    defineProps<{
        pages: ViewerPageListItem[];
        activePageNo: number;
        mode?: 'strip' | 'grid';
        railHeight?: number | null;
    }>(),
    {
        mode: 'strip',
        railHeight: null,
    },
);

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
    <aside
        class="h-full min-h-0 overflow-y-auto border-r border-slate-200 bg-slate-50 p-2"
        :style="railStyle"
    >
        <div :class="gridClass">
            <button
                v-for="item in pages"
                :key="item.id"
                type="button"
                class="group w-full overflow-hidden rounded-lg border bg-white p-1 text-left transition-all hover:shadow-md focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:outline-none"
                :class="
                    item.page_no === activePageNo
                        ? 'border-sky-500 shadow-sm ring-1 ring-sky-500'
                        : 'border-slate-200 hover:border-slate-300'
                "
                :title="`Go to page ${item.page_no}`"
                @click="onSelect(item.page_no)"
            >
                <img
                    :src="item.image_thumb_url"
                    :alt="`Page ${item.page_no}`"
                    loading="lazy"
                    class="h-auto w-full rounded"
                />
                <p
                    class="mt-1 text-center text-[11px] font-medium"
                    :class="
                        item.page_no === activePageNo
                            ? 'text-sky-700'
                            : 'text-slate-500 group-hover:text-slate-700'
                    "
                >
                    {{ item.page_no }}
                </p>
            </button>
        </div>
    </aside>
</template>
