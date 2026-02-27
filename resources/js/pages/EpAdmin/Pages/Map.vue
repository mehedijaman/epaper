<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Link, Pencil, Save, Trash2 } from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import ConfirmActionDialog from '@/components/epaper/ConfirmActionDialog.vue';
import EditionContextBar from '@/components/epaper/EditionContextBar.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import EpAdminLayout from '@/layouts/EpAdminLayout.vue';
import type { BreadcrumbItem, Hotspot, Page } from '@/types';

type EditionInfo = {
    id: number;
    edition_date: string;
    name?: string | null;
    status: 'draft' | 'published';
    published_at: string | null;
    pages_count: number;
    max_page_no: number;
};

type EditionPageItem = {
    id: number;
    page_no: number;
};

type TargetHotspotOption = {
    id: number;
    relation_kind: 'next' | 'previous';
    target_page_no: number | null;
    label: string | null;
};

type NormalizedPoint = {
    x: number;
    y: number;
};

type NormalizedRect = {
    x: number;
    y: number;
    w: number;
    h: number;
};

type AreaTransformHandle =
    | 'move'
    | 'n'
    | 's'
    | 'e'
    | 'w'
    | 'nw'
    | 'ne'
    | 'sw'
    | 'se';
type AreaResizeHandle = Exclude<AreaTransformHandle, 'move'>;

type HotspotLinkState = 'none' | 'paired' | 'outbound' | 'inbound' | 'mismatch';
type HotspotListFilter = 'all' | HotspotLinkState;
type HotspotSortOption = 'id_asc' | 'id_desc' | 'target_page' | 'relation';
type BulkDeletePreset = 'manual' | 'mismatch' | 'unlinked';

type HotspotForm = {
    page_id: number;
    relation_kind: 'next' | 'previous';
    target_page_no: number | null;
    target_hotspot_id: number | null;
    x: number;
    y: number;
    w: number;
    h: number;
    label: string;
};

type Props = {
    selected_page_id: number | null;
    edition: EditionInfo | null;
    page: Page | null;
    edition_pages: EditionPageItem[];
    target_page_numbers: number[];
    target_hotspots_by_page: Record<string, TargetHotspotOption[]>;
    focus_hotspot_id: number | null;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Page Mapping', href: '/admin/hotspots' },
];

const canvasViewportRef = ref<HTMLDivElement | null>(null);
const imageRef = ref<HTMLImageElement | null>(null);
const imageDimensions = ref({ width: 0, height: 0 });
const imageNaturalDimensions = ref({ width: 0, height: 0 });
const selectedPageId = ref<string>(
    props.selected_page_id !== null ? String(props.selected_page_id) : '',
);
const drawing = ref(false);
const drawingStart = ref<NormalizedPoint | null>(null);
const draftRect = ref<NormalizedRect | null>(null);
const isDialogOpen = ref(false);
const editingHotspotId = ref<number | null>(null);
const pendingFocusHotspotId = ref<number | null>(props.focus_hotspot_id);
const hotspotSearch = ref('');
const activeHotspotId = ref<number | null>(null);
const drawModeEnabled = ref(true);
const hotspotStateFilter = ref<HotspotListFilter>('all');
const hotspotSort = ref<HotspotSortOption>('id_asc');
const hotspotRowRefs = new Map<number, HTMLElement>();
const areaEditHotspotId = ref<number | null>(null);
const deleteDialogOpen = ref(false);
const pendingDeleteHotspot = ref<Hotspot | null>(null);
const selectedHotspotIds = ref<number[]>([]);
const bulkDeleteDialogOpen = ref(false);
const bulkDeletePreset = ref<BulkDeletePreset>('manual');
const discardDialogOpen = ref(false);
const hotspotDialogBaseline = ref<string | null>(null);
const pendingPageSelectionId = ref<string | null>(null);
const activeAreaTransformHandle = ref<AreaTransformHandle | null>(null);
const areaTransformStartPoint = ref<NormalizedPoint | null>(null);
const areaTransformStartRect = ref<NormalizedRect | null>(null);
const areaTransformPreviewRect = ref<NormalizedRect | null>(null);
const areaEditRect = ref<NormalizedRect | null>(null);
const savingAreaHotspotId = ref<number | null>(null);

const hotspotForm = useForm<HotspotForm>({
    page_id: 0,
    relation_kind: 'next',
    target_page_no: null,
    target_hotspot_id: null,
    x: 0,
    y: 0,
    w: 0,
    h: 0,
    label: '',
});

const bulkDeleteForm = useForm<{
    page_id: number;
    hotspot_ids: number[];
}>({
    page_id: 0,
    hotspot_ids: [],
});

const currentPage = computed(() => props.page);
const currentHotspots = computed(() => currentPage.value?.hotspots ?? []);
const hasPage = computed(() => currentPage.value !== null);
const areaResizeHandles: AreaResizeHandle[] = [
    'nw',
    'n',
    'ne',
    'e',
    'se',
    's',
    'sw',
    'w',
];
const targetPageNumbers = computed<number[]>(() => {
    if (props.target_page_numbers.length > 0) {
        return props.target_page_numbers;
    }

    if (currentPage.value !== null) {
        return [currentPage.value.page_no];
    }

    return [];
});
const targetHotspotsByPage = computed<Map<number, TargetHotspotOption[]>>(
    () => {
        const map = new Map<number, TargetHotspotOption[]>();

        for (const [pageNo, hotspots] of Object.entries(
            props.target_hotspots_by_page,
        )) {
            const parsedPageNo = Number.parseInt(pageNo, 10);

            if (!Number.isFinite(parsedPageNo)) {
                continue;
            }

            map.set(
                parsedPageNo,
                hotspots.map((hotspot) => ({
                    id: hotspot.id,
                    relation_kind: hotspot.relation_kind,
                    target_page_no: hotspot.target_page_no,
                    label: hotspot.label,
                })),
            );
        }

        return map;
    },
);
const availableTargetHotspots = computed<TargetHotspotOption[]>(() => {
    if (hotspotForm.target_page_no === null) {
        return [];
    }

    const options =
        targetHotspotsByPage.value.get(hotspotForm.target_page_no) ?? [];

    if (editingHotspotId.value === null) {
        return options;
    }

    return options.filter((item) => item.id !== editingHotspotId.value);
});
const targetPageSelectValue = computed<string>({
    get: () =>
        hotspotForm.target_page_no === null
            ? '__none'
            : String(hotspotForm.target_page_no),
    set: (value) => {
        if (value === '__none') {
            hotspotForm.target_page_no = null;
            return;
        }

        const parsed = Number.parseInt(value, 10);
        hotspotForm.target_page_no = Number.isFinite(parsed) ? parsed : null;
    },
});
const targetHotspotSelectValue = computed<string>({
    get: () =>
        hotspotForm.target_hotspot_id === null
            ? '__none'
            : String(hotspotForm.target_hotspot_id),
    set: (value) => {
        if (value === '__none') {
            hotspotForm.target_hotspot_id = null;
            return;
        }

        const parsed = Number.parseInt(value, 10);
        hotspotForm.target_hotspot_id = Number.isFinite(parsed) ? parsed : null;
    },
});
const overlayStyle = computed<Record<string, string>>(() => ({
    width: `${imageDimensions.value.width}px`,
    height: `${imageDimensions.value.height}px`,
}));
const imageSource = computed(() => {
    if (currentPage.value === null) {
        return '';
    }

    const largeUrl = currentPage.value.image_large_url;

    if (typeof largeUrl === 'string' && largeUrl.trim() !== '') {
        return largeUrl;
    }

    return currentPage.value.image_original_url;
});
const hotspotDialogTitle = computed(() =>
    editingHotspotId.value === null ? 'Create hotspot' : 'Edit hotspot',
);
const hotspotDialogActionText = computed(() =>
    editingHotspotId.value === null ? 'Save hotspot' : 'Update hotspot',
);
const hasDraftRect = computed(() => draftRect.value !== null);
const orderedHotspots = computed(() => {
    const rows = [...currentHotspots.value];

    if (hotspotSort.value === 'id_desc') {
        return rows.sort((a, b) => b.id - a.id);
    }

    if (hotspotSort.value === 'target_page') {
        return rows.sort((a, b) => {
            if (a.target_page_no === b.target_page_no) {
                return a.id - b.id;
            }

            const leftTargetPageNo = a.target_page_no ?? Number.MAX_SAFE_INTEGER;
            const rightTargetPageNo = b.target_page_no ?? Number.MAX_SAFE_INTEGER;

            return leftTargetPageNo - rightTargetPageNo;
        });
    }

    if (hotspotSort.value === 'relation') {
        return rows.sort((a, b) => {
            if (a.relation_kind === b.relation_kind) {
                return a.id - b.id;
            }

            return a.relation_kind.localeCompare(b.relation_kind);
        });
    }

    return rows.sort((a, b) => a.id - b.id);
});
const stateFilteredHotspots = computed(() => {
    if (hotspotStateFilter.value === 'all') {
        return orderedHotspots.value;
    }

    return orderedHotspots.value.filter(
        (hotspot) => hotspotLinkState(hotspot) === hotspotStateFilter.value,
    );
});
const filteredHotspots = computed(() => {
    const query = hotspotSearch.value.trim().toLowerCase();

    if (query === '') {
        return stateFilteredHotspots.value;
    }

    return stateFilteredHotspots.value.filter((hotspot) => {
        const label = hotspot.label?.toLowerCase() ?? '';
        const linkId = linkedHotspotId(hotspot);

        return (
            String(hotspot.id).includes(query) ||
            hotspot.relation_kind.toLowerCase().includes(query) ||
            String(hotspot.target_page_no ?? 'none').includes(query) ||
            (linkId !== null && String(linkId).includes(query)) ||
            label.includes(query) ||
            hotspotLinkLabel(hotspotLinkState(hotspot))
                .toLowerCase()
                .includes(query)
        );
    });
});
const selectedHotspotIdSet = computed(() => new Set(selectedHotspotIds.value));
const selectedHotspotCount = computed(() => selectedHotspotIds.value.length);
const visibleSelectedHotspotCount = computed(() =>
    filteredHotspots.value.reduce(
        (count, hotspot) =>
            selectedHotspotIdSet.value.has(hotspot.id) ? count + 1 : count,
        0,
    ),
);
const hasSelectedHotspots = computed(() => selectedHotspotCount.value > 0);
const areAllVisibleHotspotsSelected = computed(
    () =>
        filteredHotspots.value.length > 0 &&
        visibleSelectedHotspotCount.value === filteredHotspots.value.length,
);
const hasDialogUnsavedChanges = computed(() => {
    if (!isDialogOpen.value || hotspotDialogBaseline.value === null) {
        return false;
    }

    return hotspotDialogBaseline.value !== serializeHotspotFormState();
});
const hasUnsavedWork = computed(
    () =>
        hasDialogUnsavedChanges.value ||
        draftRect.value !== null ||
        areaEditRect.value !== null,
);
const mismatchHotspotIds = computed(() =>
    currentHotspots.value
        .filter((hotspot) => hotspotLinkState(hotspot) === 'mismatch')
        .map((hotspot) => hotspot.id),
);
const unlinkedHotspotIds = computed(() =>
    currentHotspots.value
        .filter((hotspot) => hotspotLinkState(hotspot) === 'none')
        .map((hotspot) => hotspot.id),
);
const bulkDeleteDialogTitle = computed(() => {
    if (bulkDeletePreset.value === 'mismatch') {
        return 'Delete mismatch hotspots?';
    }

    if (bulkDeletePreset.value === 'unlinked') {
        return 'Delete unlinked hotspots?';
    }

    return 'Delete selected hotspots?';
});
const bulkDeleteDialogConfirmText = computed(() => {
    if (bulkDeletePreset.value === 'mismatch') {
        return 'Delete mismatches';
    }

    if (bulkDeletePreset.value === 'unlinked') {
        return 'Delete unlinked';
    }

    return 'Delete selected';
});
const bulkDeleteDialogDescription = computed(() => {
    if (bulkDeletePreset.value === 'mismatch') {
        return `Delete ${selectedHotspotCount.value} mismatch hotspot(s)? Linked references will be detached where needed. This action cannot be undone.`;
    }

    if (bulkDeletePreset.value === 'unlinked') {
        return `Delete ${selectedHotspotCount.value} unlinked hotspot(s)? This action cannot be undone.`;
    }

    return `Delete ${selectedHotspotCount.value} selected hotspot(s)? Linked references will be detached where needed. This action cannot be undone.`;
});
const hotspotStats = computed(() => {
    const stats = {
        paired: 0,
        outbound: 0,
        inbound: 0,
        mismatch: 0,
        none: 0,
    };

    for (const hotspot of currentHotspots.value) {
        const state = hotspotLinkState(hotspot);
        stats[state] += 1;
    }

    return stats;
});
const selectedPageIndex = computed(() =>
    props.edition_pages.findIndex(
        (item) => String(item.id) === selectedPageId.value,
    ),
);
const hasPreviousPage = computed(() => selectedPageIndex.value > 0);
const hasNextPage = computed(
    () =>
        selectedPageIndex.value >= 0 &&
        selectedPageIndex.value < props.edition_pages.length - 1,
);
const editionLabel = computed(() => {
    if (props.edition !== null && typeof props.edition.name === 'string' && props.edition.name.trim() !== '') {
        return props.edition.name.trim();
    }

    if (props.edition !== null) {
        return `Edition ${props.edition.id}`;
    }

    return 'Edition';
});
const manageHref = computed<string | undefined>(() => {
    if (props.edition === null) {
        return undefined;
    }

    return `/admin/editions/manage?date=${props.edition.edition_date}&edition_id=${props.edition.id}`;
});
const publishHref = computed<string | undefined>(() => {
    if (props.edition === null) {
        return undefined;
    }

    return `/admin/editions/publish?date=${props.edition.edition_date}&edition_id=${props.edition.id}`;
});
const mappingHref = computed<string | undefined>(() => {
    if (selectedPageId.value === '') {
        return '/admin/hotspots';
    }

    return `/admin/hotspots?page_id=${selectedPageId.value}`;
});
const hasActiveListControls = computed(
    () =>
        hotspotSearch.value.trim() !== '' ||
        hotspotStateFilter.value !== 'all' ||
        hotspotSort.value !== 'id_asc',
);
const areaEditTargetHotspot = computed<Hotspot | null>(() => {
    if (areaEditHotspotId.value === null) {
        return null;
    }

    return (
        currentHotspots.value.find(
            (hotspot) => hotspot.id === areaEditHotspotId.value,
        ) ?? null
    );
});
const areaEditDisplayRect = computed<NormalizedRect | null>(() => {
    if (areaEditTargetHotspot.value === null) {
        return null;
    }

    if (
        activeAreaTransformHandle.value !== null &&
        areaTransformPreviewRect.value !== null
    ) {
        return areaTransformPreviewRect.value;
    }

    if (areaEditRect.value !== null) {
        return areaEditRect.value;
    }

    return {
        x: areaEditTargetHotspot.value.x,
        y: areaEditTargetHotspot.value.y,
        w: areaEditTargetHotspot.value.w,
        h: areaEditTargetHotspot.value.h,
    };
});

watch(
    () => props.selected_page_id,
    (value) => {
        selectedPageId.value = value !== null ? String(value) : '';
        draftRect.value = null;
        areaEditRect.value = null;
        areaTransformPreviewRect.value = null;
        drawing.value = false;
        drawingStart.value = null;
        areaEditHotspotId.value = null;
        resetAreaTransformState();
        selectedHotspotIds.value = [];
        bulkDeleteDialogOpen.value = false;
        discardDialogOpen.value = false;
        pendingPageSelectionId.value = null;
        void nextTick(() => {
            window.requestAnimationFrame(updateImageDimensions);
        });
    },
);

watch(
    () => imageSource.value,
    () => {
        void nextTick(() => {
            window.requestAnimationFrame(updateImageDimensions);
        });
    },
);

watch(
    () => props.focus_hotspot_id,
    (value) => {
        pendingFocusHotspotId.value = value;
        tryOpenFocusedHotspot();
    },
    { immediate: true },
);

watch(
    () => hotspotForm.target_page_no,
    () => {
        if (hotspotForm.target_hotspot_id === null) {
            return;
        }

        const stillAvailable = availableTargetHotspots.value.some(
            (hotspot) => hotspot.id === hotspotForm.target_hotspot_id,
        );

        if (!stillAvailable) {
            hotspotForm.target_hotspot_id = null;
        }
    },
);

watch(currentHotspots, () => {
    tryOpenFocusedHotspot();

    const existingIds = new Set(
        currentHotspots.value.map((hotspot) => hotspot.id),
    );
    const nextSelection = selectedHotspotIds.value.filter((hotspotId) =>
        existingIds.has(hotspotId),
    );

    if (nextSelection.length !== selectedHotspotIds.value.length) {
        selectedHotspotIds.value = nextSelection;
    }

    if (nextSelection.length === 0) {
        bulkDeleteDialogOpen.value = false;
    }

    if (
        areaEditHotspotId.value !== null &&
        !currentHotspots.value.some(
            (hotspot) => hotspot.id === areaEditHotspotId.value,
        )
    ) {
        areaEditHotspotId.value = null;
        resetAreaTransformState();
        draftRect.value = null;
        areaEditRect.value = null;
        areaTransformPreviewRect.value = null;
    }
});

watch(editingHotspotId, (value) => {
    activeHotspotId.value = value;
});

watch(activeHotspotId, async (value) => {
    if (value === null) {
        return;
    }

    await nextTick();
    hotspotRowRefs
        .get(value)
        ?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
});

onMounted(() => {
    window.addEventListener('resize', updateImageDimensions);
    window.addEventListener('keydown', onWindowKeydown);
    window.addEventListener('mousemove', onWindowMouseMove);
    window.addEventListener('mouseup', onWindowMouseUp);
    window.addEventListener('beforeunload', onBeforeWindowUnload);
    updateImageDimensions();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateImageDimensions);
    window.removeEventListener('keydown', onWindowKeydown);
    window.removeEventListener('mousemove', onWindowMouseMove);
    window.removeEventListener('mouseup', onWindowMouseUp);
    window.removeEventListener('beforeunload', onBeforeWindowUnload);
});

function clamp(value: number, min = 0, max = 1): number {
    return Math.min(Math.max(value, min), max);
}

function updateImageDimensions(): void {
    const image = imageRef.value;

    if (image === null) {
        imageDimensions.value = { width: 0, height: 0 };
        imageNaturalDimensions.value = { width: 0, height: 0 };
        return;
    }

    const rect = image.getBoundingClientRect();
    imageDimensions.value = {
        width: Math.max(0, rect.width),
        height: Math.max(0, rect.height),
    };
    imageNaturalDimensions.value = {
        width: Math.max(0, image.naturalWidth),
        height: Math.max(0, image.naturalHeight),
    };
}

function toNormalizedPoint(
    clientX: number,
    clientY: number,
): NormalizedPoint | null {
    const image = imageRef.value;

    if (image === null) {
        return null;
    }

    const rect = image.getBoundingClientRect();

    if (rect.width <= 0 || rect.height <= 0) {
        return null;
    }

    return {
        x: clamp((clientX - rect.left) / rect.width),
        y: clamp((clientY - rect.top) / rect.height),
    };
}

function startDrawing(clientX: number, clientY: number): void {
    if (!hasPage.value || !drawModeEnabled.value) {
        return;
    }

    const point = toNormalizedPoint(clientX, clientY);

    if (point === null) {
        return;
    }

    drawing.value = true;
    drawingStart.value = point;
    draftRect.value = {
        x: point.x,
        y: point.y,
        w: 0,
        h: 0,
    };
}

function continueDrawing(clientX: number, clientY: number): void {
    if (!drawing.value || drawingStart.value === null) {
        return;
    }

    const point = toNormalizedPoint(clientX, clientY);

    if (point === null) {
        return;
    }

    const x = Math.min(drawingStart.value.x, point.x);
    const y = Math.min(drawingStart.value.y, point.y);
    const w = Math.abs(point.x - drawingStart.value.x);
    const h = Math.abs(point.y - drawingStart.value.y);

    draftRect.value = {
        x: clamp(x),
        y: clamp(y),
        w: clamp(w),
        h: clamp(h),
    };
}

function endDrawing(): void {
    if (!drawing.value) {
        return;
    }

    drawing.value = false;
    drawingStart.value = null;

    if (draftRect.value === null) {
        return;
    }

    if (draftRect.value.w <= 0.005 || draftRect.value.h <= 0.005) {
        draftRect.value = null;
        return;
    }

    const completedRect = draftRect.value;

    if (areaEditHotspotId.value !== null) {
        const hotspot = currentHotspots.value.find(
            (item) => item.id === areaEditHotspotId.value,
        );
        areaEditHotspotId.value = null;

        if (hotspot !== undefined) {
            openEditDialog(hotspot);
            hotspotForm.x = completedRect.x;
            hotspotForm.y = completedRect.y;
            hotspotForm.w = completedRect.w;
            hotspotForm.h = completedRect.h;
        }

        draftRect.value = null;
        return;
    }

    openCreateDialog(completedRect);
    draftRect.value = null;
}

function parseTouch(event: TouchEvent): { x: number; y: number } | null {
    const touch = event.touches[0] ?? event.changedTouches[0];

    if (touch === undefined) {
        return null;
    }

    return {
        x: touch.clientX,
        y: touch.clientY,
    };
}

function onMouseDown(event: MouseEvent): void {
    if (event.button !== 0) {
        return;
    }

    event.preventDefault();
    startDrawing(event.clientX, event.clientY);
}

function onMouseMove(event: MouseEvent): void {
    if (activeAreaTransformHandle.value !== null) {
        event.preventDefault();
        continueAreaTransform(event.clientX, event.clientY);
        return;
    }

    if (!drawing.value) {
        return;
    }

    event.preventDefault();
    continueDrawing(event.clientX, event.clientY);
}

function onMouseUp(event: MouseEvent): void {
    if (activeAreaTransformHandle.value !== null && event.button === 0) {
        event.preventDefault();
        finishAreaTransform();
        return;
    }

    if (!drawing.value) {
        return;
    }

    event.preventDefault();
    endDrawing();
}

function onWindowMouseMove(event: MouseEvent): void {
    if (activeAreaTransformHandle.value !== null) {
        continueAreaTransform(event.clientX, event.clientY);
        return;
    }

    if (!drawing.value) {
        return;
    }

    continueDrawing(event.clientX, event.clientY);
}

function onWindowMouseUp(event: MouseEvent): void {
    if (activeAreaTransformHandle.value !== null && event.button === 0) {
        finishAreaTransform();
        return;
    }

    if (!drawing.value || event.button !== 0) {
        return;
    }

    endDrawing();
}

function onTouchStart(event: TouchEvent): void {
    const point = parseTouch(event);

    if (point === null) {
        return;
    }

    startDrawing(point.x, point.y);
}

function onTouchMove(event: TouchEvent): void {
    const point = parseTouch(event);

    if (point === null) {
        return;
    }

    if (activeAreaTransformHandle.value !== null) {
        continueAreaTransform(point.x, point.y);
        return;
    }

    continueDrawing(point.x, point.y);
}

function onTouchEnd(): void {
    if (activeAreaTransformHandle.value !== null) {
        finishAreaTransform();
        return;
    }

    endDrawing();
}

function hotspotToStyle(rect: NormalizedRect): Record<string, string> {
    return {
        left: `${rect.x * imageDimensions.value.width}px`,
        top: `${rect.y * imageDimensions.value.height}px`,
        width: `${rect.w * imageDimensions.value.width}px`,
        height: `${rect.h * imageDimensions.value.height}px`,
    };
}

function resetAreaTransformState(): void {
    activeAreaTransformHandle.value = null;
    areaTransformStartPoint.value = null;
    areaTransformStartRect.value = null;
    areaTransformPreviewRect.value = null;
}

function areaResizeHandleClass(handle: AreaResizeHandle): string {
    if (handle === 'n' || handle === 's') {
        return 'cursor-ns-resize';
    }

    if (handle === 'e' || handle === 'w') {
        return 'cursor-ew-resize';
    }

    if (handle === 'nw' || handle === 'se') {
        return 'cursor-nwse-resize';
    }

    return 'cursor-nesw-resize';
}

function areaResizeHandleStyle(
    handle: AreaResizeHandle,
): Record<string, string> {
    if (handle === 'n') {
        return { top: '-6px', left: '50%', transform: 'translate(-50%, -50%)' };
    }

    if (handle === 's') {
        return {
            bottom: '-6px',
            left: '50%',
            transform: 'translate(-50%, 50%)',
        };
    }

    if (handle === 'e') {
        return { top: '50%', right: '-6px', transform: 'translate(50%, -50%)' };
    }

    if (handle === 'w') {
        return { top: '50%', left: '-6px', transform: 'translate(-50%, -50%)' };
    }

    if (handle === 'nw') {
        return {
            top: '-6px',
            left: '-6px',
            transform: 'translate(-50%, -50%)',
        };
    }

    if (handle === 'ne') {
        return {
            top: '-6px',
            right: '-6px',
            transform: 'translate(50%, -50%)',
        };
    }

    if (handle === 'sw') {
        return {
            bottom: '-6px',
            left: '-6px',
            transform: 'translate(-50%, 50%)',
        };
    }

    return { bottom: '-6px', right: '-6px', transform: 'translate(50%, 50%)' };
}

function startAreaTransform(
    handle: AreaTransformHandle,
    clientX: number,
    clientY: number,
): void {
    const editRect = areaEditDisplayRect.value;

    if (editRect === null) {
        return;
    }

    const point = toNormalizedPoint(clientX, clientY);

    if (point === null) {
        return;
    }

    activeAreaTransformHandle.value = handle;
    areaTransformStartPoint.value = point;
    areaTransformStartRect.value = {
        x: editRect.x,
        y: editRect.y,
        w: editRect.w,
        h: editRect.h,
    };
    areaTransformPreviewRect.value = {
        x: editRect.x,
        y: editRect.y,
        w: editRect.w,
        h: editRect.h,
    };
}

function onAreaTransformMouseDown(
    handle: AreaTransformHandle,
    event: MouseEvent,
): void {
    if (event.button !== 0) {
        return;
    }

    event.preventDefault();
    startAreaTransform(handle, event.clientX, event.clientY);
}

function onAreaTransformTouchStart(
    handle: AreaTransformHandle,
    event: TouchEvent,
): void {
    const point = parseTouch(event);

    if (point === null) {
        return;
    }

    event.preventDefault();
    startAreaTransform(handle, point.x, point.y);
}

function onHotspotOverlayMouseDown(hotspot: Hotspot, event: MouseEvent): void {
    if (event.button !== 0) {
        return;
    }

    event.preventDefault();

    if (!drawModeEnabled.value) {
        drawModeEnabled.value = true;
    }

    if (areaEditHotspotId.value !== hotspot.id) {
        startAreaEditSelection(hotspot);
    }

    startAreaTransform('move', event.clientX, event.clientY);
}

function onHotspotOverlayTouchStart(hotspot: Hotspot, event: TouchEvent): void {
    const point = parseTouch(event);

    if (point === null) {
        return;
    }

    event.preventDefault();

    if (!drawModeEnabled.value) {
        drawModeEnabled.value = true;
    }

    if (areaEditHotspotId.value !== hotspot.id) {
        startAreaEditSelection(hotspot);
    }

    startAreaTransform('move', point.x, point.y);
}

function continueAreaTransform(clientX: number, clientY: number): void {
    if (
        activeAreaTransformHandle.value === null ||
        areaTransformStartPoint.value === null ||
        areaTransformStartRect.value === null
    ) {
        return;
    }

    const point = toNormalizedPoint(clientX, clientY);

    if (point === null) {
        return;
    }

    const startRect = areaTransformStartRect.value;
    const handle = activeAreaTransformHandle.value;
    const deltaX = point.x - areaTransformStartPoint.value.x;
    const deltaY = point.y - areaTransformStartPoint.value.y;
    const minSize = 0.005;

    if (handle === 'move') {
        const clampedX = clamp(
            startRect.x + deltaX,
            0,
            Math.max(0, 1 - startRect.w),
        );
        const clampedY = clamp(
            startRect.y + deltaY,
            0,
            Math.max(0, 1 - startRect.h),
        );

        areaTransformPreviewRect.value = {
            x: clampedX,
            y: clampedY,
            w: startRect.w,
            h: startRect.h,
        };

        return;
    }

    let left = startRect.x;
    let top = startRect.y;
    let right = startRect.x + startRect.w;
    let bottom = startRect.y + startRect.h;

    if (handle.includes('w')) {
        left = clamp(left + deltaX, 0, right - minSize);
    }

    if (handle.includes('e')) {
        right = clamp(right + deltaX, left + minSize, 1);
    }

    if (handle.includes('n')) {
        top = clamp(top + deltaY, 0, bottom - minSize);
    }

    if (handle.includes('s')) {
        bottom = clamp(bottom + deltaY, top + minSize, 1);
    }

    areaTransformPreviewRect.value = {
        x: left,
        y: top,
        w: right - left,
        h: bottom - top,
    };
}

function finishAreaTransform(): void {
    if (activeAreaTransformHandle.value === null) {
        return;
    }

    const startRect = areaTransformStartRect.value;
    const completedRect = areaTransformPreviewRect.value;

    resetAreaTransformState();

    if (startRect === null || completedRect === null) {
        return;
    }

    const hasMeaningfulChange =
        Math.abs(completedRect.x - startRect.x) > 0.000001 ||
        Math.abs(completedRect.y - startRect.y) > 0.000001 ||
        Math.abs(completedRect.w - startRect.w) > 0.000001 ||
        Math.abs(completedRect.h - startRect.h) > 0.000001;

    if (!hasMeaningfulChange) {
        return;
    }

    areaEditRect.value = {
        x: completedRect.x,
        y: completedRect.y,
        w: completedRect.w,
        h: completedRect.h,
    };
}

function hotspotThumbnailImageStyle(hotspot: Hotspot): Record<string, string> {
    const naturalWidth = imageNaturalDimensions.value.width;
    const naturalHeight = imageNaturalDimensions.value.height;

    if (naturalWidth <= 0 || naturalHeight <= 0) {
        return {};
    }

    const previewWidth = 96;
    const previewHeight = 64;
    const normalizedWidth = Math.max(hotspot.w, 0.000001);
    const normalizedHeight = Math.max(hotspot.h, 0.000001);
    const cropWidth = normalizedWidth * naturalWidth;
    const cropHeight = normalizedHeight * naturalHeight;

    if (cropWidth <= 0 || cropHeight <= 0) {
        return {};
    }

    const scale = Math.min(
        previewWidth / cropWidth,
        previewHeight / cropHeight,
    );
    const scaledWidth = naturalWidth * scale;
    const scaledHeight = naturalHeight * scale;
    const hotspotCenterX =
        (hotspot.x + normalizedWidth / 2) * naturalWidth * scale;
    const hotspotCenterY =
        (hotspot.y + normalizedHeight / 2) * naturalHeight * scale;
    const left = previewWidth / 2 - hotspotCenterX;
    const top = previewHeight / 2 - hotspotCenterY;

    return {
        width: `${scaledWidth}px`,
        height: `${scaledHeight}px`,
        left: `${left}px`,
        top: `${top}px`,
        maxWidth: 'none',
    };
}

function pageNoForTargetHotspotId(hotspotId: number): number | null {
    for (const [pageNo, hotspots] of targetHotspotsByPage.value.entries()) {
        const exists = hotspots.some((item) => item.id === hotspotId);

        if (exists) {
            return pageNo;
        }
    }

    return null;
}

function linkedHotspotId(hotspot: Hotspot): number | null {
    return hotspot.target_hotspot_id ?? hotspot.linked_hotspot_id ?? null;
}

function hotspotLocationInEdition(
    hotspotId: number,
): { pageNo: number; pageId: number } | null {
    for (const [pageNo, hotspots] of targetHotspotsByPage.value.entries()) {
        const exists = hotspots.some((item) => item.id === hotspotId);

        if (!exists) {
            continue;
        }

        const page = props.edition_pages.find(
            (item) => item.page_no === pageNo,
        );

        if (page !== undefined) {
            return {
                pageNo,
                pageId: page.id,
            };
        }
    }

    return null;
}

function linkedHotspotPageNo(hotspot: Hotspot): number | null {
    const linkedId = linkedHotspotId(hotspot);

    if (linkedId === null) {
        return null;
    }

    return hotspotLocationInEdition(linkedId)?.pageNo ?? null;
}

function hotspotLinkState(hotspot: Hotspot): HotspotLinkState {
    const targetHotspotId = hotspot.target_hotspot_id ?? null;
    const linkedHotspotId = hotspot.linked_hotspot_id ?? null;

    if (targetHotspotId === null && linkedHotspotId === null) {
        return 'none';
    }

    if (targetHotspotId !== null && linkedHotspotId !== null) {
        return targetHotspotId === linkedHotspotId ? 'paired' : 'mismatch';
    }

    if (targetHotspotId !== null) {
        return 'outbound';
    }

    return 'inbound';
}

function hotspotLinkLabel(state: HotspotLinkState): string {
    if (state === 'paired') {
        return 'Linked';
    }

    if (state === 'outbound') {
        return 'Outbound link';
    }

    if (state === 'inbound') {
        return 'Inbound link';
    }

    if (state === 'mismatch') {
        return 'Link mismatch';
    }

    return 'No link';
}

function hotspotLinkBadgeVariant(
    state: HotspotLinkState,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (state === 'paired') {
        return 'default';
    }

    if (state === 'outbound' || state === 'inbound') {
        return 'secondary';
    }

    if (state === 'mismatch') {
        return 'destructive';
    }

    return 'outline';
}

function hotspotOverlayClass(hotspot: Hotspot): string {
    const state = hotspotLinkState(hotspot);

    if (state === 'paired') {
        return 'border-emerald-500 bg-emerald-500/15 hover:bg-emerald-500/25';
    }

    if (state === 'outbound') {
        return 'border-sky-500 bg-sky-500/15 hover:bg-sky-500/25';
    }

    if (state === 'inbound') {
        return 'border-amber-500 bg-amber-500/15 hover:bg-amber-500/25';
    }

    if (state === 'mismatch') {
        return 'border-rose-500 bg-rose-500/15 hover:bg-rose-500/25';
    }

    return 'border-slate-400 bg-slate-200/25 hover:bg-slate-200/45';
}

function hotspotToolbarIsInside(hotspot: Hotspot): boolean {
    return hotspot.y < 0.04;
}

function hotspotOverlayChipText(state: HotspotLinkState): string {
    if (state === 'paired') {
        return 'L';
    }

    if (state === 'outbound') {
        return 'OUT';
    }

    if (state === 'inbound') {
        return 'IN';
    }

    if (state === 'mismatch') {
        return 'ERR';
    }

    return '';
}

function hotspotTargetPageLabel(targetPageNo: number | null): string {
    if (targetPageNo === null) {
        return 'No target page';
    }

    return `Page ${targetPageNo}`;
}

function hotspotOverlayTitle(hotspot: Hotspot): string {
    const state = hotspotLinkState(hotspot);
    const linkedId =
        hotspot.target_hotspot_id ?? hotspot.linked_hotspot_id ?? null;
    const targetPageLabel = hotspot.target_page_no === null
        ? 'No target page'
        : `Target page ${hotspot.target_page_no}`;

    if (linkedId !== null) {
        return `${targetPageLabel} • ${hotspotLinkLabel(state)} (#${linkedId})`;
    }

    return `${targetPageLabel} • ${hotspotLinkLabel(state)}`;
}

function normalizedNumberForSnapshot(value: number): number {
    return Number.parseFloat(value.toFixed(6));
}

function serializeHotspotFormState(): string {
    return JSON.stringify({
        relation_kind: hotspotForm.relation_kind,
        target_page_no: hotspotForm.target_page_no,
        target_hotspot_id: hotspotForm.target_hotspot_id,
        x: normalizedNumberForSnapshot(hotspotForm.x),
        y: normalizedNumberForSnapshot(hotspotForm.y),
        w: normalizedNumberForSnapshot(hotspotForm.w),
        h: normalizedNumberForSnapshot(hotspotForm.h),
        label: hotspotForm.label.trim(),
    });
}

function captureHotspotDialogBaseline(): void {
    hotspotDialogBaseline.value = serializeHotspotFormState();
}

function resetHotspotForm(): void {
    hotspotForm.page_id = currentPage.value?.id ?? 0;
    hotspotForm.relation_kind = 'next';
    hotspotForm.target_page_no = null;
    hotspotForm.target_hotspot_id = null;
    hotspotForm.x = 0;
    hotspotForm.y = 0;
    hotspotForm.w = 0;
    hotspotForm.h = 0;
    hotspotForm.label = '';
    hotspotForm.clearErrors();
}

function openCreateDialog(rect: NormalizedRect): void {
    if (currentPage.value === null) {
        return;
    }

    resetHotspotForm();
    areaEditHotspotId.value = null;
    areaEditRect.value = null;
    editingHotspotId.value = null;
    activeHotspotId.value = null;
    hotspotForm.x = rect.x;
    hotspotForm.y = rect.y;
    hotspotForm.w = rect.w;
    hotspotForm.h = rect.h;
    isDialogOpen.value = true;
    captureHotspotDialogBaseline();
}

function openEditDialog(hotspot: Hotspot): void {
    if (currentPage.value === null) {
        return;
    }

    if (areaEditHotspotId.value !== hotspot.id) {
        areaEditRect.value = null;
    }

    resetHotspotForm();
    areaEditHotspotId.value = hotspot.id;
    editingHotspotId.value = hotspot.id;
    activeHotspotId.value = hotspot.id;
    hotspotForm.relation_kind = hotspot.relation_kind;
    hotspotForm.target_page_no = hotspot.target_page_no ?? null;

    const connectedHotspotId =
        hotspot.target_hotspot_id ?? hotspot.linked_hotspot_id ?? null;
    hotspotForm.target_hotspot_id = connectedHotspotId;

    if (connectedHotspotId !== null) {
        const connectedPageNo = pageNoForTargetHotspotId(connectedHotspotId);

        if (connectedPageNo !== null) {
            hotspotForm.target_page_no = connectedPageNo;
        }
    }

    hotspotForm.x = areaEditRect.value?.x ?? hotspot.x;
    hotspotForm.y = areaEditRect.value?.y ?? hotspot.y;
    hotspotForm.w = areaEditRect.value?.w ?? hotspot.w;
    hotspotForm.h = areaEditRect.value?.h ?? hotspot.h;
    hotspotForm.label = hotspot.label ?? '';
    isDialogOpen.value = true;
    captureHotspotDialogBaseline();
}

function tryOpenFocusedHotspot(): void {
    const focusHotspotId = pendingFocusHotspotId.value;

    if (focusHotspotId === null) {
        return;
    }

    const hotspot = currentHotspots.value.find(
        (item) => item.id === focusHotspotId,
    );

    if (hotspot === undefined) {
        return;
    }

    openEditDialog(hotspot);
    pendingFocusHotspotId.value = null;
}

function finalizeDialogClose(): void {
    isDialogOpen.value = false;
    editingHotspotId.value = null;
    activeHotspotId.value = null;
    discardDialogOpen.value = false;
    hotspotDialogBaseline.value = null;
    hotspotForm.clearErrors();
}

function requestDialogClose(): void {
    if (hotspotForm.processing) {
        return;
    }

    if (hasDialogUnsavedChanges.value) {
        discardDialogOpen.value = true;
        return;
    }

    finalizeDialogClose();
}

function confirmDiscardDialogChanges(): void {
    const pendingTargetPageId = pendingPageSelectionId.value;

    finalizeDialogClose();

    if (pendingTargetPageId !== null) {
        pendingPageSelectionId.value = null;
        draftRect.value = null;
        areaEditRect.value = null;
        drawing.value = false;
        drawingStart.value = null;
        areaEditHotspotId.value = null;
        resetAreaTransformState();
        navigateToPageSelection(pendingTargetPageId);
    }
}

function onDiscardDialogOpenChange(open: boolean): void {
    discardDialogOpen.value = open;

    if (!open) {
        pendingPageSelectionId.value = null;
    }
}

function onHotspotDialogOpenChange(open: boolean): void {
    if (open) {
        isDialogOpen.value = true;
        return;
    }

    requestDialogClose();
}

function openLinkedHotspot(hotspot: Hotspot): void {
    const linkedId = linkedHotspotId(hotspot);

    if (linkedId === null) {
        return;
    }

    const samePageTarget = currentHotspots.value.find(
        (item) => item.id === linkedId,
    );

    if (samePageTarget !== undefined) {
        openEditDialog(samePageTarget);
        return;
    }

    const linkedLocation = hotspotLocationInEdition(linkedId);

    if (linkedLocation === null) {
        return;
    }

    router.get(
        '/admin/hotspots',
        {
            page_id: linkedLocation.pageId,
            focus_hotspot_id: linkedId,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function saveHotspot(): void {
    if (currentPage.value === null) {
        return;
    }

    hotspotForm.page_id = currentPage.value.id;

    const transformedForm = hotspotForm.transform((data) => ({
        ...data,
        target_hotspot_id:
            data.target_page_no === null ? null : data.target_hotspot_id,
        label: data.label.trim() === '' ? null : data.label.trim(),
    }));

    if (editingHotspotId.value === null) {
        transformedForm.post('/admin/hotspots', {
            preserveScroll: true,
            forceFormData: false,
            onSuccess: () => {
                areaEditRect.value = null;
                finalizeDialogClose();
            },
        });

        return;
    }

    transformedForm.put(`/admin/hotspots/${editingHotspotId.value}`, {
        preserveScroll: true,
        forceFormData: false,
        onSuccess: () => {
            areaEditRect.value = null;
            finalizeDialogClose();
        },
    });
}

function quickSaveHotspotArea(hotspot: Hotspot): void {
    if (
        currentPage.value === null ||
        areaEditRect.value === null ||
        savingAreaHotspotId.value !== null
    ) {
        return;
    }

    const rect = areaEditRect.value;
    savingAreaHotspotId.value = hotspot.id;

    const connectedHotspotId =
        hotspot.target_hotspot_id ?? hotspot.linked_hotspot_id ?? null;

    router.put(
        `/admin/hotspots/${hotspot.id}`,
        {
            page_id: currentPage.value.id,
            relation_kind: hotspot.relation_kind,
            target_page_no: hotspot.target_page_no,
            target_hotspot_id: connectedHotspotId,
            x: rect.x,
            y: rect.y,
            w: rect.w,
            h: rect.h,
            label: hotspot.label ?? '',
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                areaEditRect.value = null;
                savingAreaHotspotId.value = null;
            },
            onError: () => {
                savingAreaHotspotId.value = null;
            },
        },
    );
}

function deleteHotspot(hotspot: Hotspot): void {
    pendingDeleteHotspot.value = hotspot;
    deleteDialogOpen.value = true;
}

function confirmDeleteHotspot(): void {
    if (pendingDeleteHotspot.value === null) {
        return;
    }

    const hotspotToDelete = pendingDeleteHotspot.value;

    router.delete(`/admin/hotspots/${hotspotToDelete.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialogOpen.value = false;
            pendingDeleteHotspot.value = null;
        },
    });
}

function onDeleteDialogOpenChange(open: boolean): void {
    deleteDialogOpen.value = open;

    if (!open) {
        pendingDeleteHotspot.value = null;
    }
}

watch(deleteDialogOpen, (isOpen) => {
    if (isOpen) {
        return;
    }

    pendingDeleteHotspot.value = null;
});

watch(currentHotspots, (value) => {
    if (pendingDeleteHotspot.value === null) {
        return;
    }

    const stillExists = value.some(
        (hotspot) => hotspot.id === pendingDeleteHotspot.value?.id,
    );

    if (stillExists) {
        return;
    }

    pendingDeleteHotspot.value = null;
    deleteDialogOpen.value = false;
});

function navigateToPageSelection(normalizedValue: string): void {
    selectedPageId.value = normalizedValue;
    const pageId = Number.parseInt(normalizedValue, 10);

    if (Number.isNaN(pageId) || pageId <= 0) {
        return;
    }

    router.get(
        '/admin/hotspots',
        {
            page_id: pageId,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function onPageSelection(value: unknown): void {
    if (value === null || value === undefined) {
        return;
    }

    const normalizedValue = String(value);

    if (isDialogOpen.value) {
        if (hasDialogUnsavedChanges.value) {
            pendingPageSelectionId.value = normalizedValue;
            discardDialogOpen.value = true;
            return;
        }

        finalizeDialogClose();
    }

    draftRect.value = null;
    drawing.value = false;
    drawingStart.value = null;
    areaEditHotspotId.value = null;
    areaEditRect.value = null;
    resetAreaTransformState();
    pendingPageSelectionId.value = null;
    navigateToPageSelection(normalizedValue);
}

function jumpToAdjacentPage(direction: 'previous' | 'next'): void {
    const index = selectedPageIndex.value;

    if (index < 0) {
        return;
    }

    const nextIndex = direction === 'previous' ? index - 1 : index + 1;
    const targetPage = props.edition_pages[nextIndex];

    if (targetPage === undefined) {
        return;
    }

    onPageSelection(String(targetPage.id));
}

function goToPreviousPage(): void {
    jumpToAdjacentPage('previous');
}

function goToNextPage(): void {
    jumpToAdjacentPage('next');
}

function toggleDrawMode(): void {
    drawModeEnabled.value = !drawModeEnabled.value;

    if (!drawModeEnabled.value) {
        areaEditHotspotId.value = null;
        areaEditRect.value = null;
        resetAreaTransformState();
        draftRect.value = null;
    }
}

function startAreaEditSelection(hotspot: Hotspot): void {
    areaEditHotspotId.value = hotspot.id;
    areaEditRect.value = null;
    activeHotspotId.value = hotspot.id;
    drawModeEnabled.value = true;
    resetAreaTransformState();
    draftRect.value = null;
    drawing.value = false;
    drawingStart.value = null;
}

function cancelAreaEditSelection(): void {
    areaEditHotspotId.value = null;
    areaEditRect.value = null;
    resetAreaTransformState();
    draftRect.value = null;
    drawing.value = false;
    drawingStart.value = null;
}

function requestAreaEditFromDialog(): void {
    if (editingHotspotId.value === null) {
        return;
    }

    const hotspot = currentHotspots.value.find(
        (item) => item.id === editingHotspotId.value,
    );

    if (hotspot === undefined) {
        return;
    }

    finalizeDialogClose();
    startAreaEditSelection(hotspot);
}

function focusHotspotOnCanvas(hotspot: Hotspot): void {
    const viewport = canvasViewportRef.value;

    if (
        viewport === null ||
        imageDimensions.value.width <= 0 ||
        imageDimensions.value.height <= 0
    ) {
        return;
    }

    const left = hotspot.x * imageDimensions.value.width;
    const top = hotspot.y * imageDimensions.value.height;
    const width = hotspot.w * imageDimensions.value.width;
    const height = hotspot.h * imageDimensions.value.height;
    const targetLeft = Math.max(0, left + width / 2 - viewport.clientWidth / 2);
    const targetTop = Math.max(0, top + height / 2 - viewport.clientHeight / 2);

    viewport.scrollTo({
        left: targetLeft,
        top: targetTop,
        behavior: 'smooth',
    });
}

function setHotspotStateFilter(filter: HotspotListFilter): void {
    hotspotStateFilter.value = filter;
}

function hotspotCountForFilter(filter: HotspotListFilter): number {
    if (filter === 'all') {
        return currentHotspots.value.length;
    }

    return hotspotStats.value[filter];
}

function handleHotspotRowHover(hotspotId: number): void {
    activeHotspotId.value = hotspotId;
}

function handleHotspotRowLeave(hotspotId: number): void {
    if (editingHotspotId.value === hotspotId) {
        return;
    }

    activeHotspotId.value = null;
}

function setHotspotRowRef(hotspotId: number, element: unknown): void {
    if (element instanceof HTMLElement) {
        hotspotRowRefs.set(hotspotId, element);
        return;
    }

    hotspotRowRefs.delete(hotspotId);
}

function resetHotspotListControls(): void {
    hotspotSearch.value = '';
    hotspotStateFilter.value = 'all';
    hotspotSort.value = 'id_asc';
}

function toggleHotspotSelection(hotspotId: number): void {
    const selection = new Set(selectedHotspotIds.value);

    if (selection.has(hotspotId)) {
        selection.delete(hotspotId);
    } else {
        selection.add(hotspotId);
    }

    selectedHotspotIds.value = [...selection].sort(
        (left, right) => left - right,
    );
}

function replaceSelectedHotspots(hotspotIds: number[]): void {
    const normalizedSelection = [...new Set(hotspotIds)]
        .filter((hotspotId) => Number.isInteger(hotspotId) && hotspotId > 0)
        .sort((left, right) => left - right);

    selectedHotspotIds.value = normalizedSelection;
}

function clearSelectedHotspots(): void {
    replaceSelectedHotspots([]);
}

function selectMismatchHotspots(): void {
    replaceSelectedHotspots(mismatchHotspotIds.value);
}

function selectUnlinkedHotspots(): void {
    replaceSelectedHotspots(unlinkedHotspotIds.value);
}

function requestBulkDeletePreset(preset: BulkDeletePreset): void {
    if (preset === 'mismatch') {
        replaceSelectedHotspots(mismatchHotspotIds.value);
    } else if (preset === 'unlinked') {
        replaceSelectedHotspots(unlinkedHotspotIds.value);
    }

    requestBulkDeleteSelectedHotspots(preset);
}

function toggleSelectVisibleHotspots(): void {
    const visibleIds = filteredHotspots.value.map((hotspot) => hotspot.id);

    if (visibleIds.length === 0) {
        return;
    }

    const selection = new Set(selectedHotspotIds.value);

    if (areAllVisibleHotspotsSelected.value) {
        for (const hotspotId of visibleIds) {
            selection.delete(hotspotId);
        }
    } else {
        for (const hotspotId of visibleIds) {
            selection.add(hotspotId);
        }
    }

    selectedHotspotIds.value = [...selection].sort(
        (left, right) => left - right,
    );
}

function requestBulkDeleteSelectedHotspots(
    preset: BulkDeletePreset = 'manual',
): void {
    if (
        !hasSelectedHotspots.value ||
        currentPage.value === null ||
        bulkDeleteForm.processing
    ) {
        return;
    }

    bulkDeletePreset.value = preset;
    bulkDeleteDialogOpen.value = true;
}

function confirmBulkDeleteSelectedHotspots(): void {
    if (!hasSelectedHotspots.value || currentPage.value === null) {
        return;
    }

    bulkDeleteForm.page_id = currentPage.value.id;
    bulkDeleteForm.hotspot_ids = [...selectedHotspotIds.value];

    bulkDeleteForm.post('/admin/hotspots/bulk-delete', {
        preserveScroll: true,
        forceFormData: false,
        onSuccess: () => {
            bulkDeleteDialogOpen.value = false;
            bulkDeletePreset.value = 'manual';
            clearSelectedHotspots();
        },
    });
}

function onBulkDeleteDialogOpenChange(open: boolean): void {
    bulkDeleteDialogOpen.value = open;

    if (!open) {
        bulkDeletePreset.value = 'manual';
    }
}

function isEditableTarget(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    const tagName = target.tagName.toLowerCase();
    return (
        tagName === 'input' ||
        tagName === 'textarea' ||
        target.isContentEditable
    );
}

function onWindowKeydown(event: KeyboardEvent): void {
    if (
        isDialogOpen.value ||
        !hasPage.value ||
        isEditableTarget(event.target)
    ) {
        return;
    }

    if (event.key === 'Escape' && activeAreaTransformHandle.value !== null) {
        event.preventDefault();
        resetAreaTransformState();
        return;
    }

    if (event.key === 'Escape' && areaEditHotspotId.value !== null) {
        event.preventDefault();
        cancelAreaEditSelection();
        return;
    }

    if (event.key.toLowerCase() === 'd') {
        event.preventDefault();
        toggleDrawMode();
        return;
    }

    if (event.key.toLowerCase() === 'e' && activeHotspotId.value !== null) {
        const hotspot = currentHotspots.value.find(
            (item) => item.id === activeHotspotId.value,
        );

        if (hotspot !== undefined) {
            event.preventDefault();
            startAreaEditSelection(hotspot);
            return;
        }
    }

    if (event.key.toLowerCase() === 'f' && activeHotspotId.value !== null) {
        const hotspot = currentHotspots.value.find(
            (item) => item.id === activeHotspotId.value,
        );

        if (hotspot !== undefined) {
            event.preventDefault();
            focusHotspotOnCanvas(hotspot);
            return;
        }
    }

    if (event.key === 'ArrowLeft' && hasPreviousPage.value) {
        event.preventDefault();
        goToPreviousPage();
    }

    if (event.key === 'ArrowRight' && hasNextPage.value) {
        event.preventDefault();
        goToNextPage();
    }
}

function onBeforeWindowUnload(event: BeforeUnloadEvent): void {
    if (!hasUnsavedWork.value) {
        return;
    }

    event.preventDefault();
    event.returnValue = '';
}
</script>

<template>
    <Head title="Page Hotspots" />
    <EpAdminLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-4 p-4">
            <div class="space-y-1">
                <h1 class="text-xl font-semibold">Mapping Editor</h1>
                <p class="text-sm text-muted-foreground">
                    Draw rectangles over a page and connect them to another page
                    or a specific hotspot.
                </p>
            </div>

            <Card v-if="hasPage" class="overflow-hidden">
                <CardHeader class="space-y-3 border-b bg-muted/20">
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="space-y-1">
                            <CardTitle
                                class="flex flex-wrap items-center gap-2"
                            >
                                <span>{{ editionLabel }}</span>
                                <Badge variant="outline">{{ props.edition?.edition_date }}</Badge>
                                <Badge
                                    :variant="
                                        props.edition?.status === 'published'
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{ props.edition?.status }}
                                </Badge>
                            </CardTitle>
                            <p class="text-sm text-muted-foreground">
                                Draw a rectangle to create a hotspot, then
                                connect it to a page or a specific linked
                                hotspot.
                            </p>
                        </div>
                        <Badge variant="outline"
                            >Page {{ currentPage?.page_no }}</Badge
                        >
                    </div>
                </CardHeader>
                <CardContent class="space-y-4 p-4">
                    <EditionContextBar
                        v-if="props.edition"
                        :edition-id="props.edition.id"
                        :edition-date="props.edition.edition_date"
                        :edition-name="props.edition.name"
                        :status="props.edition.status"
                        :pages-count="props.edition.pages_count"
                        :published-at="props.edition.published_at"
                        :current-page-no="currentPage?.page_no ?? null"
                        :manage-href="manageHref"
                        :publish-href="publishHref"
                        :mapping-href="mappingHref"
                    />

                    <div
                        class="grid gap-3 lg:grid-cols-[minmax(0,1.4fr)_repeat(3,minmax(0,1fr))]"
                    >
                        <div
                            class="space-y-3 rounded-lg border bg-background p-3"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <p
                                    class="text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    Selected page
                                </p>
                                <p
                                    class="text-xs font-medium text-muted-foreground"
                                >
                                    Page {{ currentPage?.page_no }}
                                </p>
                            </div>
                            <Select
                                :model-value="selectedPageId"
                                @update:model-value="onPageSelection"
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select page" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="item in props.edition_pages"
                                        :key="item.id"
                                        :value="String(item.id)"
                                    >
                                        Page {{ item.page_no }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <div class="grid grid-cols-2 gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    :disabled="!hasPreviousPage"
                                    @click="goToPreviousPage"
                                >
                                    Previous page
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    :disabled="!hasNextPage"
                                    @click="goToNextPage"
                                >
                                    Next page
                                </Button>
                            </div>
                        </div>

                        <div
                            class="space-y-1 rounded-lg border bg-background p-3"
                        >
                            <p
                                class="text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                Total pages
                            </p>
                            <p class="text-2xl font-semibold">
                                {{ props.edition?.pages_count ?? 0 }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Max page {{ props.edition?.max_page_no ?? 0 }}
                            </p>
                        </div>

                        <div
                            class="space-y-1 rounded-lg border bg-background p-3"
                        >
                            <p
                                class="text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                Hotspots on this page
                            </p>
                            <p class="text-2xl font-semibold">
                                {{ currentHotspots.length }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Linked: {{ hotspotStats.paired }}
                            </p>
                        </div>

                        <div
                            class="space-y-1 rounded-lg border bg-background p-3"
                        >
                            <p
                                class="text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                Needs attention
                            </p>
                            <p class="text-2xl font-semibold">
                                {{ hotspotStats.mismatch }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Outbound {{ hotspotStats.outbound }} • Inbound
                                {{ hotspotStats.inbound }} • Unlinked
                                {{ hotspotStats.none }}
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    :disabled="mismatchHotspotIds.length === 0"
                                    @click="selectMismatchHotspots"
                                >
                                    Select mismatch
                                </Button>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    :disabled="mismatchHotspotIds.length === 0"
                                    @click="requestBulkDeletePreset('mismatch')"
                                >
                                    Delete mismatch
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    :disabled="unlinkedHotspotIds.length === 0"
                                    @click="selectUnlinkedHotspots"
                                >
                                    Select unlinked
                                </Button>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    :disabled="unlinkedHotspotIds.length === 0"
                                    @click="requestBulkDeletePreset('unlinked')"
                                >
                                    Delete unlinked
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div
                        class="grid items-stretch gap-4 xl:grid-cols-[minmax(0,1fr)_24rem]"
                    >
                        <div class="space-y-3">
                            <div
                                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-background px-3 py-2 text-xs"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <Badge variant="default"
                                        >Linked {{ hotspotStats.paired }}</Badge
                                    >
                                    <Badge variant="secondary"
                                        >Outbound / Inbound
                                        {{
                                            hotspotStats.outbound +
                                            hotspotStats.inbound
                                        }}</Badge
                                    >
                                    <Badge variant="destructive"
                                        >Mismatch
                                        {{ hotspotStats.mismatch }}</Badge
                                    >
                                    <Badge variant="outline"
                                        >No link {{ hotspotStats.none }}</Badge
                                    >
                                </div>
                                <div class="flex items-center gap-2">
                                    <Button
                                        size="sm"
                                        :variant="
                                            drawModeEnabled
                                                ? 'default'
                                                : 'outline'
                                        "
                                        @click="toggleDrawMode"
                                    >
                                        {{
                                            drawModeEnabled
                                                ? 'Draw mode on'
                                                : 'Draw mode off'
                                        }}
                                    </Button>
                                    <p class="text-muted-foreground">
                                        Shortcut: ← / → page, D draw mode, E
                                        area edit, F locate
                                    </p>
                                </div>
                            </div>
                            <div
                                v-if="areaEditTargetHotspot !== null"
                                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-blue-300 bg-blue-50 px-3 py-2 text-xs text-blue-900"
                            >
                                <div class="space-y-1">
                                    <p>
                                        Area edit mode: drag the box to move
                                        hotspot #{{ areaEditTargetHotspot.id }}
                                        or drag handles to scale in/out. Press
                                        <span class="font-semibold">Esc</span>
                                        to cancel.
                                    </p>
                                </div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="cancelAreaEditSelection"
                                >
                                    Cancel
                                </Button>
                            </div>

                            <div
                                ref="canvasViewportRef"
                                class="overflow-auto rounded-xl border bg-background p-3 shadow-sm"
                            >
                                <div class="relative w-full">
                                    <img
                                        ref="imageRef"
                                        :src="imageSource"
                                        :alt="`Page ${currentPage?.page_no}`"
                                        class="block h-auto w-full rounded-md"
                                        draggable="false"
                                        @load="updateImageDimensions"
                                    />

                                    <div
                                        v-if="
                                            imageDimensions.width > 0 &&
                                            imageDimensions.height > 0
                                        "
                                        class="pointer-events-none absolute top-0 left-0 overflow-visible"
                                        :style="overlayStyle"
                                    >
                                        <div
                                            v-for="hotspot in currentHotspots"
                                            :key="hotspot.id"
                                            class="pointer-events-auto absolute rounded-sm border-2 transition-colors"
                                            :class="[
                                                hotspotOverlayClass(hotspot),
                                                activeHotspotId === hotspot.id
                                                    ? 'z-20 ring-2 ring-primary ring-offset-1 ring-offset-background'
                                                    : 'z-10',
                                            ]"
                                            :style="hotspotToStyle(hotspot)"
                                            :title="
                                                hotspotOverlayTitle(hotspot)
                                            "
                                            @mouseenter="
                                                activeHotspotId = hotspot.id
                                            "
                                            @mouseleave="
                                                handleHotspotRowLeave(
                                                    hotspot.id,
                                                )
                                            "
                                            @mousedown.stop.prevent="
                                                onHotspotOverlayMouseDown(
                                                    hotspot,
                                                    $event,
                                                )
                                            "
                                            @touchstart.stop.prevent="
                                                onHotspotOverlayTouchStart(
                                                    hotspot,
                                                    $event,
                                                )
                                            "
                                        >
                                            <span
                                                v-if="
                                                    hotspotLinkState(
                                                        hotspot,
                                                    ) !== 'none'
                                                "
                                                class="pointer-events-none absolute -top-2 left-0 rounded bg-slate-900/85 px-1 py-0.5 text-[10px] leading-none font-semibold tracking-wide text-white uppercase"
                                            >
                                                {{
                                                    hotspotOverlayChipText(
                                                        hotspotLinkState(
                                                            hotspot,
                                                        ),
                                                    )
                                                }}
                                            </span>
                                            <div
                                                class="absolute z-30 flex items-center gap-0.5 rounded-md border bg-popover/95 p-0.5 shadow-md backdrop-blur-sm"
                                                :class="[
                                                    hotspotToolbarIsInside(
                                                        hotspot,
                                                    )
                                                        ? 'top-0.5 right-0.5'
                                                        : '-top-1 right-0 -translate-y-full',
                                                ]"
                                                @mousedown.stop
                                                @touchstart.stop
                                                @click.stop
                                            >
                                                <span
                                                    class="px-1 text-[10px] font-semibold text-muted-foreground"
                                                >
                                                    #{{ hotspot.id }}
                                                </span>
                                                <Button
                                                    size="icon-sm"
                                                    variant="ghost"
                                                    class="size-6"
                                                    title="Edit"
                                                    @click="
                                                        openEditDialog(hotspot)
                                                    "
                                                >
                                                    <Pencil class="size-3" />
                                                </Button>
                                                <Button
                                                    size="icon-sm"
                                                    variant="ghost"
                                                    class="size-6"
                                                    title="Open linked hotspot"
                                                    :disabled="
                                                        linkedHotspotId(
                                                            hotspot,
                                                        ) === null
                                                    "
                                                    @click="
                                                        openLinkedHotspot(
                                                            hotspot,
                                                        )
                                                    "
                                                >
                                                    <Link class="size-3" />
                                                </Button>
                                                <Button
                                                    size="icon-sm"
                                                    variant="ghost"
                                                    class="size-6 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                    title="Delete"
                                                    @click="
                                                        deleteHotspot(hotspot)
                                                    "
                                                >
                                                    <Trash2 class="size-3" />
                                                </Button>
                                                <Button
                                                    v-if="
                                                        areaEditHotspotId ===
                                                            hotspot.id &&
                                                        areaEditRect !== null
                                                    "
                                                    size="icon-sm"
                                                    variant="default"
                                                    class="size-6"
                                                    title="Save area"
                                                    :disabled="
                                                        savingAreaHotspotId ===
                                                        hotspot.id
                                                    "
                                                    @click="
                                                        quickSaveHotspotArea(
                                                            hotspot,
                                                        )
                                                    "
                                                >
                                                    <Save class="size-3" />
                                                </Button>
                                            </div>
                                        </div>

                                        <div
                                            v-if="
                                                areaEditTargetHotspot !==
                                                    null &&
                                                areaEditDisplayRect !== null
                                            "
                                            class="pointer-events-auto absolute rounded-sm border-2 border-dashed border-blue-700/85 bg-blue-600/10"
                                            :class="
                                                activeAreaTransformHandle !==
                                                null
                                                    ? 'cursor-grabbing'
                                                    : 'cursor-move'
                                            "
                                            :style="
                                                hotspotToStyle(
                                                    areaEditDisplayRect,
                                                )
                                            "
                                            @mousedown.stop.prevent="
                                                onAreaTransformMouseDown(
                                                    'move',
                                                    $event,
                                                )
                                            "
                                            @touchstart.stop.prevent="
                                                onAreaTransformTouchStart(
                                                    'move',
                                                    $event,
                                                )
                                            "
                                        >
                                            <button
                                                v-for="handle in areaResizeHandles"
                                                :key="`resize-handle-${handle}`"
                                                type="button"
                                                class="absolute z-10 h-3 w-3 rounded-full border border-white bg-blue-700 shadow-sm"
                                                :class="
                                                    areaResizeHandleClass(
                                                        handle,
                                                    )
                                                "
                                                :style="
                                                    areaResizeHandleStyle(
                                                        handle,
                                                    )
                                                "
                                                @mousedown.stop.prevent="
                                                    onAreaTransformMouseDown(
                                                        handle,
                                                        $event,
                                                    )
                                                "
                                                @touchstart.stop.prevent="
                                                    onAreaTransformTouchStart(
                                                        handle,
                                                        $event,
                                                    )
                                                "
                                            />
                                        </div>

                                        <div
                                            v-if="
                                                hasDraftRect &&
                                                draftRect !== null &&
                                                areaEditTargetHotspot === null
                                            "
                                            class="absolute rounded-sm border-2 border-blue-500 bg-blue-500/15"
                                            :style="hotspotToStyle(draftRect)"
                                        />
                                    </div>

                                    <div
                                        v-if="
                                            drawModeEnabled &&
                                            areaEditTargetHotspot === null &&
                                            imageDimensions.width > 0 &&
                                            imageDimensions.height > 0
                                        "
                                        class="absolute top-0 left-0 cursor-crosshair touch-none"
                                        :style="overlayStyle"
                                        @mousedown="onMouseDown"
                                        @mousemove="onMouseMove"
                                        @mouseup="onMouseUp"
                                        @touchstart.prevent="onTouchStart"
                                        @touchmove.prevent="onTouchMove"
                                        @touchend.prevent="onTouchEnd"
                                        @touchcancel.prevent="onTouchEnd"
                                    />
                                    <div
                                        v-if="
                                            !drawModeEnabled &&
                                            imageDimensions.width > 0 &&
                                            imageDimensions.height > 0
                                        "
                                        class="pointer-events-none absolute top-2 right-2 rounded bg-slate-900/80 px-2 py-1 text-[11px] font-medium text-white"
                                    >
                                        Draw mode off
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex h-full min-h-0 min-h-[24rem] flex-col overflow-hidden rounded-xl border bg-background"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3"
                            >
                                <div>
                                    <h2 class="text-sm font-semibold">
                                        Existing hotspots
                                    </h2>
                                    <p class="text-xs text-muted-foreground">
                                        {{ filteredHotspots.length }} of
                                        {{ currentHotspots.length }} shown
                                    </p>
                                </div>
                                <Badge variant="outline">{{
                                    currentHotspots.length
                                }}</Badge>
                            </div>
                            <div class="flex flex-col gap-3 border-b p-3">
                                <Input
                                    v-model="hotspotSearch"
                                    type="text"
                                    placeholder="Search by id, relation, page, label, or link"
                                />
                                <div class="space-y-2">
                                    <p
                                        class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                    >
                                        Sort
                                    </p>
                                    <Select v-model="hotspotSort">
                                        <SelectTrigger>
                                            <SelectValue
                                                placeholder="Sort hotspots"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="id_asc"
                                                >ID (oldest first)</SelectItem
                                            >
                                            <SelectItem value="id_desc"
                                                >ID (newest first)</SelectItem
                                            >
                                            <SelectItem value="target_page"
                                                >Target page</SelectItem
                                            >
                                            <SelectItem value="relation"
                                                >Relation kind</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        :class="
                                            hotspotStateFilter === 'all'
                                                ? 'border-primary bg-primary/10 text-primary'
                                                : ''
                                        "
                                        @click="setHotspotStateFilter('all')"
                                    >
                                        All {{ hotspotCountForFilter('all') }}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        :class="
                                            hotspotStateFilter === 'paired'
                                                ? 'border-primary bg-primary/10 text-primary'
                                                : ''
                                        "
                                        @click="setHotspotStateFilter('paired')"
                                    >
                                        Linked
                                        {{ hotspotCountForFilter('paired') }}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        :class="
                                            hotspotStateFilter === 'outbound'
                                                ? 'border-primary bg-primary/10 text-primary'
                                                : ''
                                        "
                                        @click="
                                            setHotspotStateFilter('outbound')
                                        "
                                    >
                                        Out
                                        {{ hotspotCountForFilter('outbound') }}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        :class="
                                            hotspotStateFilter === 'inbound'
                                                ? 'border-primary bg-primary/10 text-primary'
                                                : ''
                                        "
                                        @click="
                                            setHotspotStateFilter('inbound')
                                        "
                                    >
                                        In
                                        {{ hotspotCountForFilter('inbound') }}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        :class="
                                            hotspotStateFilter === 'mismatch'
                                                ? 'border-primary bg-primary/10 text-primary'
                                                : ''
                                        "
                                        @click="
                                            setHotspotStateFilter('mismatch')
                                        "
                                    >
                                        Mismatch
                                        {{ hotspotCountForFilter('mismatch') }}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        :class="
                                            hotspotStateFilter === 'none'
                                                ? 'border-primary bg-primary/10 text-primary'
                                                : ''
                                        "
                                        @click="setHotspotStateFilter('none')"
                                    >
                                        No link
                                        {{ hotspotCountForFilter('none') }}
                                    </Button>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-if="hotspotSearch.trim() !== ''"
                                        size="sm"
                                        variant="ghost"
                                        class="w-fit"
                                        @click="hotspotSearch = ''"
                                    >
                                        Clear search
                                    </Button>
                                    <Button
                                        v-if="hasActiveListControls"
                                        size="sm"
                                        variant="outline"
                                        class="w-fit"
                                        @click="resetHotspotListControls"
                                    >
                                        Reset filters
                                    </Button>
                                </div>
                                <div
                                    class="space-y-2 rounded-lg border bg-muted/20 p-2"
                                >
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground"
                                    >
                                        <p>
                                            Selected
                                            {{ selectedHotspotCount }}
                                            hotspot(s)
                                        </p>
                                        <p>
                                            Visible selected
                                            {{ visibleSelectedHotspotCount }}/{{
                                                filteredHotspots.length
                                            }}
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            :disabled="
                                                filteredHotspots.length === 0
                                            "
                                            @click="toggleSelectVisibleHotspots"
                                        >
                                            {{
                                                areAllVisibleHotspotsSelected
                                                    ? 'Unselect shown'
                                                    : 'Select shown'
                                            }}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            :disabled="!hasSelectedHotspots"
                                            @click="clearSelectedHotspots"
                                        >
                                            Clear selected
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            :disabled="
                                                !hasSelectedHotspots ||
                                                bulkDeleteForm.processing
                                            "
                                            @click="
                                                requestBulkDeleteSelectedHotspots
                                            "
                                        >
                                            Delete selected
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <div class="flex-1 space-y-3 overflow-y-auto p-3">
                                <div
                                    v-for="hotspot in filteredHotspots"
                                    :key="hotspot.id"
                                    :ref="
                                        (element) =>
                                            setHotspotRowRef(
                                                hotspot.id,
                                                element,
                                            )
                                    "
                                    role="button"
                                    tabindex="0"
                                    class="group cursor-pointer space-y-3 rounded-lg border bg-card p-3 shadow-sm transition hover:border-primary/40"
                                    :class="[
                                        activeHotspotId === hotspot.id
                                            ? 'border-primary bg-primary/5'
                                            : '',
                                        selectedHotspotIdSet.has(hotspot.id)
                                            ? 'border-destructive/40 bg-destructive/5'
                                            : '',
                                    ]"
                                    @mouseenter="
                                        handleHotspotRowHover(hotspot.id)
                                    "
                                    @mouseleave="
                                        handleHotspotRowLeave(hotspot.id)
                                    "
                                    @focus="handleHotspotRowHover(hotspot.id)"
                                    @blur="handleHotspotRowLeave(hotspot.id)"
                                    @click="openEditDialog(hotspot)"
                                    @keydown.enter.prevent="
                                        openEditDialog(hotspot)
                                    "
                                    @keydown.space.prevent="
                                        openEditDialog(hotspot)
                                    "
                                >
                                    <div class="flex items-start gap-3">
                                        <label
                                            class="mt-1 flex items-center gap-2 text-xs text-muted-foreground"
                                            @click.stop
                                        >
                                            <input
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-border text-primary focus:ring-primary"
                                                :checked="
                                                    selectedHotspotIdSet.has(
                                                        hotspot.id,
                                                    )
                                                "
                                                @change.stop="
                                                    toggleHotspotSelection(
                                                        hotspot.id,
                                                    )
                                                "
                                            />
                                            <span class="sr-only"
                                                >Select hotspot
                                                {{ hotspot.id }}</span
                                            >
                                        </label>
                                        <div
                                            class="relative h-16 w-24 shrink-0 overflow-hidden rounded-md border bg-muted/30"
                                        >
                                            <img
                                                v-if="imageSource !== ''"
                                                :src="imageSource"
                                                alt=""
                                                class="pointer-events-none absolute select-none"
                                                draggable="false"
                                                :style="
                                                    hotspotThumbnailImageStyle(
                                                        hotspot,
                                                    )
                                                "
                                            />
                                            <div
                                                v-else
                                                class="flex h-full w-full items-center justify-center text-[10px] text-muted-foreground"
                                            >
                                                No preview
                                            </div>
                                        </div>

                                        <div
                                            class="min-w-0 flex-1 space-y-2 text-sm"
                                        >
                                            <div
                                                class="flex flex-wrap items-center justify-between gap-2"
                                            >
                                                <p class="font-medium">
                                                    #{{ hotspot.id }}
                                                </p>
                                                <Badge
                                                    :variant="
                                                        hotspotLinkBadgeVariant(
                                                            hotspotLinkState(
                                                                hotspot,
                                                            ),
                                                        )
                                                    "
                                                >
                                                    {{
                                                        hotspotLinkLabel(
                                                            hotspotLinkState(
                                                                hotspot,
                                                            ),
                                                        )
                                                    }}
                                                </Badge>
                                            </div>
                                            <p class="text-muted-foreground">
                                                {{ hotspot.relation_kind }} →
                                                {{
                                                    hotspotTargetPageLabel(
                                                        hotspot.target_page_no,
                                                    )
                                                }}
                                            </p>
                                            <p
                                                v-if="
                                                    hotspot.target_hotspot_id ||
                                                    hotspot.linked_hotspot_id
                                                "
                                                class="text-muted-foreground"
                                            >
                                                Linked hotspot: #{{
                                                    hotspot.target_hotspot_id ??
                                                    hotspot.linked_hotspot_id
                                                }}
                                                <span
                                                    v-if="
                                                        linkedHotspotPageNo(
                                                            hotspot,
                                                        ) !== null
                                                    "
                                                >
                                                    (Page
                                                    {{
                                                        linkedHotspotPageNo(
                                                            hotspot,
                                                        )
                                                    }})
                                                </span>
                                            </p>
                                            <p
                                                v-if="hotspot.label"
                                                class="truncate text-muted-foreground"
                                            >
                                                {{ hotspot.label }}
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        class="flex max-h-0 flex-wrap gap-2 overflow-hidden opacity-0 transition-all duration-200 group-focus-within:max-h-20 group-focus-within:opacity-100 group-hover:max-h-20 group-hover:opacity-100"
                                        :class="[
                                            activeHotspotId === hotspot.id ||
                                            selectedHotspotIdSet.has(hotspot.id)
                                                ? '!max-h-20 !opacity-100'
                                                : '',
                                        ]"
                                    >
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            class="flex-1 sm:flex-none"
                                            @click.stop="
                                                focusHotspotOnCanvas(hotspot)
                                            "
                                        >
                                            Locate
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            class="flex-1 sm:flex-none"
                                            @click.stop="
                                                openEditDialog(hotspot)
                                            "
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            size="sm"
                                            :variant="
                                                areaEditHotspotId === hotspot.id
                                                    ? 'default'
                                                    : 'outline'
                                            "
                                            class="flex-1 sm:flex-none"
                                            @click.stop="
                                                areaEditHotspotId === hotspot.id
                                                    ? cancelAreaEditSelection()
                                                    : startAreaEditSelection(
                                                          hotspot,
                                                      )
                                            "
                                        >
                                            {{
                                                areaEditHotspotId === hotspot.id
                                                    ? 'Cancel area edit'
                                                    : 'Drag area'
                                            }}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            class="flex-1 sm:flex-none"
                                            :disabled="
                                                linkedHotspotId(hotspot) ===
                                                null
                                            "
                                            @click.stop="
                                                openLinkedHotspot(hotspot)
                                            "
                                        >
                                            Open linked
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            class="flex-1 sm:flex-none"
                                            @click.stop="deleteHotspot(hotspot)"
                                        >
                                            Delete
                                        </Button>
                                    </div>
                                </div>

                                <p
                                    v-if="
                                        filteredHotspots.length === 0 &&
                                        currentHotspots.length > 0
                                    "
                                    class="text-sm text-muted-foreground"
                                >
                                    No hotspots match your search.
                                </p>
                                <p
                                    v-if="currentHotspots.length === 0"
                                    class="text-sm text-muted-foreground"
                                >
                                    No hotspots yet. Draw a rectangle on the
                                    page image to create one.
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card v-else>
                <CardContent class="py-6 text-sm text-muted-foreground">
                    No pages found yet. Upload pages first, then open mapping.
                </CardContent>
            </Card>

            <Dialog
                :open="isDialogOpen"
                @update:open="onHotspotDialogOpenChange"
            >
                <DialogContent class="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{{ hotspotDialogTitle }}</DialogTitle>
                        <DialogDescription>
                            Configure relation and optional target references.
                            Target page and target hotspot can both be empty.
                        </DialogDescription>
                    </DialogHeader>
                    <p
                        v-if="hasDialogUnsavedChanges"
                        class="text-xs text-amber-700"
                    >
                        You have unsaved changes in this hotspot form.
                    </p>

                    <div class="space-y-4">
                        <div
                            class="space-y-3 rounded-lg border bg-muted/20 p-3"
                        >
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Link settings
                            </p>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium"
                                        >Relation kind</label
                                    >
                                    <Select v-model="hotspotForm.relation_kind">
                                        <SelectTrigger>
                                            <SelectValue
                                                placeholder="Select relation"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="next"
                                                >next</SelectItem
                                            >
                                            <SelectItem value="previous"
                                                >previous</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="
                                            hotspotForm.errors.relation_kind
                                        "
                                    />
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium"
                                        >Target page</label
                                    >
                                    <Select
                                        v-model="targetPageSelectValue"
                                    >
                                        <SelectTrigger>
                                            <SelectValue
                                                placeholder="No target page"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__none">
                                                No target page
                                            </SelectItem>
                                            <SelectItem
                                                v-for="pageNo in targetPageNumbers"
                                                :key="pageNo"
                                                :value="String(pageNo)"
                                            >
                                                Page {{ pageNo }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="
                                            hotspotForm.errors.target_page_no
                                        "
                                    />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-medium"
                                    >Target hotspot (optional)</label
                                >
                                <Select
                                    v-model="targetHotspotSelectValue"
                                    :disabled="hotspotForm.target_page_no === null"
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            :placeholder="
                                                hotspotForm.target_page_no === null
                                                    ? 'Select target page first'
                                                    : 'Any hotspot on target page'
                                            "
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__none"
                                            >Any hotspot on target
                                            page</SelectItem
                                        >
                                        <SelectItem
                                            v-for="targetHotspot in availableTargetHotspots"
                                            :key="targetHotspot.id"
                                            :value="String(targetHotspot.id)"
                                        >
                                            #{{ targetHotspot.id }}
                                            <span v-if="targetHotspot.label">
                                                -
                                                {{ targetHotspot.label }}</span
                                            >
                                            <span class="text-muted-foreground">
                                                ({{
                                                    targetHotspot.relation_kind
                                                }}
                                                →
                                                {{
                                                    hotspotTargetPageLabel(
                                                        targetHotspot.target_page_no,
                                                    )
                                                }})
                                            </span>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="
                                        hotspotForm.errors.target_hotspot_id
                                    "
                                />
                            </div>
                        </div>

                        <div class="space-y-3 rounded-lg border p-3">
                            <div class="space-y-2">
                                <label class="text-sm font-medium"
                                    >Label (optional)</label
                                >
                                <Input
                                    v-model="hotspotForm.label"
                                    placeholder="Optional label"
                                />
                                <InputError
                                    :message="hotspotForm.errors.label"
                                />
                            </div>

                            <div
                                v-if="editingHotspotId !== null"
                                class="flex flex-wrap items-center justify-between gap-2 rounded-md border bg-muted/20 p-2"
                            >
                                <p class="text-xs text-muted-foreground">
                                    Need a new area? Drag-select directly on the
                                    page image.
                                </p>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="requestAreaEditFromDialog"
                                >
                                    Edit area by drag
                                </Button>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            :disabled="hotspotForm.processing"
                            @click="requestDialogClose"
                        >
                            Cancel
                        </Button>
                        <Button
                            :disabled="hotspotForm.processing"
                            @click="saveHotspot"
                        >
                            {{ hotspotDialogActionText }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmActionDialog
                :open="deleteDialogOpen"
                title="Delete hotspot?"
                :description="
                    pendingDeleteHotspot === null
                        ? 'This action cannot be undone.'
                        : `Delete hotspot #${pendingDeleteHotspot.id}? This action cannot be undone.`
                "
                confirm-text="Delete"
                confirm-variant="destructive"
                @update:open="onDeleteDialogOpenChange"
                @confirm="confirmDeleteHotspot"
            />

            <ConfirmActionDialog
                :open="bulkDeleteDialogOpen"
                :title="bulkDeleteDialogTitle"
                :description="bulkDeleteDialogDescription"
                :confirm-text="bulkDeleteDialogConfirmText"
                confirm-variant="destructive"
                :processing="bulkDeleteForm.processing"
                @update:open="onBulkDeleteDialogOpenChange"
                @confirm="confirmBulkDeleteSelectedHotspots"
            />

            <ConfirmActionDialog
                :open="discardDialogOpen"
                title="Discard unsaved changes?"
                description="Your hotspot form has unsaved changes. Discard them and close?"
                confirm-text="Discard changes"
                confirm-variant="destructive"
                @update:open="onDiscardDialogOpenChange"
                @confirm="confirmDiscardDialogChanges"
            />
        </div>
    </EpAdminLayout>
</template>
