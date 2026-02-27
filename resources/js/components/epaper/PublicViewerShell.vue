<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    CalendarDays,
    Grid2x2,
    List,
    Newspaper,
} from 'lucide-vue-next';
import type { AcceptableValue } from 'reka-ui';
import type { ComponentPublicInstance } from 'vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import ThumbnailRail from '@/components/epaper/ThumbnailRail.vue';
import ViewerFrame from '@/components/epaper/ViewerFrame.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    Ad,
    Page,
    ViewerCategoryItem,
    ViewerEditionItem,
    ViewerPageListItem,
} from '@/types';

type FooterSettings = {
    footer_editor_info: string;
    footer_contact_info: string;
    footer_copyright: string;
};

const props = withDefaults(
    defineProps<{
        title: string;
        editionDate: string | null;
        page: Page | null;
        pages: ViewerPageListItem[];
        categories?: ViewerCategoryItem[];
        editionsForDate?: ViewerEditionItem[];
        selectedEdition?: ViewerEditionItem | null;
        availableDates: string[];
        logoUrl?: string | null;
        settings: FooterSettings;
        adsBySlot?: Record<string, Ad[]>;
    }>(),
    {
        categories: () => [],
        editionsForDate: () => [],
        selectedEdition: null,
        logoUrl: null,
        adsBySlot: undefined,
    },
);

function dateInDhaka(reference: Date = new Date()): string {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Dhaka',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(reference);

    const year = parts.find((part) => part.type === 'year')?.value ?? '';
    const month = parts.find((part) => part.type === 'month')?.value ?? '';
    const day = parts.find((part) => part.type === 'day')?.value ?? '';

    return year !== '' && month !== '' && day !== ''
        ? `${year}-${month}-${day}`
        : '';
}

const selectedDate = ref(dateInDhaka());
const selectedPage = ref(props.page ? String(props.page.page_no) : '');
const selectedCategory = ref('');
const selectedEditionId = ref('');
const thumbnailMode = ref<'strip' | 'grid'>('strip');
const dateInputRef = ref<HTMLInputElement | ComponentPublicInstance | null>(
    null,
);
const viewerSectionRef = ref<HTMLElement | null>(null);
const thumbnailRailHeight = ref<number | null>(null);

const toastMessage = ref('');
let toastTimeoutId: number | null = null;
let viewerSectionObserver: ResizeObserver | null = null;

const hasPageData = computed(() => {
    return (
        props.editionDate !== null &&
        props.page !== null &&
        scopedPages.value.length > 0
    );
});

const currentPageNo = computed(() => props.page?.page_no ?? 0);

const scopedPages = computed<ViewerPageListItem[]>(() => {
    if (props.page === null) {
        return [...props.pages].sort((a, b) => a.page_no - b.page_no);
    }

    const filtered = props.pages.filter(
        (item) => item.edition_id === props.page?.edition_id,
    );

    if (filtered.length === 0) {
        return [
            {
                id: props.page.id,
                edition_id: props.page.edition_id,
                page_no: props.page.page_no,
                category_id: props.page.category_id,
                category_name: props.page.category_name,
                image_thumb_url: props.page.image_thumb_url,
                image_large_url: props.page.image_large_url,
                image_original_url: props.page.image_original_url,
                hotspots: props.page.hotspots,
            },
        ];
    }

    return filtered.sort((a, b) => a.page_no - b.page_no);
});

const pageByNumber = computed(() => {
    const map = new Map<number, ViewerPageListItem>();

    for (const item of scopedPages.value) {
        map.set(item.page_no, item);
    }

    return map;
});

const pageIndex = computed(() => {
    if (!hasPageData.value) {
        return -1;
    }

    return scopedPages.value.findIndex(
        (item) => item.page_no === currentPageNo.value,
    );
});

const prevPageNo = computed<number | null>(() => {
    if (pageIndex.value <= 0) {
        return null;
    }

    return scopedPages.value[pageIndex.value - 1]?.page_no ?? null;
});

const nextPageNo = computed<number | null>(() => {
    if (
        pageIndex.value < 0 ||
        pageIndex.value >= scopedPages.value.length - 1
    ) {
        return null;
    }

    return scopedPages.value[pageIndex.value + 1]?.page_no ?? null;
});

const categoryPageMap = computed(() => {
    const map = new Map<number, number>();

    for (const item of scopedPages.value) {
        if (item.category_id !== null && !map.has(item.category_id)) {
            map.set(item.category_id, item.page_no);
        }
    }

    return map;
});

const derivedCategories = computed<ViewerCategoryItem[]>(() => {
    const map = new Map<number, ViewerCategoryItem>();

    for (const item of scopedPages.value) {
        if (
            item.category_id === null ||
            item.category_name === null ||
            map.has(item.category_id)
        ) {
            continue;
        }

        map.set(item.category_id, {
            id: item.category_id,
            name: item.category_name,
            position: item.page_no,
        });
    }

    return Array.from(map.values()).sort((a, b) => a.position - b.position);
});

const categoryOptions = computed<ViewerCategoryItem[]>(() => {
    const source =
        props.categories.length > 0
            ? props.categories
            : derivedCategories.value;

    return [...source].sort((a, b) => a.position - b.position);
});

const hasCategories = computed(() => categoryOptions.value.length > 0);

const editionOptions = computed<ViewerEditionItem[]>(() => {
    if (props.editionsForDate.length > 0) {
        return props.editionsForDate;
    }

    if (props.selectedEdition !== null) {
        return [props.selectedEdition];
    }

    if (props.page !== null && props.editionDate !== null) {
        return [
            {
                id: props.page.edition_id,
                name: null,
                edition_date: props.editionDate,
            },
        ];
    }

    return [];
});

const selectedEditionIdNumber = computed<number | null>(() => {
    const parsed = Number.parseInt(selectedEditionId.value, 10);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
});

const frameEditionDate = computed(() => props.editionDate ?? selectedDate.value);

watch(
    () => props.editionDate,
    (value) => {
        selectedDate.value = value ?? dateInDhaka();
    },
);

watch(
    () => props.page?.page_no,
    (value) => {
        selectedPage.value = value !== undefined ? String(value) : '';
    },
);

watch(
    () => props.page?.category_id,
    (value) => {
        selectedCategory.value =
            value !== null && value !== undefined ? String(value) : '';
    },
    { immediate: true },
);

watch(
    () => [props.selectedEdition?.id, props.page?.edition_id] as const,
    ([selectedId, pageEditionId]) => {
        if (selectedId !== undefined && selectedId !== null) {
            selectedEditionId.value = String(selectedId);
            return;
        }

        selectedEditionId.value =
            pageEditionId !== undefined ? String(pageEditionId) : '';
    },
    { immediate: true },
);

watch(
    () => props.page?.id,
    () => {
        void nextTick(() => {
            refreshViewerSectionObserver();
            updateThumbnailRailHeight();
        });
    },
    { immediate: true },
);

watch(
    () => hasPageData.value,
    () => {
        void nextTick(() => {
            refreshViewerSectionObserver();
            updateThumbnailRailHeight();
        });
    },
);

function editionDisplayLabel(edition: ViewerEditionItem): string {
    return edition.name?.trim() || edition.edition_date;
}

function viewerUrl(date: string, pageNo: number, editionId?: number | null): string {
    const normalizedEditionId =
        editionId !== null && editionId !== undefined && editionId > 0
            ? editionId
            : null;

    if (normalizedEditionId === null) {
        return `/epaper/${date}/page/${pageNo}`;
    }

    return `/epaper/${date}/page/${pageNo}?edition=${normalizedEditionId}`;
}

function editionUrl(date: string): string {
    return `/epaper/${date}`;
}

function isIsoDate(value: string): boolean {
    return /^\d{4}-\d{2}-\d{2}$/.test(value);
}

function showToastError(message: string): void {
    toastMessage.value = message;

    if (toastTimeoutId !== null) {
        window.clearTimeout(toastTimeoutId);
    }

    toastTimeoutId = window.setTimeout(() => {
        toastMessage.value = '';
        toastTimeoutId = null;
    }, 4000);
}

function navigateToPage(pageNo: number): void {
    if (!hasPageData.value || props.editionDate === null) {
        return;
    }

    const target = pageByNumber.value.get(pageNo);

    if (target === undefined || target.page_no === currentPageNo.value) {
        return;
    }

    router.visit(
        viewerUrl(props.editionDate, target.page_no, selectedEditionIdNumber.value),
        {
            preserveScroll: true,
        },
    );
}

async function resolveDateNavigationUrl(date: string): Promise<string> {
    const preferredUrl = viewerUrl(date, 1);

    if (typeof window === 'undefined' || typeof window.fetch !== 'function') {
        return preferredUrl;
    }

    try {
        const response = await window.fetch(preferredUrl, {
            method: 'HEAD',
            credentials: 'same-origin',
        });

        if (response.ok) {
            return preferredUrl;
        }
    } catch {
        // Fall back to edition route below.
    }

    return editionUrl(date);
}

async function navigateToDate(date: string): Promise<void> {
    if (date === '') {
        return;
    }

    if (!isIsoDate(date)) {
        showToastError('Please select a valid date.');
        selectedDate.value = props.editionDate ?? dateInDhaka();
        return;
    }

    if (
        props.availableDates.length > 0 &&
        !props.availableDates.includes(date)
    ) {
        showToastError(`No published edition found for ${date}.`);
        selectedDate.value = props.editionDate ?? dateInDhaka();
        return;
    }

    const targetUrl = await resolveDateNavigationUrl(date);

    router.visit(targetUrl, {
        preserveScroll: true,
    });
}

function onPageSelect(rawValue: AcceptableValue): void {
    if (rawValue === null) {
        return;
    }

    const pageNo = Number.parseInt(String(rawValue), 10);

    if (!Number.isFinite(pageNo) || pageNo <= 0) {
        return;
    }

    navigateToPage(pageNo);
}

function onCategorySelect(rawValue: AcceptableValue): void {
    if (rawValue === null) {
        return;
    }

    selectedCategory.value = String(rawValue);

    const categoryId = Number.parseInt(String(rawValue), 10);

    if (!Number.isFinite(categoryId) || categoryId <= 0) {
        return;
    }

    const pageNo = categoryPageMap.value.get(categoryId);

    if (pageNo === undefined) {
        showToastError('No pages mapped for this category in this edition.');
        return;
    }

    navigateToPage(pageNo);
}

function onEditionSelect(rawValue: AcceptableValue): void {
    if (rawValue === null) {
        return;
    }

    const editionId = Number.parseInt(String(rawValue), 10);

    if (!Number.isFinite(editionId) || editionId <= 0) {
        return;
    }

    selectedEditionId.value = String(editionId);

    if (selectedDate.value === '' || !isIsoDate(selectedDate.value)) {
        showToastError('Please select a valid date.');
        return;
    }

    router.visit(viewerUrl(selectedDate.value, 1, editionId), {
        preserveScroll: true,
    });
}

function onDateChange(event: Event): void {
    const target = event.target as HTMLInputElement | null;

    if (target === null) {
        return;
    }

    selectedDate.value = target.value;
    void navigateToDate(target.value);
}

function resolveDateInputElement(): HTMLInputElement | null {
    const value = dateInputRef.value;

    if (value instanceof HTMLInputElement) {
        return value;
    }

    if (
        value !== null &&
        '$el' in value &&
        (value.$el instanceof HTMLElement || value.$el instanceof SVGElement)
    ) {
        const element = value.$el;

        if (element instanceof HTMLInputElement) {
            return element;
        }

        const nestedInput = element.querySelector('input[type="date"]');

        if (nestedInput instanceof HTMLInputElement) {
            return nestedInput;
        }
    }

    return null;
}

function openDatePicker(): void {
    const input = resolveDateInputElement();

    if (input === null) {
        return;
    }

    const picker = input as HTMLInputElement & { showPicker?: () => void };

    if (picker.showPicker !== undefined) {
        picker.showPicker();
        return;
    }

    input.focus();
}

function toggleThumbnailMode(): void {
    thumbnailMode.value = thumbnailMode.value === 'strip' ? 'grid' : 'strip';
}

function updateThumbnailRailHeight(): void {
    const section = viewerSectionRef.value;

    if (section === null) {
        thumbnailRailHeight.value = null;
        return;
    }

    thumbnailRailHeight.value = Math.max(
        0,
        Math.round(section.getBoundingClientRect().height),
    );
}

function refreshViewerSectionObserver(): void {
    viewerSectionObserver?.disconnect();
    viewerSectionObserver = null;

    if (
        typeof window === 'undefined' ||
        viewerSectionRef.value === null ||
        !('ResizeObserver' in window)
    ) {
        return;
    }

    viewerSectionObserver = new ResizeObserver(() => {
        updateThumbnailRailHeight();
    });
    viewerSectionObserver.observe(viewerSectionRef.value);
}

onMounted(() => {
    refreshViewerSectionObserver();
    updateThumbnailRailHeight();
});

onBeforeUnmount(() => {
    if (toastTimeoutId !== null) {
        window.clearTimeout(toastTimeoutId);
    }

    viewerSectionObserver?.disconnect();
    viewerSectionObserver = null;
});
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-screen flex-col bg-slate-100 text-slate-900">
        <header class="border-b border-slate-200 bg-white shadow-sm">
            <div
                class="mx-auto flex max-w-7xl items-center justify-center px-4 py-3"
            >
                <img
                    v-if="logoUrl"
                    :src="logoUrl"
                    alt="Newspaper logo"
                    class="h-10 w-auto object-contain sm:h-12"
                />
                <div v-else class="inline-flex items-center gap-2">
                    <Newspaper class="size-5 text-slate-700" />
                    <span
                        class="text-lg font-bold tracking-tight text-slate-900"
                        >ePaper</span
                    >
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl flex-1 px-2 py-3 sm:px-4">
            <div
                class="rounded-xl border border-slate-200 bg-white shadow-sm"
            >
                <div
                    class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 px-3 py-2.5 backdrop-blur-sm"
                >
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div
                            class="flex w-full flex-wrap items-center gap-2 sm:w-auto"
                        >
                            <div
                                class="min-w-[140px] flex-1 basis-[160px] sm:w-[170px] sm:flex-none"
                            >
                                <Select
                                    :model-value="selectedCategory"
                                    :disabled="!hasCategories"
                                    @update:model-value="onCategorySelect"
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="category in categoryOptions"
                                            :key="category.id"
                                            :value="String(category.id)"
                                        >
                                            {{ category.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div
                                class="min-w-[160px] flex-1 basis-[180px] sm:w-[220px] sm:flex-none"
                            >
                                <Select
                                    :model-value="selectedEditionId"
                                    :disabled="editionOptions.length === 0"
                                    @update:model-value="onEditionSelect"
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Edition Select" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="edition in editionOptions"
                                            :key="edition.id"
                                            :value="String(edition.id)"
                                        >
                                            {{ editionDisplayLabel(edition) }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div
                                class="min-w-[100px] flex-1 basis-[110px] sm:w-[110px] sm:flex-none"
                            >
                                <Select
                                    :model-value="selectedPage"
                                    :disabled="!hasPageData"
                                    @update:model-value="onPageSelect"
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Page" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="item in scopedPages"
                                            :key="item.id"
                                            :value="String(item.page_no)"
                                        >
                                            Page {{ item.page_no }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                class="shrink-0"
                                :title="
                                    thumbnailMode === 'strip'
                                        ? 'Switch to grid thumbnails'
                                        : 'Switch to strip thumbnails'
                                "
                                @click="toggleThumbnailMode"
                            >
                                <Grid2x2
                                    v-if="thumbnailMode === 'strip'"
                                    class="size-4"
                                />
                                <List v-else class="size-4" />
                            </Button>
                        </div>

                        <div
                            class="inline-flex w-full cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 transition hover:border-slate-300 sm:ml-auto sm:w-auto"
                            @click="openDatePicker"
                        >
                            <CalendarDays class="size-4 text-slate-500" />
                            <Input
                                ref="dateInputRef"
                                type="date"
                                :value="selectedDate"
                                list="public-viewer-dates"
                                class="h-auto w-full cursor-pointer border-0 bg-transparent p-0 text-sm shadow-none focus-visible:ring-0 sm:w-[140px]"
                                @change="onDateChange"
                                @click.stop="openDatePicker"
                            />
                            <datalist id="public-viewer-dates">
                                <option
                                    v-for="dateItem in availableDates"
                                    :key="dateItem"
                                    :value="dateItem"
                                />
                            </datalist>
                        </div>
                    </div>

                    <p
                        v-if="!hasCategories"
                        class="mt-1.5 text-xs text-slate-500"
                    >
                        No categories available.
                    </p>
                </div>

                <div
                    v-if="hasPageData && page"
                    class="grid items-stretch lg:grid-cols-[140px_minmax(0,1fr)]"
                >
                    <ThumbnailRail
                        class="hidden lg:block"
                        :pages="scopedPages"
                        :active-page-no="page.page_no"
                        :mode="thumbnailMode"
                        :rail-height="thumbnailRailHeight"
                        @select="navigateToPage"
                    />

                    <section
                        ref="viewerSectionRef"
                        class="min-w-0 bg-slate-50 p-2 sm:p-3"
                    >
                        <div class="mx-auto max-w-5xl">
                            <ViewerFrame
                                :page="page"
                                :edition-date="frameEditionDate"
                                :selected-edition-id="selectedEditionIdNumber"
                                :total-pages="scopedPages.length"
                                :prev-page-no="prevPageNo"
                                :next-page-no="nextPageNo"
                                @previous="
                                    prevPageNo !== null
                                        ? navigateToPage(prevPageNo)
                                        : null
                                "
                                @next="
                                    nextPageNo !== null
                                        ? navigateToPage(nextPageNo)
                                        : null
                                "
                            />
                        </div>
                    </section>
                </div>

                <div
                    v-else
                    class="flex items-center justify-center p-12 text-sm text-slate-500"
                >
                    No published edition found.
                </div>
            </div>
        </main>

        <footer class="border-t border-slate-200 bg-white py-4">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-6 px-4 text-xs text-slate-600 sm:flex-row sm:items-end sm:justify-between sm:text-sm"
            >
                <div class="space-y-2 text-center sm:text-left">
                    <img
                        v-if="logoUrl"
                        :src="logoUrl"
                        alt="Newspaper logo"
                        class="mx-auto h-10 w-auto object-contain sm:mx-0 sm:h-12"
                    />
                    <div v-else class="inline-flex items-center gap-2">
                        <Newspaper class="size-4 text-slate-500" />
                        <span class="text-sm font-semibold text-slate-700"
                            >ePaper</span
                        >
                    </div>
                    <p class="text-slate-500">
                        {{ settings.footer_copyright }}
                    </p>
                </div>

                <div
                    class="max-w-4xl space-y-1 text-center text-slate-500 sm:text-right"
                >
                    <p>{{ settings.footer_editor_info }}</p>
                    <p>{{ settings.footer_contact_info }}</p>
                </div>
            </div>
        </footer>

        <div
            v-if="toastMessage !== ''"
            class="pointer-events-none fixed top-4 right-4 z-50 w-full max-w-sm"
        >
            <Alert variant="destructive" class="pointer-events-auto shadow-lg">
                <AlertCircle class="size-4" />
                <AlertDescription>{{ toastMessage }}</AlertDescription>
            </Alert>
        </div>
    </div>
</template>
