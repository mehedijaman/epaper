<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { GripVertical, LoaderCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import ConfirmActionDialog from '@/components/epaper/ConfirmActionDialog.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
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
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import EpAdminLayout from '@/layouts/EpAdminLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface Category {
    id: number;
    name: string;
    position: number;
    is_active: boolean;
}

interface CategoryListItem extends Category {
    pages_count: number;
}

interface CategoryProp {
    id: number;
    name: string;
    position: number;
    is_active: boolean | number | string | null;
    pages_count: number;
}

interface CategoryFormPayload {
    name: string;
    is_active: boolean;
}

interface PageErrorBag {
    category?: string;
    ordered_ids?: string;
    is_active?: string;
}

const props = defineProps<{
    categories: CategoryProp[];
}>();

const page = usePage<{
    errors?: PageErrorBag;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Categories', href: '/admin/categories' },
];

const categoryItems = ref<CategoryListItem[]>([]);
const dragSnapshot = ref<CategoryListItem[] | null>(null);
const reorderError = ref('');
const toggleError = ref('');
const editingCategoryId = ref<number | null>(null);
const pendingDeleteCategory = ref<CategoryListItem | null>(null);
const createDialogOpen = ref(false);
const editDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const deleteProcessing = ref(false);
const toggleProcessingById = ref<Record<number, boolean>>({});

const createForm = useForm<CategoryFormPayload>({
    name: '',
    is_active: true,
});

const editForm = useForm<CategoryFormPayload>({
    name: '',
    is_active: true,
});

const reorderForm = useForm<{
    ordered_ids: number[];
}>({
    ordered_ids: [],
});

const totalCategories = computed(() => categoryItems.value.length);
const activeCategories = computed(
    () => categoryItems.value.filter((item) => item.is_active).length,
);
const mappedPagesCount = computed(() =>
    categoryItems.value.reduce((sum, item) => sum + item.pages_count, 0),
);
const deleteError = computed(() => page.props.errors?.category ?? '');
const reorderFeedback = computed(
    () => reorderError.value || page.props.errors?.ordered_ids || '',
);
const toggleFeedback = computed(
    () => toggleError.value || page.props.errors?.is_active || '',
);
const dragDisabled = computed(
    () =>
        createDialogOpen.value ||
        editDialogOpen.value ||
        deleteDialogOpen.value ||
        reorderForm.processing ||
        categoryItems.value.length < 2,
);

watch(
    () => props.categories,
    (incoming) => {
        categoryItems.value = normalizeCategories(incoming);
    },
    { immediate: true },
);

function normalizeBoolean(value: boolean | number | string | null): boolean {
    if (typeof value === 'boolean') {
        return value;
    }

    if (typeof value === 'number') {
        return value === 1;
    }

    if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase();
        return (
            normalized === '1' ||
            normalized === 'true' ||
            normalized === 'yes' ||
            normalized === 'on'
        );
    }

    return false;
}

function normalizeCategories(incoming: CategoryProp[]): CategoryListItem[] {
    return [...incoming]
        .map((category) => ({
            id: category.id,
            name: category.name,
            position: category.position,
            is_active: normalizeBoolean(category.is_active),
            pages_count: category.pages_count,
        }))
        .sort((left, right) => left.position - right.position);
}

function cloneCategories(source: CategoryListItem[]): CategoryListItem[] {
    return source.map((category) => ({ ...category }));
}

function resetCreateForm(): void {
    createForm.reset();
    createForm.clearErrors();
    createForm.is_active = true;
}

function openCreateDialog(): void {
    resetCreateForm();
    createDialogOpen.value = true;
}

function submitCreate(): void {
    createForm.post('/admin/categories', {
        preserveScroll: true,
        onSuccess: () => {
            createDialogOpen.value = false;
            resetCreateForm();
        },
    });
}

function openEditDialog(category: CategoryListItem): void {
    editingCategoryId.value = category.id;
    editForm.name = category.name;
    editForm.is_active = category.is_active;
    editForm.clearErrors();
    editDialogOpen.value = true;
}

function submitEdit(): void {
    if (editingCategoryId.value === null) {
        return;
    }

    editForm.put(`/admin/categories/${editingCategoryId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            editDialogOpen.value = false;
            editingCategoryId.value = null;
        },
    });
}

function deleteCategory(category: CategoryListItem): void {
    if (category.pages_count > 0) {
        return;
    }

    pendingDeleteCategory.value = category;
    deleteDialogOpen.value = true;
}

function confirmDeleteCategory(): void {
    if (pendingDeleteCategory.value === null) {
        return;
    }

    const categoryId = pendingDeleteCategory.value.id;
    deleteProcessing.value = true;

    router.delete(`/admin/categories/${categoryId}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialogOpen.value = false;
            pendingDeleteCategory.value = null;
        },
        onFinish: () => {
            deleteProcessing.value = false;
        },
    });
}

function onDeleteDialogOpenChange(open: boolean): void {
    deleteDialogOpen.value = open;

    if (!open && !deleteProcessing.value) {
        pendingDeleteCategory.value = null;
    }
}

function deleteDisabledReason(category: CategoryListItem): string | undefined {
    if (category.pages_count > 0) {
        return 'Cannot delete a category that is assigned to pages.';
    }

    return undefined;
}

function onDragStart(): void {
    dragSnapshot.value = cloneCategories(categoryItems.value);
    reorderError.value = '';
}

function updateDraftPositions(): void {
    categoryItems.value = categoryItems.value.map((category, index) => ({
        ...category,
        position: index + 1,
    }));
}

function onDragEnd(): void {
    const previousOrder = dragSnapshot.value
        ? cloneCategories(dragSnapshot.value)
        : cloneCategories(categoryItems.value);
    dragSnapshot.value = null;

    if (dragDisabled.value) {
        return;
    }

    const previousIds = previousOrder.map((category) => category.id);
    const currentIds = categoryItems.value.map((category) => category.id);
    const changed =
        previousIds.length === currentIds.length &&
        previousIds.some((id, index) => id !== currentIds[index]);

    if (!changed) {
        updateDraftPositions();
        return;
    }

    updateDraftPositions();
    reorderForm.ordered_ids = [...currentIds];
    reorderError.value = '';
    reorderForm.clearErrors();

    reorderForm.patch('/admin/categories/reorder', {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            categoryItems.value = previousOrder;
            reorderError.value =
                'Could not save category order. Please try again.';
        },
        onSuccess: () => {
            reorderError.value = '';
        },
        onFinish: () => {
            reorderForm.ordered_ids = [];
        },
    });
}

function setToggleProcessing(categoryId: number, value: boolean): void {
    toggleProcessingById.value = {
        ...toggleProcessingById.value,
        [categoryId]: value,
    };
}

function isToggleProcessing(categoryId: number): boolean {
    return toggleProcessingById.value[categoryId] === true;
}

function toggleCategoryStatus(categoryId: number, checked: boolean): void {
    const index = categoryItems.value.findIndex(
        (category) => category.id === categoryId,
    );

    if (index < 0) {
        return;
    }

    const original = categoryItems.value[index];

    if (original === undefined || original.is_active === checked) {
        return;
    }

    categoryItems.value[index] = {
        ...original,
        is_active: checked,
    };

    toggleError.value = '';
    setToggleProcessing(categoryId, true);

    router.patch(
        `/admin/categories/${categoryId}/toggle`,
        { is_active: checked },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                const rollbackIndex = categoryItems.value.findIndex(
                    (category) => category.id === categoryId,
                );

                if (rollbackIndex >= 0 && original !== undefined) {
                    categoryItems.value[rollbackIndex] = { ...original };
                }

                toggleError.value =
                    'Could not update category status. Please try again.';
            },
            onFinish: () => {
                setToggleProcessing(categoryId, false);
            },
        },
    );
}
</script>

<template>
    <Head title="Categories" />

    <EpAdminLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-7xl space-y-5 p-4 sm:p-6">
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"
            >
                <div class="space-y-1">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Categories
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Drag to reorder categories and toggle visibility
                        instantly.
                    </p>
                </div>
                <Button class="w-full sm:w-auto" @click="openCreateDialog">
                    Create Category
                </Button>
            </div>

            <InputError :message="deleteError" />
            <InputError :message="reorderFeedback" />
            <InputError :message="toggleFeedback" />

            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Total Categories</CardDescription>
                        <CardTitle class="text-2xl">{{
                            totalCategories
                        }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Active Categories</CardDescription>
                        <CardTitle class="text-2xl">{{
                            activeCategories
                        }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Mapped Pages</CardDescription>
                        <CardTitle class="text-2xl">{{
                            mappedPagesCount
                        }}</CardTitle>
                    </CardHeader>
                </Card>
            </div>

            <Card>
                <CardHeader class="gap-2">
                    <CardTitle class="text-base">Category List</CardTitle>
                    <CardDescription class="flex items-center gap-2">
                        <GripVertical class="size-4" />
                        Drag to reorder. Changes are saved automatically.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="categoryItems.length === 0"
                        class="rounded-lg border border-dashed border-border py-10 text-center text-sm text-muted-foreground"
                    >
                        No categories found. Create your first category.
                    </div>

                    <VueDraggable
                        v-else
                        v-model="categoryItems"
                        item-key="id"
                        handle=".category-drag-handle"
                        :disabled="dragDisabled"
                        class="space-y-3 md:hidden"
                        @start="onDragStart"
                        @end="onDragEnd"
                    >
                        <article
                            v-for="category in categoryItems"
                            :key="category.id"
                            class="space-y-3 rounded-lg border border-border/80 bg-card p-4 transition-colors hover:border-border hover:bg-muted/20"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-2">
                                    <button
                                        type="button"
                                        class="category-drag-handle mt-0.5 inline-flex size-7 items-center justify-center rounded-md border text-muted-foreground transition hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="dragDisabled"
                                        :aria-label="`Drag to reorder ${category.name}`"
                                    >
                                        <GripVertical class="size-4" />
                                    </button>
                                    <div>
                                        <p class="font-medium">
                                            {{ category.name }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Serial {{ category.position }}
                                        </p>
                                    </div>
                                </div>
                                <Badge
                                    :variant="
                                        category.is_active
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        category.is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </Badge>
                            </div>

                            <div
                                class="flex items-center justify-between rounded-md border border-border/70 px-3 py-2"
                            >
                                <p class="text-sm font-medium">Status</p>
                                <div class="flex items-center gap-2">
                                    <Switch
                                        :model-value="category.is_active"
                                        :disabled="
                                            isToggleProcessing(category.id)
                                        "
                                        @update:model-value="
                                            toggleCategoryStatus(
                                                category.id,
                                                $event,
                                            )
                                        "
                                    />
                                    <LoaderCircle
                                        v-if="isToggleProcessing(category.id)"
                                        class="size-4 animate-spin text-muted-foreground"
                                    />
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Badge variant="outline">
                                    Pages {{ category.pages_count }}
                                </Badge>
                            </div>

                            <p
                                v-if="category.pages_count > 0"
                                class="text-xs text-muted-foreground"
                            >
                                Delete disabled: category is assigned to pages.
                            </p>

                            <div class="grid grid-cols-2 gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="openEditDialog(category)"
                                >
                                    Edit
                                </Button>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    :disabled="category.pages_count > 0"
                                    :title="deleteDisabledReason(category)"
                                    @click="deleteCategory(category)"
                                >
                                    Delete
                                </Button>
                            </div>
                        </article>
                    </VueDraggable>

                    <div
                        v-if="categoryItems.length > 0"
                        class="hidden overflow-x-auto rounded-lg border border-border/80 md:block"
                    >
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-[72px]">Move</TableHead>
                                    <TableHead>Serial</TableHead>
                                    <TableHead class="min-w-[220px]"
                                        >Name</TableHead
                                    >
                                    <TableHead>Status</TableHead>
                                    <TableHead>Used By Pages</TableHead>
                                    <TableHead class="text-right"
                                        >Actions</TableHead
                                    >
                                </TableRow>
                            </TableHeader>

                            <VueDraggable
                                v-model="categoryItems"
                                item-key="id"
                                tag="tbody"
                                handle=".category-drag-handle"
                                :disabled="dragDisabled"
                                @start="onDragStart"
                                @end="onDragEnd"
                            >
                                <TableRow
                                    v-for="category in categoryItems"
                                    :key="category.id"
                                    class="hover:bg-muted/30"
                                >
                                    <TableCell>
                                        <button
                                            type="button"
                                            class="category-drag-handle inline-flex size-8 items-center justify-center rounded-md border text-muted-foreground transition hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
                                            :disabled="dragDisabled"
                                            :aria-label="`Drag to reorder ${category.name}`"
                                        >
                                            <GripVertical class="size-4" />
                                        </button>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline">
                                            {{ category.position }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <p class="font-medium">
                                            {{ category.name }}
                                        </p>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-3">
                                            <Switch
                                                :model-value="
                                                    category.is_active
                                                "
                                                :disabled="
                                                    isToggleProcessing(
                                                        category.id,
                                                    )
                                                "
                                                @update:model-value="
                                                    toggleCategoryStatus(
                                                        category.id,
                                                        $event,
                                                    )
                                                "
                                            />
                                            <LoaderCircle
                                                v-if="
                                                    isToggleProcessing(
                                                        category.id,
                                                    )
                                                "
                                                class="size-4 animate-spin text-muted-foreground"
                                            />
                                            <Badge
                                                :variant="
                                                    category.is_active
                                                        ? 'default'
                                                        : 'secondary'
                                                "
                                            >
                                                {{
                                                    category.is_active
                                                        ? 'Active'
                                                        : 'Inactive'
                                                }}
                                            </Badge>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline">
                                            {{ category.pages_count }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                @click="
                                                    openEditDialog(category)
                                                "
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                :disabled="
                                                    category.pages_count > 0
                                                "
                                                :title="
                                                    deleteDisabledReason(
                                                        category,
                                                    )
                                                "
                                                @click="
                                                    deleteCategory(category)
                                                "
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </VueDraggable>
                        </Table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="createDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create Category</DialogTitle>
                    <DialogDescription>
                        Add a new category for page mapping.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Name</label>
                        <Input
                            v-model="createForm.name"
                            placeholder="Category name"
                        />
                        <InputError :message="createForm.errors.name" />
                    </div>

                    <div
                        class="flex items-center justify-between rounded-md border p-3"
                    >
                        <div>
                            <p class="text-sm font-medium">Active</p>
                            <p class="text-xs text-muted-foreground">
                                Enabled categories are available for use.
                            </p>
                        </div>
                        <Switch v-model="createForm.is_active" />
                    </div>
                    <InputError :message="createForm.errors.is_active" />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="createDialogOpen = false">
                        Cancel
                    </Button>
                    <Button
                        :disabled="createForm.processing"
                        @click="submitCreate"
                    >
                        {{ createForm.processing ? 'Saving...' : 'Save' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="editDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit Category</DialogTitle>
                    <DialogDescription>
                        Update category details and status.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Name</label>
                        <Input
                            v-model="editForm.name"
                            placeholder="Category name"
                        />
                        <InputError :message="editForm.errors.name" />
                    </div>

                    <div
                        class="flex items-center justify-between rounded-md border p-3"
                    >
                        <div>
                            <p class="text-sm font-medium">Active</p>
                            <p class="text-xs text-muted-foreground">
                                Toggle visibility for this category.
                            </p>
                        </div>
                        <Switch v-model="editForm.is_active" />
                    </div>
                    <InputError :message="editForm.errors.is_active" />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="editDialogOpen = false">
                        Cancel
                    </Button>
                    <Button :disabled="editForm.processing" @click="submitEdit">
                        {{ editForm.processing ? 'Updating...' : 'Update' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ConfirmActionDialog
            :open="deleteDialogOpen"
            title="Delete category?"
            :description="
                pendingDeleteCategory === null
                    ? 'This action cannot be undone.'
                    : `Delete ${pendingDeleteCategory.name}? This action cannot be undone.`
            "
            confirm-text="Delete"
            confirm-variant="destructive"
            :processing="deleteProcessing"
            @update:open="onDeleteDialogOpenChange"
            @confirm="confirmDeleteCategory"
        />
    </EpAdminLayout>
</template>
