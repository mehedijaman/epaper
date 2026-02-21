<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { GripVertical, Pencil, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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
    status: 'draft' | 'published';
    published_at: string | null;
};

type Props = {
    date: string;
    date_error: string | null;
    edition: EditionSummary | null;
    pages: Page[];
    categories: Category[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Manage Editions', href: '/admin/editions/manage' },
];

const date = ref(props.date);

watch(
    () => props.date,
    (value) => {
        date.value = value;
    },
);

const editionPages = computed<Page[]>(() => props.pages ?? []);
const pages = ref<Page[]>([]);
const hasEdition = computed(() => props.edition !== null);
const editionId = computed<number>(() => props.edition?.id ?? 0);
const activePageId = ref<number | null>(null);
const isEditDialogOpen = ref(false);
const replaceInputKey = ref(0);
const replacementFileName = ref('');
const draggedPageId = ref<number | null>(null);
const dropIndex = ref<number | null>(null);

const activePage = computed<Page | null>(() => {
    if (activePageId.value === null) {
        return null;
    }

    return pages.value.find((page) => page.id === activePageId.value) ?? null;
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
    if (pages.value.length !== editionPages.value.length) {
        return false;
    }

    return pages.value.some((page, index) => page.id !== editionPages.value[index]?.id);
});

const statusBadgeVariant = computed<'default' | 'secondary'>(() => {
    return props.edition?.status === 'published' ? 'default' : 'secondary';
});

const isDragging = computed(() => draggedPageId.value !== null);

watch(
    editionPages,
    (value) => {
        pages.value = [...value].sort((a, b) => a.page_no - b.page_no);
    },
    { immediate: true },
);

function searchByDate(): void {
    router.get(
        '/admin/editions/manage',
        {
            date: date.value !== '' ? date.value : undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

function refreshAfterUpload(): void {
    // Upload redirects back to this page with updated props.
}

function movePageToIndex(sourcePageId: number, targetIndex: number): boolean {
    const current = [...pages.value];
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

    const changed = current.some((item, index) => item.id !== pages.value[index]?.id);

    if (!changed) {
        return false;
    }

    pages.value = current;

    return true;
}

function onPageDragStart(pageId: number, event: DragEvent): void {
    if (reorderForm.processing) {
        event.preventDefault();
        return;
    }

    draggedPageId.value = pageId;
    const sourceIndex = pages.value.findIndex((item) => item.id === pageId);
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
    dropIndex.value = Math.max(0, Math.min(targetIndex, pages.value.length));

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

    persistPageOrder();
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

function persistPageOrder(): void {
    if (!hasEdition.value || !hasOrderChanges.value) {
        return;
    }

    reorderForm.edition_id = editionId.value;
    reorderForm.ordered_page_ids = pages.value.map((page) => page.id);

    reorderForm.post('/admin/pages/reorder', {
        preserveScroll: true,
        preserveState: true,
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
    if (!window.confirm(`Delete page ${page.page_no}? This also removes its hotspots.`)) {
        return;
    }

    router.delete(`/admin/pages/${page.id}`, {
        preserveScroll: true,
    });
}

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
                    Autosave order is enabled.
                </p>
            </div>

            <Card class="border-border/70">
                <CardHeader class="pb-2">
                    <CardTitle>Search edition by date</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="space-y-1">
                            <label for="manage-edition-date" class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                Edition date
                            </label>
                            <Input id="manage-edition-date" v-model="date" type="date" class="sm:w-56" />
                        </div>
                        <Button class="sm:min-w-28" @click="searchByDate">Search</Button>
                    </div>
                    <p v-if="props.date_error" class="text-sm text-destructive">
                        {{ props.date_error }}
                    </p>
                </CardContent>
            </Card>

            <div v-if="hasEdition" class="space-y-4">
                <Card class="border-border/70">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex flex-wrap items-center gap-2">
                            <span>Edition {{ props.edition?.edition_date }}</span>
                            <Badge :variant="statusBadgeVariant">
                                {{ props.edition?.status }}
                            </Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-3 text-sm text-muted-foreground sm:grid-cols-3">
                        <p>Pages: {{ pages.length }}</p>
                        <p>Categories loaded: {{ props.categories.length }}</p>
                        <p>Edition ID: {{ props.edition?.id }}</p>
                    </CardContent>
                </Card>

                <MultiPageUploadForm
                    :edition-id="editionId"
                    @uploaded="refreshAfterUpload"
                />

                <Card class="border-border/70">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex flex-wrap items-center gap-2">
                            <span>Uploaded pages</span>
                            <Badge variant="secondary">{{ pages.length }}</Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="mb-3 text-xs text-muted-foreground">
                            Use the drag handle to move pages before or after others. New order is saved immediately.
                        </p>

                        <p v-if="reorderForm.processing" class="mb-3 text-xs text-muted-foreground">
                            Saving new order...
                        </p>

                        <p v-if="reorderForm.errors.ordered_page_ids" class="mb-3 text-sm text-destructive">
                            {{ reorderForm.errors.ordered_page_ids }}
                        </p>

                        <div v-if="pages.length === 0" class="text-sm text-muted-foreground">
                            No pages uploaded for this edition yet.
                        </div>

                        <div v-else class="space-y-2">
                            <div
                                class="rounded-md transition-colors"
                                :class="isDropZoneActive(0) ? 'h-2 bg-primary/45' : (isDragging ? 'h-2 bg-muted/60' : 'h-1 bg-transparent')"
                                @dragover="onDropZoneDragOver(0, $event)"
                                @drop="onDropZoneDrop(0, $event)"
                            />

                            <template v-for="(page, index) in pages" :key="page.id">
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
                    Select a date and click Search to load or create an edition.
                </CardContent>
            </Card>

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
        </div>
    </EpAdminLayout>
</template>
