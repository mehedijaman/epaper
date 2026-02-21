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
import AdBlock from '@/components/epaper/AdBlock.vue';
import HotspotModal from '@/components/epaper/HotspotModal.vue';
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
import { createHotspotPreviewDataUrl } from '@/lib/epaper/crop';
import type {
    Ad,
    Hotspot,
    LinkedHotspotRef,
    Page,
    ViewerCategoryItem,
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
        availableDates: string[];
        logoUrl?: string | null;
        settings: FooterSettings;
        adsBySlot?: Record<string, Ad[]>;
    }>(),
    {
        categories: () => [],
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
const thumbnailMode = ref<'strip' | 'grid'>('strip');
const dateInputRef = ref<HTMLInputElement | ComponentPublicInstance | null>(
    null,
);
const viewerSectionRef = ref<HTMLElement | null>(null);
const thumbnailRailHeight = ref<number | null>(null);

const toastMessage = ref('');
let toastTimeoutId: number | null = null;
let viewerSectionObserver: ResizeObserver | null = null;

const isModalOpen = ref(false);
const activeHotspotId = ref<number | null>(null);
const hotspotPreviewUrl = ref<string | null>(null);
const linkedHotspotPreviewUrl = ref<string | null>(null);
const linkedHotspotPageNo = ref<number | null>(null);
const isGeneratingPreview = ref(false);
const isGeneratingLinkedPreview = ref(false);
const hotspotPreviewImage = ref<HTMLImageElement | null>(null);
let previewJobId = 0;
let linkedPreviewJobId = 0;
const hotspotImagePromiseCache = new Map<
    string,
    Promise<HTMLImageElement | null>
>();

const hasPageData = computed(() => {
    return (
        props.editionDate !== null &&
        props.page !== null &&
        scopedPages.value.length > 0
    );
});

const currentPageNo = computed(() => props.page?.page_no ?? 0);
const currentPageHotspots = computed<Hotspot[]>(
    () => props.page?.hotspots ?? [],
);
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

const mappedCategories = computed<ViewerCategoryItem[]>(() => {
    const source =
        props.categories.length > 0
            ? props.categories
            : derivedCategories.value;

    return source
        .filter((item) => categoryPageMap.value.has(item.id))
        .sort((a, b) => a.position - b.position);
});

const hasMappedCategories = computed(() => mappedCategories.value.length > 0);

const adSlots = computed(() => {
    if (props.adsBySlot === undefined) {
        return [];
    }

    return Array.from({ length: 8 }, (_, index) => {
        const slotNo = index + 1;

        return {
            slotNo,
            ads: props.adsBySlot?.[String(slotNo)] ?? [],
        };
    });
});

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

watch(currentPageHotspots, (hotspots) => {
    if (activeHotspotId.value === null) {
        return;
    }

    const activeExists = hotspots.some(
        (hotspot) => hotspot.id === activeHotspotId.value,
    );

    if (!activeExists) {
        closeHotspotModal();
    }
});

watch(
    () => props.page?.id,
    () => {
        void openHotspotFromHashIfNeeded();
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

function viewerUrl(date: string, pageNo: number): string {
    return `/epaper/${date}/page/${pageNo}`;
}

function hotspotIdFromHash(hash: string): number | null {
    const match = /^#hotspot-(\d+)$/.exec(hash.trim());

    if (match === null) {
        return null;
    }

    const hotspotId = Number.parseInt(match[1] ?? '', 10);

    if (!Number.isFinite(hotspotId) || hotspotId <= 0) {
        return null;
    }

    return hotspotId;
}

function readHotspotIdFromLocationHash(): number | null {
    if (typeof window === 'undefined') {
        return null;
    }

    return hotspotIdFromHash(window.location.hash);
}

function updateLocationHash(hotspotId: number | null): void {
    if (typeof window === 'undefined') {
        return;
    }

    const baseUrl = `${window.location.pathname}${window.location.search}`;
    const nextUrl =
        hotspotId === null ? baseUrl : `${baseUrl}#hotspot-${hotspotId}`;
    window.history.replaceState(window.history.state, '', nextUrl);
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

    router.visit(viewerUrl(props.editionDate, target.page_no), {
        preserveScroll: true,
    });
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
        return;
    }

    navigateToPage(pageNo);
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

function resetLinkedHotspotPreview(): void {
    linkedHotspotPreviewUrl.value = null;
    linkedHotspotPageNo.value = null;
    isGeneratingLinkedPreview.value = false;
    linkedPreviewJobId += 1;
}

function closeHotspotModal(): void {
    isModalOpen.value = false;
    activeHotspotId.value = null;
    hotspotPreviewImage.value = null;
    hotspotPreviewUrl.value = null;
    isGeneratingPreview.value = false;
    previewJobId += 1;
    resetLinkedHotspotPreview();
    updateLocationHash(null);
}

function waitForImageReady(image: HTMLImageElement): Promise<void> {
    if (image.complete && image.naturalWidth > 0 && image.naturalHeight > 0) {
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        const done = () => {
            image.removeEventListener('load', done);
            image.removeEventListener('error', done);
            resolve();
        };

        image.addEventListener('load', done, { once: true });
        image.addEventListener('error', done, { once: true });
    });
}

function toHotspotFromReference(reference: LinkedHotspotRef): Hotspot {
    return {
        id: reference.id,
        relation_kind: reference.relation_kind,
        target_page_no: reference.target_page_no,
        x: reference.x,
        y: reference.y,
        w: reference.w,
        h: reference.h,
        label: reference.label,
    };
}

function findHotspotByIdInPages(
    hotspotId: number,
): { hotspot: Hotspot; pageNo: number } | null {
    for (const pageItem of scopedPages.value) {
        const found = pageItem.hotspots.find(
            (candidate) => candidate.id === hotspotId,
        );

        if (found !== undefined) {
            return {
                hotspot: found,
                pageNo: pageItem.page_no,
            };
        }
    }

    return null;
}

function resolveLinkedHotspot(
    hotspot: Hotspot,
): { hotspot: Hotspot; pageNo: number } | null {
    const linkedReference = hotspot.linked_hotspot ?? hotspot.target_hotspot;

    if (linkedReference !== null && linkedReference !== undefined) {
        return {
            hotspot: toHotspotFromReference(linkedReference),
            pageNo: linkedReference.page_no,
        };
    }

    const linkedHotspotId =
        hotspot.linked_hotspot_id ?? hotspot.target_hotspot_id;

    if (linkedHotspotId !== null && linkedHotspotId !== undefined) {
        const matchedById = findHotspotByIdInPages(linkedHotspotId);

        if (matchedById !== null) {
            return matchedById;
        }
    }

    if (hotspot.target_page_no <= 0) {
        return null;
    }

    const targetPage = pageByNumber.value.get(hotspot.target_page_no);

    if (targetPage === undefined || targetPage.hotspots.length === 0) {
        return null;
    }

    const reverseLinked = targetPage.hotspots.find((candidate) => {
        const candidateLinkedId =
            candidate.linked_hotspot_id ?? candidate.target_hotspot_id;
        return (
            candidateLinkedId !== null &&
            candidateLinkedId !== undefined &&
            candidateLinkedId === hotspot.id
        );
    });

    if (reverseLinked !== undefined) {
        return {
            hotspot: reverseLinked,
            pageNo: targetPage.page_no,
        };
    }

    const reverseByTargetPage = targetPage.hotspots.find(
        (candidate) => candidate.target_page_no === currentPageNo.value,
    );

    if (reverseByTargetPage !== undefined) {
        return {
            hotspot: reverseByTargetPage,
            pageNo: targetPage.page_no,
        };
    }

    const firstTargetHotspot = targetPage.hotspots[0];

    if (firstTargetHotspot === undefined) {
        return null;
    }

    return {
        hotspot: firstTargetHotspot,
        pageNo: targetPage.page_no,
    };
}

function pageImageUrl(pageNo: number): string | null {
    const fromPageList = pageByNumber.value.get(pageNo);

    if (fromPageList !== undefined) {
        return (
            fromPageList.image_large_url ||
            fromPageList.image_original_url ||
            fromPageList.image_thumb_url
        );
    }

    if (props.page !== null && props.page.page_no === pageNo) {
        return (
            props.page.image_large_url ||
            props.page.image_original_url ||
            props.page.image_thumb_url
        );
    }

    return null;
}

function loadImageFromUrl(url: string): Promise<HTMLImageElement | null> {
    const cached = hotspotImagePromiseCache.get(url);

    if (cached !== undefined) {
        return cached;
    }

    const promise = new Promise<HTMLImageElement | null>((resolve) => {
        const image = new Image();
        image.decoding = 'async';

        const done = (result: HTMLImageElement | null) => {
            if (result === null) {
                hotspotImagePromiseCache.delete(url);
            }

            resolve(result);
        };

        image.addEventListener(
            'load',
            () => {
                done(image);
            },
            { once: true },
        );
        image.addEventListener(
            'error',
            () => {
                done(null);
            },
            { once: true },
        );

        image.src = url;
    });

    hotspotImagePromiseCache.set(url, promise);

    return promise;
}

async function updateLinkedHotspotPreview(
    sourceHotspot: Hotspot,
): Promise<void> {
    resetLinkedHotspotPreview();

    const linked = resolveLinkedHotspot(sourceHotspot);

    if (linked === null) {
        return;
    }

    linkedHotspotPageNo.value = linked.pageNo;
    isGeneratingLinkedPreview.value = true;

    const jobId = ++linkedPreviewJobId;
    const linkedImageUrl = pageImageUrl(linked.pageNo);

    if (linkedImageUrl === null) {
        if (jobId === linkedPreviewJobId) {
            isGeneratingLinkedPreview.value = false;
        }

        return;
    }

    const linkedImage = await loadImageFromUrl(linkedImageUrl);

    if (jobId !== linkedPreviewJobId) {
        return;
    }

    if (linkedImage === null) {
        isGeneratingLinkedPreview.value = false;
        return;
    }

    await waitForImageReady(linkedImage);

    const previewUrl = createHotspotPreviewDataUrl(linkedImage, {
        x: linked.hotspot.x,
        y: linked.hotspot.y,
        w: linked.hotspot.w,
        h: linked.hotspot.h,
    });

    if (jobId !== linkedPreviewJobId) {
        return;
    }

    linkedHotspotPreviewUrl.value = previewUrl;
    isGeneratingLinkedPreview.value = false;
}

async function openHotspotFromHashIfNeeded(): Promise<void> {
    const hotspotId = readHotspotIdFromLocationHash();

    if (hotspotId === null) {
        return;
    }

    const hotspotOnCurrentPage = currentPageHotspots.value.some(
        (hotspot) => hotspot.id === hotspotId,
    );

    if (hotspotOnCurrentPage) {
        isModalOpen.value = true;
        await selectHotspot(hotspotId);
        return;
    }

    if (props.editionDate === null) {
        return;
    }

    const hotspotLocation = findHotspotByIdInPages(hotspotId);

    if (
        hotspotLocation === null ||
        hotspotLocation.pageNo === currentPageNo.value
    ) {
        return;
    }

    router.visit(
        `${viewerUrl(props.editionDate, hotspotLocation.pageNo)}#hotspot-${hotspotId}`,
        {
            preserveScroll: true,
        },
    );
}

function onHashChange(): void {
    const hotspotId = readHotspotIdFromLocationHash();

    if (hotspotId === null) {
        if (isModalOpen.value) {
            closeHotspotModal();
        }

        return;
    }

    void openHotspotFromHashIfNeeded();
}

async function onHotspotClick(payload: {
    hotspot: Hotspot;
    image: HTMLImageElement | null;
}): Promise<void> {
    hotspotPreviewImage.value = payload.image;
    isModalOpen.value = true;

    await selectHotspot(payload.hotspot.id);
}

async function selectHotspot(hotspotId: number): Promise<void> {
    const hotspot = currentPageHotspots.value.find(
        (item) => item.id === hotspotId,
    );

    if (hotspot === undefined) {
        return;
    }

    activeHotspotId.value = hotspot.id;
    hotspotPreviewUrl.value = null;
    isGeneratingPreview.value = true;
    void updateLinkedHotspotPreview(hotspot);
    updateLocationHash(hotspot.id);

    const jobId = ++previewJobId;

    await nextTick();

    let previewImage = hotspotPreviewImage.value;

    if (previewImage === null) {
        const currentImageUrl = pageImageUrl(currentPageNo.value);

        if (currentImageUrl !== null) {
            previewImage = await loadImageFromUrl(currentImageUrl);
        }
    }

    if (jobId !== previewJobId) {
        return;
    }

    if (previewImage === null) {
        if (jobId === previewJobId) {
            hotspotPreviewUrl.value = null;
            isGeneratingPreview.value = false;
        }

        return;
    }

    hotspotPreviewImage.value = previewImage;
    await waitForImageReady(previewImage);

    const previewUrl = createHotspotPreviewDataUrl(previewImage, {
        x: hotspot.x,
        y: hotspot.y,
        w: hotspot.w,
        h: hotspot.h,
    });

    if (jobId !== previewJobId) {
        return;
    }

    hotspotPreviewUrl.value = previewUrl;
    isGeneratingPreview.value = false;
}

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    window.addEventListener('hashchange', onHashChange);
    refreshViewerSectionObserver();
    updateThumbnailRailHeight();
});

onBeforeUnmount(() => {
    if (toastTimeoutId !== null) {
        window.clearTimeout(toastTimeoutId);
    }

    if (typeof window !== 'undefined') {
        window.removeEventListener('hashchange', onHashChange);
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
                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
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
                                    :disabled="!hasMappedCategories"
                                    @update:model-value="onCategorySelect"
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="category in mappedCategories"
                                            :key="category.id"
                                            :value="String(category.id)"
                                        >
                                            {{ category.name }}
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
                        v-if="!hasMappedCategories"
                        class="mt-1.5 text-xs text-slate-500"
                    >
                        No categories mapped in this edition.
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
                                @hotspot-click="onHotspotClick"
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

            <!-- Temporary hidden ad slots
            <div v-if="adSlots.length > 0" class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <section
                    v-for="slot in adSlots"
                    :key="slot.slotNo"
                    class="space-y-2 rounded border border-border/70 bg-card p-2"
                >
                    <h3 class="text-xs font-semibold uppercase text-muted-foreground">Slot {{ slot.slotNo }}</h3>
                    <AdBlock v-for="ad in slot.ads" :key="ad.id" :ad="ad" />
                    <p v-if="slot.ads.length === 0" class="text-xs text-muted-foreground">No ad in this slot.</p>
                </section>
            </div>
            -->
        </main>

        <footer class="border-t border-slate-200 bg-white py-4">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-4 px-4 text-xs text-slate-600 sm:flex-row sm:items-end sm:justify-between sm:text-sm"
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
                    class="max-w-lg space-y-1 text-center text-slate-500 sm:text-right"
                >
                    <p>{{ settings.footer_editor_info }}</p>
                    <p>{{ settings.footer_contact_info }}</p>
                </div>
            </div>
        </footer>

        <HotspotModal
            :open="isModalOpen"
            :active-hotspot-id="activeHotspotId"
            :current-page-no="page?.page_no ?? null"
            :preview-url="hotspotPreviewUrl"
            :loading="isGeneratingPreview"
            :linked-preview-url="linkedHotspotPreviewUrl"
            :linked-loading="isGeneratingLinkedPreview"
            :linked-page-no="linkedHotspotPageNo"
            @close="closeHotspotModal"
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
