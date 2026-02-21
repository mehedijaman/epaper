<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import EpAdminLayout from '@/layouts/EpAdminLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Category = {
    id: number;
    name: string;
    position: number;
    is_active: boolean | number | string | null;
    pages_count: number;
};

type CategoryPayload = {
    name: string;
    position: number;
    is_active: boolean;
};

const props = defineProps<{
    categories: Category[];
}>();

const page = usePage<{
    errors?: {
        category?: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Categories', href: '/admin/categories' },
];

const createDialogOpen = ref(false);
const editDialogOpen = ref(false);
const editingCategoryId = ref<number | null>(null);

const createForm = useForm<CategoryPayload>({
    name: '',
    position: 1,
    is_active: true,
});

const editForm = useForm<CategoryPayload>({
    name: '',
    position: 1,
    is_active: true,
});

function normalizeBoolean(value: boolean | number | string | null | undefined): boolean {
    if (typeof value === 'boolean') {
        return value;
    }

    if (typeof value === 'number') {
        return value === 1;
    }

    if (typeof value === 'string') {
        const normalized = value.trim().toLowerCase();
        return normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'on';
    }

    return false;
}

const deleteError = computed(() => page.props.errors?.category ?? '');
const sortedCategories = computed(() =>
    [...props.categories]
        .map((category) => ({
            ...category,
            is_active: normalizeBoolean(category.is_active),
        }))
        .sort((a, b) => a.position - b.position),
);
const totalCategories = computed(() => sortedCategories.value.length);
const activeCategories = computed(() => sortedCategories.value.filter((item) => item.is_active).length);
const mappedPagesCount = computed(() => sortedCategories.value.reduce((sum, item) => sum + item.pages_count, 0));
const createActiveChecked = computed<boolean | 'indeterminate'>({
    get: () => normalizeBoolean(createForm.is_active),
    set: (value) => {
        createForm.is_active = value === true;
    },
});
const editActiveChecked = computed<boolean | 'indeterminate'>({
    get: () => normalizeBoolean(editForm.is_active),
    set: (value) => {
        editForm.is_active = value === true;
    },
});

function resetCreateForm(): void {
    createForm.reset();
    createForm.clearErrors();
    createForm.position = 1;
    createForm.is_active = true;
}

function openCreateDialog(): void {
    resetCreateForm();
    createDialogOpen.value = true;
}

function submitCreate(): void {
    createForm.is_active = normalizeBoolean(createForm.is_active);
    createForm.post('/admin/categories', {
        preserveScroll: true,
        onSuccess: () => {
            createDialogOpen.value = false;
            resetCreateForm();
        },
    });
}

function openEditDialog(category: Category): void {
    editingCategoryId.value = category.id;
    editForm.name = category.name;
    editForm.position = category.position;
    editForm.is_active = normalizeBoolean(category.is_active);
    editForm.clearErrors();
    editDialogOpen.value = true;
}

function submitEdit(): void {
    if (editingCategoryId.value === null) {
        return;
    }

    editForm.is_active = normalizeBoolean(editForm.is_active);
    editForm.put(`/admin/categories/${editingCategoryId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            editDialogOpen.value = false;
            editingCategoryId.value = null;
        },
    });
}

function deleteCategory(category: Category): void {
    if (category.pages_count > 0) {
        return;
    }

    if (!window.confirm('Delete this category?')) {
        return;
    }

    router.delete(`/admin/categories/${category.id}`, {
        preserveScroll: true,
    });
}

function deleteDisabledReason(category: Category): string | undefined {
    if (category.pages_count > 0) {
        return 'Cannot delete a category that is assigned to pages.';
    }

    return undefined;
}
</script>

<template>
    <Head title="Categories" />

    <EpAdminLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-7xl space-y-5 p-4 sm:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div class="space-y-1">
                    <h1 class="text-2xl font-semibold tracking-tight">Categories</h1>
                    <p class="text-sm text-muted-foreground">
                        Organize pages with category positions and availability status.
                    </p>
                </div>
                <Button class="w-full sm:w-auto" @click="openCreateDialog">Create Category</Button>
            </div>

            <InputError :message="deleteError" />

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-border/70 bg-card p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Total Categories</p>
                    <p class="mt-2 text-2xl font-semibold">{{ totalCategories }}</p>
                </div>
                <div class="rounded-lg border border-border/70 bg-card p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Active Categories</p>
                    <p class="mt-2 text-2xl font-semibold">{{ activeCategories }}</p>
                </div>
                <div class="rounded-lg border border-border/70 bg-card p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Mapped Pages</p>
                    <p class="mt-2 text-2xl font-semibold">{{ mappedPagesCount }}</p>
                </div>
            </div>

            <div
                v-if="sortedCategories.length === 0"
                class="rounded-lg border border-border/70 bg-card py-10 text-center text-muted-foreground"
            >
                No categories found. Create your first category to start mapping pages.
            </div>

            <div v-else class="space-y-3 sm:hidden">
                <article
                    v-for="category in sortedCategories"
                    :key="category.id"
                    class="space-y-3 rounded-lg border border-border/70 bg-card p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-medium">{{ category.name }}</p>
                        <Badge :variant="category.is_active ? 'default' : 'secondary'">
                            {{ category.is_active ? 'Active' : 'Inactive' }}
                        </Badge>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Badge variant="outline">Position {{ category.position }}</Badge>
                        <Badge variant="outline">Pages {{ category.pages_count }}</Badge>
                    </div>
                    <p v-if="category.pages_count > 0" class="text-xs text-muted-foreground">
                        Delete disabled: category is assigned to pages.
                    </p>
                    <div class="grid grid-cols-2 gap-2">
                        <Button size="sm" variant="outline" @click="openEditDialog(category)">
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
            </div>

            <div v-if="sortedCategories.length > 0" class="hidden overflow-x-auto rounded-lg border border-border/70 bg-card sm:block">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="min-w-[220px]">Name</TableHead>
                            <TableHead>Position</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Used By Pages</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="category in sortedCategories" :key="category.id">
                            <TableCell>
                                <p class="font-medium">{{ category.name }}</p>
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline">{{ category.position }}</Badge>
                            </TableCell>
                            <TableCell>
                                <Badge :variant="category.is_active ? 'default' : 'secondary'">
                                    {{ category.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline">{{ category.pages_count }}</Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-2">
                                    <Button size="sm" variant="outline" @click="openEditDialog(category)">
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
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <Dialog v-model:open="createDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create Category</DialogTitle>
                    <DialogDescription>
                        Add a new category and define its display position.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Name</label>
                        <Input v-model="createForm.name" placeholder="Category name" />
                        <InputError :message="createForm.errors.name" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Position</label>
                        <Input v-model.number="createForm.position" type="number" min="1" />
                        <InputError :message="createForm.errors.position" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox v-model:checked="createActiveChecked" />
                        <span class="text-sm">{{ createForm.is_active ? 'Active' : 'Inactive' }}</span>
                    </div>
                    <InputError :message="createForm.errors.is_active" />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="createDialogOpen = false">Cancel</Button>
                    <Button :disabled="createForm.processing" @click="submitCreate">
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
                        <Input v-model="editForm.name" placeholder="Category name" />
                        <InputError :message="editForm.errors.name" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Position</label>
                        <Input v-model.number="editForm.position" type="number" min="1" />
                        <InputError :message="editForm.errors.position" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox v-model:checked="editActiveChecked" />
                        <span class="text-sm">{{ editForm.is_active ? 'Active' : 'Inactive' }}</span>
                    </div>
                    <InputError :message="editForm.errors.is_active" />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="editDialogOpen = false">Cancel</Button>
                    <Button :disabled="editForm.processing" @click="submitEdit">
                        {{ editForm.processing ? 'Updating...' : 'Update' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </EpAdminLayout>
</template>
