<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Grid2x2,
    List,
} from 'lucide-vue-next';
import type { AcceptableValue } from 'reka-ui';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import AdBlock from '@/components/epaper/AdBlock.vue';
import PublicFooter from '@/components/epaper/PublicFooter.vue';
import PublicHeader from '@/components/epaper/PublicHeader.vue';
import ThumbnailRail from '@/components/epaper/ThumbnailRail.vue';
import ViewerFrame from '@/components/epaper/ViewerFrame.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
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
    site_url: string;
    social_facebook: string;
    social_x: string;
    social_youtube: string;
    social_linkedin: string;
    social_instagram: string;
    social_pinterest: string;
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

const selectedDate = ref(props.editionDate ?? dateInDhaka());
const selectedCategory = ref('');
const selectedEditionId = ref('');
const thumbnailMode = ref<'strip' | 'grid'>('strip');
const calendarRef = ref<HTMLElement | null>(null);
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

function adsForPosition(title: string): Ad[] {
    return props.adsBySlot?.[title] ?? [];
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

const calendarOpen = ref(false);
const calendarViewYear = ref(new Date().getFullYear());
const calendarViewMonth = ref(new Date().getMonth());

const WEEKDAY_LABELS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
const MONTH_LABELS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

const calendarMonthLabel = computed(
    () => `${MONTH_LABELS[calendarViewMonth.value]} ${calendarViewYear.value}`,
);

const calendarGrid = computed<(string | null)[]>(() => {
    const year = calendarViewYear.value;
    const month = calendarViewMonth.value;
    const firstWeekday = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const cells: (string | null)[] = Array<null>(firstWeekday).fill(null);
    for (let d = 1; d <= daysInMonth; d++) {
        const mm = String(month + 1).padStart(2, '0');
        const dd = String(d).padStart(2, '0');
        cells.push(`${year}-${mm}-${dd}`);
    }
    while (cells.length % 7 !== 0) {
        cells.push(null);
    }
    return cells;
});

const availableDatesSet = computed(() => new Set(props.availableDates));

const formattedSelectedDate = computed(() => {
    if (!selectedDate.value || !isIsoDate(selectedDate.value)) {
        return 'Select date';
    }
    const d = new Date(selectedDate.value + 'T00:00:00');
    return new Intl.DateTimeFormat('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(d);
});

function isDateAvailable(date: string): boolean {
    return availableDatesSet.value.has(date);
}

function openCalendar(): void {
    if (selectedDate.value && isIsoDate(selectedDate.value)) {
        const d = new Date(selectedDate.value + 'T00:00:00');
        calendarViewYear.value = d.getFullYear();
        calendarViewMonth.value = d.getMonth();
    }
    calendarOpen.value = true;
}

function prevCalendarMonth(): void {
    if (calendarViewMonth.value === 0) {
        calendarViewMonth.value = 11;
        calendarViewYear.value--;
    } else {
        calendarViewMonth.value--;
    }
}

function nextCalendarMonth(): void {
    if (calendarViewMonth.value === 11) {
        calendarViewMonth.value = 0;
        calendarViewYear.value++;
    } else {
        calendarViewMonth.value++;
    }
}

function selectCalendarDate(date: string | null): void {
    if (date === null || !isDateAvailable(date)) {
        return;
    }
    calendarOpen.value = false;
    selectedDate.value = date;
    void navigateToDate(date);
}

function handleCalendarClickOutside(event: MouseEvent): void {
    if (calendarRef.value !== null && !calendarRef.value.contains(event.target as Node)) {
        calendarOpen.value = false;
    }
}

watch(calendarOpen, (isOpen) => {
    if (isOpen) {
        document.addEventListener('click', handleCalendarClickOutside);
    } else {
        document.removeEventListener('click', handleCalendarClickOutside);
    }
});

function toggleThumbnailMode(): void {
    thumbnailMode.value = thumbnailMode.value === 'strip' ? 'grid' : 'strip';
}

function navigateToPageFromGrid(pageNo: number): void {
    thumbnailMode.value = 'strip';
    navigateToPage(pageNo);
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
    document.removeEventListener('click', handleCalendarClickOutside);
});
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-screen flex-col bg-slate-100 text-slate-900">
        <PublicHeader
                :logo-url="logoUrl"
                :site-url="settings.site_url"
                :edition-date="editionDate"
                :social-facebook="settings.social_facebook"
                :social-x="settings.social_x"
                :social-youtube="settings.social_youtube"
                :social-linkedin="settings.social_linkedin"
                :social-instagram="settings.social_instagram"
                :social-pinterest="settings.social_pinterest"
            />

        <main class="mx-auto w-full max-w-7xl flex-1 px-2 py-3 sm:px-4">
            <div
                class="rounded-xl border border-slate-200 bg-white shadow-sm"
            >
                <div
                    class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 px-3 py-2.5 backdrop-blur-sm"
                >
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-2">
                        <!-- Row 1 on mobile (Edition + Calendar); sm:contents lets children join the parent flex row on desktop -->
                        <div class="flex items-center gap-2 sm:contents">
                            <div class="min-w-0 flex-1 sm:w-55 sm:flex-none">
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

                            <div ref="calendarRef" class="relative ml-auto shrink-0 sm:order-last sm:ml-auto">
                                <button
                                    type="button"
                                    class="inline-flex w-full cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm transition hover:border-slate-300 hover:bg-white sm:w-auto"
                                    @click.stop="openCalendar"
                                >
                                    <CalendarDays class="size-4 shrink-0 text-slate-400" />
                                    <span class="font-semibold text-slate-800">{{ formattedSelectedDate }}</span>
                                </button>

                                <div
                                    v-if="calendarOpen"
                                    class="absolute right-0 top-full z-50 mt-1 w-72 rounded-xl border border-slate-200 bg-white p-3 shadow-xl"
                                >
                                    <div class="mb-2 flex items-center justify-between">
                                        <button
                                            type="button"
                                            class="flex size-7 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                                            @click.stop="prevCalendarMonth"
                                        >
                                            <ChevronLeft class="size-4" />
                                        </button>
                                        <span class="text-sm font-semibold text-slate-800">{{ calendarMonthLabel }}</span>
                                        <button
                                            type="button"
                                            class="flex size-7 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                                            @click.stop="nextCalendarMonth"
                                        >
                                            <ChevronRight class="size-4" />
                                        </button>
                                    </div>
                                    <div class="mb-1 grid grid-cols-7 text-center">
                                        <div
                                            v-for="label in WEEKDAY_LABELS"
                                            :key="label"
                                            class="py-1 text-xs font-medium text-slate-400"
                                        >
                                            {{ label }}
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-7 gap-0.5">
                                        <template v-for="(date, i) in calendarGrid" :key="i">
                                            <button
                                                v-if="date !== null"
                                                type="button"
                                                :disabled="!isDateAvailable(date)"
                                                :class="[
                                                    'relative w-full rounded-md py-1.5 text-xs transition',
                                                    date === selectedDate
                                                        ? 'bg-slate-900 font-semibold text-white'
                                                        : isDateAvailable(date)
                                                          ? 'cursor-pointer font-medium text-slate-700 hover:bg-slate-100'
                                                          : 'cursor-not-allowed text-slate-200',
                                                ]"
                                                @click.stop="selectCalendarDate(date)"
                                            >
                                                {{ Number(date.split('-')[2]) }}
                                                <span
                                                    v-if="isDateAvailable(date) && date !== selectedDate"
                                                    class="absolute bottom-0.5 left-1/2 size-1 -translate-x-1/2 rounded-full bg-slate-400"
                                                />
                                            </button>
                                            <div v-else />
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2 on mobile (Pagination + Toggle); sm:contents lets children join the parent flex row on desktop -->
                        <div class="flex items-center gap-2 sm:contents">
                            <div class="flex min-w-0 flex-1 items-center gap-1">
                                <button
                                    type="button"
                                    :disabled="prevPageNo === null || !hasPageData"
                                    title="Previous page"
                                    class="flex h-7 shrink-0 items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2 text-xs font-medium text-slate-600 transition-all hover:border-slate-300 hover:bg-white hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-40"
                                    @click="prevPageNo !== null ? navigateToPage(prevPageNo) : null"
                                >
                                    <ChevronLeft class="size-3.5" />
                                    <span class="hidden sm:inline">Prev</span>
                                </button>

                                <div class="min-w-0 flex-1 overflow-x-auto">
                                    <div class="flex w-max items-center gap-0.5 rounded-lg border border-slate-200 bg-slate-50 p-0.5">
                                        <button
                                            v-for="item in scopedPages"
                                            :key="item.id"
                                            type="button"
                                            :disabled="!hasPageData"
                                            :title="`Page ${item.page_no}`"
                                            class="flex size-6 items-center justify-center rounded-md text-xs font-semibold transition-all"
                                            :class="
                                                item.page_no === currentPageNo
                                                    ? 'bg-slate-900 text-white shadow-sm'
                                                    : 'text-slate-500 hover:bg-white hover:text-slate-900 hover:shadow-sm'
                                            "
                                            @click="navigateToPage(item.page_no)"
                                        >
                                            {{ item.page_no }}
                                        </button>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    :disabled="nextPageNo === null || !hasPageData"
                                    title="Next page"
                                    class="flex h-7 shrink-0 items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2 text-xs font-medium text-slate-600 transition-all hover:border-slate-300 hover:bg-white hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-40"
                                    @click="nextPageNo !== null ? navigateToPage(nextPageNo) : null"
                                >
                                    <span class="hidden sm:inline">Next</span>
                                    <ChevronRight class="size-3.5" />
                                </button>
                            </div>

                            <button
                                type="button"
                                class="flex h-7 shrink-0 items-center gap-1.5 rounded-md border px-2.5 text-xs font-medium transition-all"
                                :class="
                                    thumbnailMode === 'grid'
                                        ? 'border-slate-900 bg-slate-900 text-white hover:bg-slate-700 hover:border-slate-700'
                                        : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-slate-300 hover:bg-white hover:text-slate-900'
                                "
                                :title="thumbnailMode === 'strip' ? 'All pages (thumbnail view)' : 'Back to reader'"
                                @click="toggleThumbnailMode"
                            >
                                <Grid2x2 v-if="thumbnailMode === 'strip'" class="size-3.5" />
                                <List v-else class="size-3.5" />
                                সব পাতা
                            </button>
                        </div>
                    </div>

                    <!-- <p
                        v-if="!hasCategories"
                        class="mt-1.5 text-xs text-slate-500"
                    >
                        No categories available.
                    </p> -->
                </div>

                <!-- Top Banner -->
                <div
                    v-if="adsForPosition('Top Banner').length > 0"
                    class="border-b border-slate-200 px-3 py-2"
                >
                    <AdBlock
                        v-for="ad in adsForPosition('Top Banner')"
                        :key="ad.id"
                        :ad="ad"
                        class="rounded-lg"
                    />
                </div>

                <template v-if="hasPageData && page">
                    <!-- Full-page thumbnail grid view -->
                    <div
                        v-if="thumbnailMode === 'grid'"
                        class="p-3 sm:p-5"
                    >
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                            <button
                                v-for="item in scopedPages"
                                :key="item.id"
                                type="button"
                                class="group w-full overflow-hidden rounded-lg border bg-white p-1.5 text-left transition-all hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-400"
                                :class="
                                    item.page_no === currentPageNo
                                        ? 'border-sky-500 shadow-sm ring-1 ring-sky-500'
                                        : 'border-slate-200 hover:border-slate-300'
                                "
                                :title="`Go to page ${item.page_no}`"
                                @click="navigateToPageFromGrid(item.page_no)"
                            >
                                <img
                                    :src="item.image_thumb_url"
                                    :alt="`Page ${item.page_no}`"
                                    loading="lazy"
                                    class="h-auto w-full rounded"
                                />
                                <p
                                    class="mt-1 text-center text-[11px] font-medium bg-black text-white"
                                    :class="
                                        item.page_no === currentPageNo
                                            ? 'text-sky-700'
                                            : 'text-slate-500 group-hover:text-slate-700'
                                    "
                                >
                                    Page {{ item.page_no }}
                                </p>
                            </button>
                        </div>
                    </div>

                    <!-- Strip reader view -->
                    <div
                        v-else
                        class="grid items-stretch lg:grid-cols-[140px_minmax(0,1fr)]"
                    >
                        <ThumbnailRail
                            class="hidden lg:block"
                            :pages="scopedPages"
                            :active-page-no="page.page_no"
                            :mode="'strip'"
                            :rail-height="thumbnailRailHeight"
                            @select="navigateToPage"
                        />

                        <section
                            ref="viewerSectionRef"
                            class="min-w-0 bg-slate-50 p-2 sm:p-3"
                        >
                            <div
                                class="mx-auto max-w-5xl"
                                :class="adsForPosition('Sidebar Right').length > 0 ? 'xl:grid xl:grid-cols-[minmax(0,1fr)_200px] xl:gap-3' : ''"
                            >
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
                                <!-- Sidebar Right -->
                                <aside
                                    v-if="adsForPosition('Sidebar Right').length > 0"
                                    class="hidden xl:flex xl:flex-col xl:gap-2"
                                >
                                    <AdBlock
                                        v-for="ad in adsForPosition('Sidebar Right')"
                                        :key="ad.id"
                                        :ad="ad"
                                    />
                                </aside>
                            </div>

                            <!-- Between-content banner (Sidebar Left on small screens) -->
                            <div
                                v-if="adsForPosition('Sidebar Left').length > 0"
                                class="mx-auto mt-3 max-w-5xl space-y-2"
                            >
                                <AdBlock
                                    v-for="ad in adsForPosition('Sidebar Left')"
                                    :key="ad.id"
                                    :ad="ad"
                                />
                            </div>
                        </section>
                    </div>
                </template>

                <div
                    v-else
                    class="flex items-center justify-center p-12 text-sm text-slate-500"
                >
                    No published edition found.
                </div>
            </div>
        </main>

        <!-- Footer Banner -->
        <div
            v-if="adsForPosition('Footer Banner').length > 0"
            class="border-t border-slate-200 bg-slate-50 py-3"
        >
            <div class="mx-auto max-w-7xl space-y-2 px-4">
                <AdBlock
                    v-for="ad in adsForPosition('Footer Banner')"
                    :key="ad.id"
                    :ad="ad"
                />
            </div>
        </div>

        <PublicFooter
            :logo-url="logoUrl"
            :site-url="settings.site_url"
            :copyright="settings.footer_copyright"
            :editor-info="settings.footer_editor_info"
            :contact-info="settings.footer_contact_info"
            :social-facebook="settings.social_facebook"
            :social-x="settings.social_x"
            :social-youtube="settings.social_youtube"
            :social-linkedin="settings.social_linkedin"
            :social-instagram="settings.social_instagram"
            :social-pinterest="settings.social_pinterest"
        />

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
