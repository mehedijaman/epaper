<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { GripVertical, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ConfirmActionDialog from '@/components/epaper/ConfirmActionDialog.vue';
import EditionContextBar from '@/components/epaper/EditionContextBar.vue';
import MultiPageUploadForm from '@/components/epaper/MultiPageUploadForm.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import type { BreadcrumbItem, Category, Page } from '@/types';

type EditionSummary = {
    id: number;
    edition_date: string;
    name?: string | null;
    status: 'draft' | 'published';
    published_at: string | null;
};

type EditionOption = {
    id: number;
    edition_date: string;
    name?: string | null;
    status: 'draft' | 'published';
    published_at: string | null;
    pages_count: number;
};

type Props = {
    selectedDate: string | null;
    dateError: string | null;
    dateNotice: string | null;
    selectedEdition: EditionSummary | null;
    canDeleteSelectedEdition: boolean;
    editionsForDate: EditionOption[];
    uploadConstraints: {
        maxFileSizeKb: number;
        maxFiles: number;
        serverUploadMaxBytes: number;
        serverPostMaxBytes: number;
        serverMaxFileUploads: number;
    };
    pages: Page[];
    categories: Category[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Manage Editions', href: '/admin/editions/manage' },
];

const date = ref(props.selectedDate ?? '');
const selectedEditionId = ref(
    typeof props.selectedEdition?.id === 'number' && props.selectedEdition.id > 0
        ? String(props.selectedEdition.id)
        : 'none',
);
const createEditionDialogOpen = ref(false);
const editEditionNameDialogOpen = ref(false);
const deleteEditionDialogOpen = ref(false);

watch(
    () => props.selectedDate,
    (value) => {
        date.value = value ?? '';
    },
);

watch(
    () => props.selectedEdition?.id ?? null,
    (value) => {
        selectedEditionId.value = typeof value === 'number' && value > 0 ? String(value) : 'none';
    },
);

const editionPages = computed<Page[]>(() => props.pages ?? []);
const orderedPages = ref<Page[]>([]);
const hasEdition = computed(() => props.selectedEdition !== null);
const canDeleteEdition = computed(() => hasEdition.value && props.canDeleteSelectedEdition);
const hasSelectedDate = computed(() => date.value !== '');
const editionId = computed<number>(() => props.selectedEdition?.id ?? 0);
const activePageId = ref<number | null>(null);
const isEditDialogOpen = ref(false);
const replaceInputKey = ref(0);
const replacementFileName = ref('');
const draggedPageId = ref<number | null>(null);
const dropIndex = ref<number | null>(null);
const deleteDialogOpen = ref(false);
const pendingDeletePage = ref<Page | null>(null);
const pendingUndoOrderIds = ref<number[] | null>(null);
const reorderNotice = ref<string | null>(null);

const activePage = computed<Page | null>(() => {
    if (activePageId.value === null) {
        return null;
    }

    return orderedPages.value.find((page) => page.id === activePageId.value) ?? null;
});

const updateForm = useForm<{
    page_no: number;
    category_id: number | null;
}>({
    page_no: 1,
    category_id: null,
});

const replaceForm = useForm<{
    file: File | null;
}>({
    file: null,
});

const reorderForm = useForm<{
    edition_id: number;
    ordered_page_ids: number[];
}>({
    edition_id: 0,
    ordered_page_ids: [],
});
const createEditionForm = useForm<{
    edition_date: string;
    name: string;
}>({
    edition_date: '',
    name: '',
});
const editEditionNameForm = useForm<{
    name: string;
}>({
    name: '',
});
const deleteEditionForm = useForm<Record<string, never>>({});

const selectedCategoryValue = computed<string>({
    get: () => {
        if (
            typeof updateForm.category_id !== 'number'
            || !Number.isFinite(updateForm.category_id)
            || updateForm.category_id <= 0
        ) {
            return 'none';
        }

        return String(updateForm.category_id);
    },
    set: (value: string) => {
        if (value === 'none') {
            updateForm.category_id = null;
            return;
        }

        const categoryId = Number.parseInt(value, 10);
        updateForm.category_id = Number.isFinite(categoryId) && categoryId > 0 ? categoryId : null;
    },
});

const hasOrderChanges = computed(() => {
    if (orderedPages.value.length !== editionPages.value.length) {
        return false;
    }

    return orderedPages.value.some((page, index) => page.id !== editionPages.value[index]?.id);
});

const isDragging = computed(() => draggedPageId.value !== null);
const hasUndoOrderAvailable = computed(() => {
    if (pendingUndoOrderIds.value === null || reorderForm.processing) {
        return false;
    }

    const currentPageIds = editionPages.value.map((page) => page.id).sort((left, right) => left - right);
    const undoPageIds = [...pendingUndoOrderIds.value].sort((left, right) => left - right);

    if (currentPageIds.length !== undoPageIds.length) {
        return false;
    }

    return currentPageIds.every((id, index) => id === undoPageIds[index]);
});

watch(
    editionPages,
    (value) => {
        orderedPages.value = [...value].sort((a, b) => a.page_no - b.page_no);
    },
    { immediate: true },
);

function loadManagePage(editionIdValue: number | null): void {
    const parsedEditionId = editionIdValue ?? Number.NaN;

    router.get(
        '/admin/editions/manage',
        {
            date: date.value !== '' ? date.value : undefined,
            edition_id: Number.isFinite(parsedEditionId) && parsedEditionId > 0
                ? parsedEditionId
                : undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function onDateInputChanged(): void {
    selectedEditionId.value = 'none';

    if (date.value === '') {
        loadManagePage(null);
        return;
    }

    loadManagePage(null);
}

function onEditionSelect(value: unknown): void {
    let normalizedValue = 'none';

    if (typeof value === 'string') {
        normalizedValue = value;
    } else if (typeof value === 'number' || typeof value === 'bigint') {
        normalizedValue = String(value);
    }

    selectedEditionId.value = normalizedValue;

    loadManagePage(
        normalizedValue === 'none'
            ? null
            : Number.parseInt(normalizedValue, 10),
    );
}

function openCreateEditionDialog(): void {
    if (date.value === '') {
        return;
    }

    createEditionForm.reset();
    createEditionForm.clearErrors();
    createEditionForm.edition_date = date.value;
    createEditionDialogOpen.value = true;
}

function submitCreateEdition(): void {
    if (date.value === '') {
        return;
    }

    createEditionForm.edition_date = date.value;
    createEditionForm.post('/admin/editions', {
        preserveScroll: true,
        onSuccess: () => {
            createEditionDialogOpen.value = false;
            createEditionForm.reset();
        },
    });
}

function openEditEditionNameDialog(): void {
    if (props.selectedEdition === null) {
        return;
    }

    editEditionNameForm.reset();
    editEditionNameForm.clearErrors();
    editEditionNameForm.name = props.selectedEdition.name ?? '';
    editEditionNameDialogOpen.value = true;
}

function submitEditEditionName(): void {
    if (props.selectedEdition === null) {
        return;
    }

    editEditionNameForm.patch(`/admin/editions/${props.selectedEdition.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editEditionNameDialogOpen.value = false;
        },
    });
}

function openDeleteEditionDialog(): void {
    if (!canDeleteEdition.value) {
        return;
    }

    deleteEditionForm.clearErrors();
    deleteEditionDialogOpen.value = true;
}

function confirmDeleteEdition(): void {
    if (props.selectedEdition === null || !canDeleteEdition.value) {
        return;
    }

    deleteEditionForm.delete(`/admin/editions/${props.selectedEdition.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteEditionDialogOpen.value = false;
        },
    });
}

function onDeleteEditionDialogOpenChange(value: boolean): void {
    deleteEditionDialogOpen.value = value;

    if (!value) {
        deleteEditionForm.clearErrors();
    }
}

function formatEditionName(item: { id: number; edition_date: string; name?: string | null }): string {
    if (typeof item.name === 'string' && item.name.trim() !== '') {
        return item.name.trim();
    }

    return `Edition ${item.id}`;
}

function formatEditionOption(item: EditionOption): string {
    const pageSuffix = item.pages_count === 1 ? 'page' : 'pages';

    return `${formatEditionName(item)} | ${item.edition_date} | ${item.status} | ${item.pages_count} ${pageSuffix}`;
}

function refreshAfterUpload(): void {
    // Upload redirects back to this page with updated props.
}

function movePageToIndex(sourcePageId: number, targetIndex: number): boolean {
    const current = [...orderedPages.value];
    const sourceIndex = current.findIndex((item) => item.id === sourcePageId);

    if (sourceIndex < 0) {
        return false;
    }

    let insertionIndex = Math.max(0, Math.min(targetIndex, current.length));

    const [moved] = current.splice(sourceIndex, 1);

    if (moved === undefined) {
        return false;
    }

    if (sourceIndex < insertionIndex) {
        insertionIndex -= 1;
    }

    insertionIndex = Math.max(0, Math.min(insertionIndex, current.length));
    current.splice(insertionIndex, 0, moved);

    const changed = current.some((item, index) => item.id !== orderedPages.value[index]?.id);

    if (!changed) {
        return false;
    }

    orderedPages.value = current;

    return true;
}

function onPageDragStart(pageId: number, event: DragEvent): void {
    if (reorderForm.processing) {
        event.preventDefault();
        return;
    }

    draggedPageId.value = pageId;
    const sourceIndex = orderedPages.value.findIndex((item) => item.id === pageId);
    dropIndex.value = sourceIndex >= 0 ? sourceIndex : null;

    if (event.dataTransfer === null) {
        return;
    }

    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(pageId));
}

function onDropZoneDragOver(targetIndex: number, event: DragEvent): void {
    if (draggedPageId.value === null || reorderForm.processing) {
        return;
    }

    event.preventDefault();
    dropIndex.value = Math.max(0, Math.min(targetIndex, orderedPages.value.length));

    if (event.dataTransfer !== null) {
        event.dataTransfer.dropEffect = 'move';
    }
}

function resolveCardDropIndex(index: number, event: DragEvent): number {
    const target = event.currentTarget;

    if (!(target instanceof HTMLElement)) {
        return index + 1;
    }

    const rect = target.getBoundingClientRect();
    const middleY = rect.top + (rect.height / 2);

    return event.clientY <= middleY ? index : index + 1;
}

function onPageCardDragOver(index: number, event: DragEvent): void {
    const targetIndex = resolveCardDropIndex(index, event);
    onDropZoneDragOver(targetIndex, event);
}

function onPageCardDrop(index: number, event: DragEvent): void {
    const targetIndex = resolveCardDropIndex(index, event);
    onDropZoneDrop(targetIndex, event);
}

function onDropZoneDrop(targetIndex: number, event: DragEvent): void {
    if (draggedPageId.value === null || reorderForm.processing) {
        return;
    }

    event.preventDefault();

    const transferredId = event.dataTransfer?.getData('text/plain') ?? '';
    const sourcePageId = transferredId !== ''
        ? Number.parseInt(transferredId, 10)
        : draggedPageId.value;

    if (sourcePageId === null || !Number.isFinite(sourcePageId)) {
        resetDragState();
        return;
    }

    const didMove = movePageToIndex(sourcePageId, targetIndex);
    resetDragState();

    if (!didMove) {
        return;
    }

    reorderNotice.value = null;
}

function onPageDragEnd(): void {
    resetDragState();
}

function resetDragState(): void {
    dropIndex.value = null;
    draggedPageId.value = null;
}

function isDropZoneActive(index: number): boolean {
    return draggedPageId.value !== null && dropIndex.value === index;
}

function savePageOrder(): void {
    if (!hasEdition.value || !hasOrderChanges.value) {
        return;
    }

    reorderNotice.value = null;
    const previousOrderPageIds = editionPages.value.map((page) => page.id);

    reorderForm.edition_id = editionId.value;
    reorderForm.ordered_page_ids = orderedPages.value.map((page) => page.id);

    reorderForm.post('/admin/pages/reorder', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            pendingUndoOrderIds.value = previousOrderPageIds;
            reorderNotice.value = 'Page order saved.';
        },
    });
}

function revertOrderDraft(): void {
    orderedPages.value = [...editionPages.value].sort((left, right) => left.page_no - right.page_no);
    reorderForm.clearErrors('ordered_page_ids');
    reorderNotice.value = null;
}

function undoLastOrderSave(): void {
    if (!hasEdition.value || pendingUndoOrderIds.value === null || !hasUndoOrderAvailable.value) {
        return;
    }

    reorderNotice.value = null;
    reorderForm.edition_id = editionId.value;
    reorderForm.ordered_page_ids = [...pendingUndoOrderIds.value];

    reorderForm.post('/admin/pages/reorder', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            pendingUndoOrderIds.value = null;
            reorderNotice.value = 'Reverted to the previous order.';
        },
    });
}

function openEditDialog(page: Page): void {
    activePageId.value = page.id;
    updateForm.page_no = page.page_no;
    updateForm.category_id = typeof page.category_id === 'number' && Number.isFinite(page.category_id) && page.category_id > 0
        ? page.category_id
        : null;
    updateForm.clearErrors();

    resetReplacementForm();
    isEditDialogOpen.value = true;
}

function closeEditDialog(): void {
    isEditDialogOpen.value = false;
    activePageId.value = null;
}

function onEditDialogOpenChange(value: boolean): void {
    isEditDialogOpen.value = value;

    if (!value) {
        activePageId.value = null;
    }
}

function savePageMeta(): void {
    if (activePage.value === null) {
        return;
    }

    updateForm.put(`/admin/pages/${activePage.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            closeEditDialog();
        },
    });
}

function onReplacementFileChange(event: Event): void {
    const input = event.target as HTMLInputElement | null;
    const file = input?.files?.[0] ?? null;

    replaceForm.file = file;
    replacementFileName.value = file?.name ?? '';
}

function resetReplacementForm(): void {
    replaceForm.reset('file');
    replaceForm.clearErrors();
    replacementFileName.value = '';
    replaceInputKey.value += 1;
}

function replacePageImage(): void {
    if (activePage.value === null || replaceForm.file === null) {
        return;
    }

    replaceForm.put(`/admin/pages/${activePage.value.id}/replace`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            resetReplacementForm();
        },
    });
}

function deletePage(page: Page): void {
    if (isEditDialogOpen.value && activePage.value?.id === page.id) {
        closeEditDialog();
    }

    pendingDeletePage.value = page;
    deleteDialogOpen.value = true;
}

function confirmDeletePage(): void {
    if (pendingDeletePage.value === null) {
        return;
    }

    const pageToDelete = pendingDeletePage.value;

    router.delete(`/admin/pages/${pageToDelete.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialogOpen.value = false;
            pendingDeletePage.value = null;
        },
    });
}

function onDeleteDialogOpenChange(open: boolean): void {
    deleteDialogOpen.value = open;

    if (!open) {
        pendingDeletePage.value = null;
    }
}

const mappingHref = computed<string | undefined>(() => {
    const firstPage = orderedPages.value[0];

    if (firstPage === undefined) {
        return undefined;
    }

    return `/admin/hotspots?page_id=${firstPage.id}`;
});

const publishHref = computed<string | undefined>(() => {
    if (props.selectedEdition === null) {
        return undefined;
    }

    return `/admin/editions/publish?date=${props.selectedEdition.edition_date}&edition_id=${props.selectedEdition.id}`;
});

const manageHref = computed<string | undefined>(() => {
    if (props.selectedEdition === null) {
        return undefined;
    }

    return `/admin/editions/manage?date=${props.selectedEdition.edition_date}&edition_id=${props.selectedEdition.id}`;
});

watch(deleteDialogOpen, (isOpen) => {
    if (isOpen) {
        return;
    }

    pendingDeletePage.value = null;
});

watch(
    () => props.pages,
    () => {
        if (pendingDeletePage.value === null) {
            return;
        }

        const stillExists = props.pages.some((page) => page.id === pendingDeletePage.value?.id);

        if (!stillExists) {
            pendingDeletePage.value = null;
            deleteDialogOpen.value = false;
        }
    },
);

watch(
    () => editionId.value,
    () => {
        pendingUndoOrderIds.value = null;
        reorderNotice.value = null;
        reorderForm.clearErrors('ordered_page_ids');
        editEditionNameDialogOpen.value = false;
        editEditionNameForm.reset();
        editEditionNameForm.clearErrors();
        deleteEditionDialogOpen.value = false;
        deleteEditionForm.clearErrors();
    },
);

watch(hasUndoOrderAvailable, (value) => {
    if (value || pendingUndoOrderIds.value === null) {
        return;
    }

    pendingUndoOrderIds.value = null;
});

watch(isEditDialogOpen, (isOpen) => {
    if (isOpen) {
        return;
    }

    updateForm.clearErrors();
    resetReplacementForm();
});
</script>

<template>
    <Head title="Manage Editions" />
    <EpAdminLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-7xl space-y-5 p-4 sm:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div class="space-y-1">
                    <h1 class="text-2xl font-semibold tracking-tight">Manage Pages</h1>
                    <p class="text-sm text-muted-foreground">
                        Search by edition date, upload pages, and maintain ordering and metadata.
                    </p>
                </div>
                <p class="text-xs text-muted-foreground">
                    Drag to reorder, then save explicitly.
                </p>
            </div>

            <Card class="border-border/70">
                <CardHeader class="pb-2">
                    <CardTitle>Select date and edition</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,220px)_minmax(0,320px)_auto] xl:items-end">
                        <div class="space-y-1">
                            <label for="manage-edition-date" class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Edition date
                            </label>
                            <Input id="manage-edition-date" v-model="date" type="date" @change="onDateInputChanged" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Edition
                            </label>
                            <Select :model-value="selectedEditionId" @update:model-value="onEditionSelect">
                                <SelectTrigger :disabled="!hasSelectedDate">
                                    <SelectValue placeholder="Select edition" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">Select an edition</SelectItem>
                                    <SelectItem
                                        v-for="item in props.editionsForDate"
                                        :key="item.id"
                                        :value="String(item.id)"
                                    >
                                        {{ formatEditionOption(item) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-1 xl:grid-cols-1">
                            <Button
                                class="w-full md:w-auto xl:min-w-28"
                                variant="outline"
                                :disabled="!hasSelectedDate"
                                @click="openCreateEditionDialog"
                            >
                                Create new edition
                            </Button>
                            <Button
                                class="w-full md:w-auto xl:min-w-28"
                                variant="secondary"
                                :disabled="!hasEdition"
                                @click="openEditEditionNameDialog"
                            >
                                Edit edition name
                            </Button>
                            <Button
                                class="w-full md:w-auto xl:min-w-28"
                                variant="destructive"
                                :disabled="!canDeleteEdition || deleteEditionForm.processing"
                                @click="openDeleteEditionDialog"
                            >
                                Delete edition
                            </Button>
                        </div>
                    </div>
                    <p v-if="!hasSelectedDate" class="text-sm text-muted-foreground">
                        Pick a date to load available editions.
                    </p>
                    <p v-if="props.dateError" class="text-sm text-destructive">
                        {{ props.dateError }}
                    </p>
                    <p v-if="props.dateNotice" class="text-sm text-muted-foreground">
                        {{ props.dateNotice }}
                    </p>
                </CardContent>
            </Card>

            <div v-if="hasEdition" class="space-y-4">
                <EditionContextBar
                    v-if="props.selectedEdition"
                    :edition-id="props.selectedEdition.id"
                    :edition-date="props.selectedEdition.edition_date"
                    :edition-name="props.selectedEdition.name"
                    :status="props.selectedEdition.status"
                    :pages-count="orderedPages.length"
                    :published-at="props.selectedEdition.published_at"
                    :manage-href="manageHref"
                    :publish-href="publishHref"
                    :mapping-href="mappingHref"
                />

                <MultiPageUploadForm
                    :edition-id="editionId"
                    :categories="props.categories"
                    :upload-constraints="props.uploadConstraints"
                    :existing-page-numbers="orderedPages.map((page) => page.page_no)"
                    @uploaded="refreshAfterUpload"
                />

                <Card class="border-border/70">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex flex-wrap items-center gap-2">
                            <span>Uploaded pages</span>
                            <Badge variant="secondary">{{ orderedPages.length }}</Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="mb-3 text-xs text-muted-foreground">
                            Use the drag handle to reorder pages. Save changes when you are ready.
                        </p>

                        <p v-if="reorderForm.processing" class="mb-3 text-xs text-muted-foreground">
                            Saving order...
                        </p>

                        <p v-if="reorderForm.errors.ordered_page_ids" class="mb-3 text-sm text-destructive">
                            {{ reorderForm.errors.ordered_page_ids }}
                        </p>

                        <div
                            v-if="hasOrderChanges || hasUndoOrderAvailable || reorderNotice"
                            class="mb-3 flex flex-wrap items-center gap-2 rounded-md border border-border/70 bg-muted/20 px-3 py-2"
                        >
                            <Badge v-if="hasOrderChanges" variant="secondary">
                                Unsaved order changes
                            </Badge>
                            <p v-else-if="reorderNotice" class="text-xs text-muted-foreground">
                                {{ reorderNotice }}
                            </p>

                            <div class="ml-auto flex flex-wrap gap-2">
                                <Button
                                    v-if="hasOrderChanges"
                                    size="sm"
                                    :disabled="reorderForm.processing"
                                    @click="savePageOrder"
                                >
                                    Save order
                                </Button>
                                <Button
                                    v-if="hasOrderChanges"
                                    size="sm"
                                    variant="outline"
                                    :disabled="reorderForm.processing"
                                    @click="revertOrderDraft"
                                >
                                    Cancel changes
                                </Button>
                                <Button
                                    v-if="!hasOrderChanges && hasUndoOrderAvailable"
                                    size="sm"
                                    variant="outline"
                                    :disabled="reorderForm.processing"
                                    @click="undoLastOrderSave"
                                >
                                    Undo last save
                                </Button>
                            </div>
                        </div>

                        <div v-if="orderedPages.length === 0" class="text-sm text-muted-foreground">
                            No pages uploaded for this edition yet.
                        </div>

                        <div v-else class="space-y-2">
                            <div
                                class="rounded-md transition-colors"
                                :class="isDropZoneActive(0) ? 'h-2 bg-primary/45' : (isDragging ? 'h-2 bg-muted/60' : 'h-1 bg-transparent')"
                                @dragover="onDropZoneDragOver(0, $event)"
                                @drop="onDropZoneDrop(0, $event)"
                            />

                            <template v-for="(page, index) in orderedPages" :key="page.id">
                                <article
                                    class="group rounded-xl border border-border/70 bg-card p-3 transition-shadow hover:shadow-sm"
                                    :class="draggedPageId === page.id ? 'opacity-60' : ''"
                                    :draggable="!reorderForm.processing"
                                    @dragstart="onPageDragStart(page.id, $event)"
                                    @dragend="onPageDragEnd"
                                    @dragenter="onPageCardDragOver(index, $event)"
                                    @dragover="onPageCardDragOver(index, $event)"
                                    @drop="onPageCardDrop(index, $event)"
                                >
                                    <div class="grid gap-3 sm:grid-cols-[140px_minmax(0,1fr)]">
                                        <img
                                            :src="page.image_thumb_url"
                                            :alt="`Page ${page.page_no}`"
                                            class="aspect-[3/4] w-full rounded-md border border-border/60 object-cover"
                                            draggable="false"
                                            loading="lazy"
                                        />

                                        <div class="space-y-3">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="text-sm font-medium">Page {{ page.page_no }}</p>
                                                <Badge variant="outline" class="text-[11px]">
                                                    Position {{ index + 1 }}
                                                </Badge>
                                                <span
                                                    class="ml-auto inline-flex select-none items-center gap-1 rounded border border-border px-2 py-0.5 text-[11px] text-muted-foreground"
                                                    :class="reorderForm.processing
                                                        ? 'cursor-not-allowed opacity-50'
                                                        : 'cursor-grab hover:bg-muted active:cursor-grabbing'"
                                                >
                                                    <GripVertical class="size-3.5" />
                                                    Drag
                                                </span>
                                            </div>

                                            <div class="space-y-1 text-xs text-muted-foreground">
                                                <p>
                                                    Category:
                                                    <span class="font-medium text-foreground">
                                                        {{ page.category_name ?? 'Uncategorized' }}
                                                    </span>
                                                </p>
                                                <p>{{ page.width }} x {{ page.height }}</p>
                                            </div>

                                            <div class="flex flex-wrap gap-2 pt-1">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    class="flex-1 sm:flex-none"
                                                    @click="openEditDialog(page)"
                                                >
                                                    <Pencil class="size-4" />
                                                    Edit
                                                </Button>
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    class="flex-1 sm:flex-none"
                                                    @click="deletePage(page)"
                                                >
                                                    <Trash2 class="size-4" />
                                                    Delete
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </article>

                                <div
                                    class="rounded-md transition-colors"
                                    :class="isDropZoneActive(index + 1) ? 'h-2 bg-primary/45' : (isDragging ? 'h-2 bg-muted/60' : 'h-1 bg-transparent')"
                                    @dragover="onDropZoneDragOver(index + 1, $event)"
                                    @drop="onDropZoneDrop(index + 1, $event)"
                                />
                            </template>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card v-else>
                <CardContent class="py-6 text-sm text-muted-foreground">
                    <p v-if="!hasSelectedDate">
                        Select a date to load editions.
                    </p>
                    <p v-else>
                        Select an edition for {{ date }} or create a new one.
                    </p>
                </CardContent>
            </Card>

            <Dialog v-model:open="createEditionDialogOpen">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Create new edition</DialogTitle>
                        <DialogDescription>
                            This creates a new draft edition for {{ date }}.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium" for="create-edition-date">
                                Edition date
                            </label>
                            <Input id="create-edition-date" v-model="createEditionForm.edition_date" type="date" disabled />
                            <p v-if="createEditionForm.errors.edition_date" class="text-xs text-destructive">
                                {{ createEditionForm.errors.edition_date }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium" for="create-edition-name">
                                Name (optional)
                            </label>
                            <Input
                                id="create-edition-name"
                                v-model="createEditionForm.name"
                                type="text"
                                maxlength="150"
                                placeholder="Morning Edition"
                            />
                            <p class="text-xs text-muted-foreground">
                                If empty, a default label like "Edition 42" will be shown.
                            </p>
                            <p v-if="createEditionForm.errors.name" class="text-xs text-destructive">
                                {{ createEditionForm.errors.name }}
                            </p>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" :disabled="createEditionForm.processing" @click="createEditionDialogOpen = false">
                            Cancel
                        </Button>
                        <Button :disabled="createEditionForm.processing || !hasSelectedDate" @click="submitCreateEdition">
                            Create edition
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog v-model:open="editEditionNameDialogOpen">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Edit edition name</DialogTitle>
                        <DialogDescription>
                            Update the name shown for this edition.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium" for="edit-edition-date">
                                Edition date
                            </label>
                            <Input
                                id="edit-edition-date"
                                :model-value="props.selectedEdition?.edition_date ?? ''"
                                type="date"
                                disabled
                            />
                        </div>

                        <div class="space-y-2">
                            <label class="text-sm font-medium" for="edit-edition-name">
                                Name (optional)
                            </label>
                            <Input
                                id="edit-edition-name"
                                v-model="editEditionNameForm.name"
                                type="text"
                                maxlength="150"
                                placeholder="Morning Edition"
                            />
                            <p class="text-xs text-muted-foreground">
                                Leave blank to use the default label.
                            </p>
                            <p v-if="editEditionNameForm.errors.name" class="text-xs text-destructive">
                                {{ editEditionNameForm.errors.name }}
                            </p>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" :disabled="editEditionNameForm.processing" @click="editEditionNameDialogOpen = false">
                            Cancel
                        </Button>
                        <Button :disabled="editEditionNameForm.processing || !hasEdition" @click="submitEditEditionName">
                            Save name
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog :open="isEditDialogOpen" @update:open="onEditDialogOpenChange">
                <DialogContent class="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            Edit page {{ activePage?.page_no ?? '-' }}
                        </DialogTitle>
                        <DialogDescription>
                            Update page metadata, then optionally replace the uploaded image.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-5">
                        <div class="grid gap-4 sm:grid-cols-[170px_minmax(0,1fr)]">
                            <img
                                v-if="activePage"
                                :src="activePage.image_thumb_url"
                                :alt="`Page ${activePage.page_no}`"
                                class="aspect-[3/4] w-full rounded-md border object-cover"
                            />

                            <div class="space-y-3">
                                <div class="space-y-2">
                                    <label class="text-sm font-medium" for="manage-page-no">Page number</label>
                                    <Input
                                        id="manage-page-no"
                                        v-model.number="updateForm.page_no"
                                        type="number"
                                        min="1"
                                    />
                                    <p v-if="updateForm.errors.page_no" class="text-xs text-destructive">
                                        {{ updateForm.errors.page_no }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Category</label>
                                    <Select v-model="selectedCategoryValue">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select category" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">Uncategorized</SelectItem>
                                            <SelectItem
                                                v-for="category in props.categories"
                                                :key="category.id"
                                                :value="String(category.id)"
                                            >
                                                {{ category.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p v-if="updateForm.errors.category_id" class="text-xs text-destructive">
                                        {{ updateForm.errors.category_id }}
                                    </p>
                                </div>

                                <Button
                                    :disabled="updateForm.processing"
                                    @click="savePageMeta"
                                >
                                    Save changes
                                </Button>
                            </div>
                        </div>

                        <div class="space-y-2 rounded-md border p-3">
                            <p class="text-sm font-medium">Replace page image</p>
                            <Input
                                :key="replaceInputKey"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                @change="onReplacementFileChange"
                            />
                            <p v-if="replacementFileName !== ''" class="text-xs text-muted-foreground">
                                Selected: {{ replacementFileName }}
                            </p>
                            <p v-if="replaceForm.errors.file" class="text-xs text-destructive">
                                {{ replaceForm.errors.file }}
                            </p>
                            <div class="flex justify-end">
                                <Button
                                    variant="outline"
                                    :disabled="replaceForm.processing || replaceForm.file === null"
                                    @click="replacePageImage"
                                >
                                    Replace image
                                </Button>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="closeEditDialog">
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmActionDialog
                :open="deleteDialogOpen"
                title="Delete page?"
                :description="pendingDeletePage === null
                    ? 'This also removes related hotspots.'
                    : `Delete page ${pendingDeletePage.page_no}? This also removes related hotspots.`"
                confirm-text="Delete"
                confirm-variant="destructive"
                @update:open="onDeleteDialogOpenChange"
                @confirm="confirmDeletePage"
            />

            <ConfirmActionDialog
                :open="deleteEditionDialogOpen"
                title="Delete edition?"
                :description="props.selectedEdition === null
                    ? 'This edition and all uploaded pages will be deleted.'
                    : `Delete ${formatEditionName(props.selectedEdition)} on ${props.selectedEdition.edition_date}? This deletes all uploaded pages and hotspots for this edition.`"
                confirm-text="Delete edition"
                confirm-variant="destructive"
                :processing="deleteEditionForm.processing"
                @update:open="onDeleteEditionDialogOpenChange"
                @confirm="confirmDeleteEdition"
            />
        </div>
    </EpAdminLayout>
</template>
