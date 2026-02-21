<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Category } from '@/types';

const props = defineProps<{
    editionId: number;
    categories: Category[];
    existingPageNumbers: number[];
}>();

const emit = defineEmits<{
    (e: 'uploaded'): void;
}>();

type PageNoStrategy = 'auto' | 'filename' | 'next_available';
type ResolvedPageNoStrategy = 'filename' | 'next_available' | 'filename_invalid';

type UploadPreviewRow = {
    key: string;
    fileName: string;
    fileSizeText: string;
    targetPageNo: number | null;
    replacesExisting: boolean;
};

const selectedFiles = ref<File[]>([]);
const fileInputRef = ref<HTMLInputElement | null>(null);
const fileInputKey = ref(0);
const isDropzoneActive = ref(false);

const form = useForm<{
    edition_id: number;
    category_id: number | null;
    page_no_strategy: PageNoStrategy;
    files: File[];
}>({
    edition_id: props.editionId,
    category_id: null,
    page_no_strategy: 'auto',
    files: [],
});

const selectedCategoryValue = computed<string>({
    get: () => {
        if (
            typeof form.category_id !== 'number'
            || !Number.isFinite(form.category_id)
            || form.category_id <= 0
        ) {
            return 'none';
        }

        return String(form.category_id);
    },
    set: (value: string) => {
        if (value === 'none') {
            form.category_id = null;
            return;
        }

        const categoryId = Number.parseInt(value, 10);
        form.category_id = Number.isFinite(categoryId) && categoryId > 0 ? categoryId : null;
    },
});

const existingPageNumberSet = computed(() => new Set(props.existingPageNumbers));
const maxExistingPageNo = computed(() => {
    if (props.existingPageNumbers.length === 0) {
        return 0;
    }

    return Math.max(...props.existingPageNumbers);
});

const allFilesHaveNumericPrefix = computed(() => (
    selectedFiles.value.length > 0
    && selectedFiles.value.every((file) => leadingNumberFromFileName(file.name) !== null)
));

const resolvedStrategy = computed<ResolvedPageNoStrategy>(() => {
    if (form.page_no_strategy === 'next_available') {
        return 'next_available';
    }

    if (form.page_no_strategy === 'filename') {
        return allFilesHaveNumericPrefix.value ? 'filename' : 'filename_invalid';
    }

    return allFilesHaveNumericPrefix.value ? 'filename' : 'next_available';
});

const orderedFilesForUpload = computed<File[]>(() => {
    if (resolvedStrategy.value === 'filename') {
        return sortFilesByFilenamePrefix(selectedFiles.value);
    }

    return [...selectedFiles.value];
});

const targetPageNumbers = computed<number[]>(() => {
    if (orderedFilesForUpload.value.length === 0) {
        return [];
    }

    if (resolvedStrategy.value === 'filename_invalid') {
        return [];
    }

    if (resolvedStrategy.value === 'filename') {
        const firstNumber = leadingNumberFromFileName(orderedFilesForUpload.value[0]?.name ?? '') ?? 1;
        const startPageNo = Math.max(1, firstNumber);

        return orderedFilesForUpload.value.map((_, index) => startPageNo + index);
    }

    const startPageNo = maxExistingPageNo.value + 1;
    return orderedFilesForUpload.value.map((_, index) => startPageNo + index);
});

const conflictPageNumbers = computed<number[]>(() => targetPageNumbers.value
    .filter((pageNo) => existingPageNumberSet.value.has(pageNo)));

const previewRows = computed<UploadPreviewRow[]>(() => orderedFilesForUpload.value.map((file, index) => {
    const targetPageNo = targetPageNumbers.value[index] ?? null;
    const replacesExisting = targetPageNo !== null && existingPageNumberSet.value.has(targetPageNo);

    return {
        key: `${file.name}-${file.size}-${index}`,
        fileName: file.name,
        fileSizeText: `${(file.size / 1024 / 1024).toFixed(2)} MB`,
        targetPageNo,
        replacesExisting,
    };
}));

const strategySummary = computed(() => {
    if (resolvedStrategy.value === 'filename') {
        return 'Filename prefix order';
    }

    if (resolvedStrategy.value === 'next_available') {
        return 'Next available page numbers';
    }

    return 'Filename prefixes required';
});

const targetRangeSummary = computed(() => {
    if (targetPageNumbers.value.length === 0) {
        return null;
    }

    const first = targetPageNumbers.value[0];
    const last = targetPageNumbers.value[targetPageNumbers.value.length - 1];

    if (first === undefined || last === undefined) {
        return null;
    }

    if (first === last) {
        return `Page ${first}`;
    }

    return `Pages ${first} to ${last}`;
});

const canSubmit = computed(() => {
    if (form.processing || selectedFiles.value.length === 0) {
        return false;
    }

    return resolvedStrategy.value !== 'filename_invalid';
});

const progress = computed(() => form.progress?.percentage ?? 0);

function leadingNumberFromFileName(fileName: string): number | null {
    const lastDotIndex = fileName.lastIndexOf('.');
    const baseName = lastDotIndex > 0 ? fileName.slice(0, lastDotIndex) : fileName;
    const match = /^(\d+)/.exec(baseName);

    if (match === null) {
        return null;
    }

    return Number.parseInt(match[1], 10);
}

function sortFilesByFilenamePrefix(files: File[]): File[] {
    return [...files].sort((left, right) => {
        const leftNumber = leadingNumberFromFileName(left.name);
        const rightNumber = leadingNumberFromFileName(right.name);

        if (leftNumber !== rightNumber) {
            return (leftNumber ?? Number.MAX_SAFE_INTEGER) - (rightNumber ?? Number.MAX_SAFE_INTEGER);
        }

        return left.name.localeCompare(right.name, undefined, { sensitivity: 'base' });
    });
}

function applySelectedFiles(files: File[]): void {
    selectedFiles.value = files;
    form.files = files;
    form.clearErrors('files');
}

function onFilesChanged(event: Event): void {
    const input = event.target as HTMLInputElement;
    applySelectedFiles(Array.from(input.files ?? []));
}

function openFilePicker(): void {
    fileInputRef.value?.click();
}

function onDropzoneDragEnter(): void {
    isDropzoneActive.value = true;
}

function onDropzoneDragOver(event: DragEvent): void {
    isDropzoneActive.value = true;

    if (event.dataTransfer !== null) {
        event.dataTransfer.dropEffect = 'copy';
    }
}

function onDropzoneDragLeave(event: DragEvent): void {
    const currentTarget = event.currentTarget;
    const relatedTarget = event.relatedTarget;

    if (
        currentTarget instanceof HTMLElement
        && relatedTarget instanceof Node
        && currentTarget.contains(relatedTarget)
    ) {
        return;
    }

    isDropzoneActive.value = false;
}

function onDropzoneDrop(event: DragEvent): void {
    isDropzoneActive.value = false;
    applySelectedFiles(Array.from(event.dataTransfer?.files ?? []));
}

function clearSelectedFiles(): void {
    applySelectedFiles([]);
    fileInputKey.value += 1;
}

function submit(): void {
    if (!canSubmit.value) {
        return;
    }

    form.edition_id = props.editionId;

    form.post('/admin/pages/upload', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('files');
            selectedFiles.value = [];
            fileInputKey.value += 1;
            emit('uploaded');
        },
    });
}
</script>

<template>
    <div class="space-y-4 rounded-xl border border-border/70 bg-card p-4 shadow-sm">
        <div class="space-y-1">
            <h3 class="text-base font-semibold">Upload pages</h3>
            <p class="text-xs text-muted-foreground">
                Drop images, preview target page numbers, and check replacement conflicts before upload.
            </p>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            <div class="space-y-2">
                <label class="text-sm font-medium">Page number strategy</label>
                <Select v-model="form.page_no_strategy">
                    <SelectTrigger>
                        <SelectValue placeholder="Select strategy" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="auto">Auto</SelectItem>
                        <SelectItem value="filename">Filename (01.jpg, 02.jpg)</SelectItem>
                        <SelectItem value="next_available">Next available</SelectItem>
                    </SelectContent>
                </Select>
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
                <p v-if="form.errors.category_id" class="text-xs text-destructive">
                    {{ form.errors.category_id }}
                </p>
            </div>
        </div>

        <input
            :key="fileInputKey"
            ref="fileInputRef"
            type="file"
            class="sr-only"
            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
            multiple
            @change="onFilesChanged"
        />

        <div
            role="button"
            tabindex="0"
            class="rounded-xl border-2 border-dashed px-4 py-8 text-center transition-colors"
            :class="isDropzoneActive
                ? 'border-primary bg-primary/5'
                : 'border-slate-300 bg-slate-50/80 hover:border-primary/70 hover:bg-primary/5'"
            @click="openFilePicker"
            @keydown.enter.prevent="openFilePicker"
            @keydown.space.prevent="openFilePicker"
            @dragenter.prevent="onDropzoneDragEnter"
            @dragover.prevent="onDropzoneDragOver"
            @dragleave.prevent="onDropzoneDragLeave"
            @drop.prevent="onDropzoneDrop"
        >
            <p class="text-sm font-medium">Drop image files here or click to browse</p>
            <p class="mt-1 text-xs text-muted-foreground">
                Supported: JPG, PNG, WEBP
            </p>
        </div>

        <div v-if="selectedFiles.length > 0" class="space-y-3">
            <div class="grid gap-2 sm:grid-cols-3">
                <div class="rounded-md border border-border/70 bg-muted/20 p-3 text-xs">
                    <p class="font-medium text-foreground">Effective strategy</p>
                    <p class="mt-1 text-muted-foreground">{{ strategySummary }}</p>
                </div>
                <div class="rounded-md border border-border/70 bg-muted/20 p-3 text-xs">
                    <p class="font-medium text-foreground">Target range</p>
                    <p class="mt-1 text-muted-foreground">{{ targetRangeSummary ?? 'Unavailable' }}</p>
                </div>
                <div class="rounded-md border border-border/70 bg-muted/20 p-3 text-xs">
                    <p class="font-medium text-foreground">Conflicts</p>
                    <p class="mt-1 text-muted-foreground">
                        {{ conflictPageNumbers.length }}
                        <span v-if="conflictPageNumbers.length > 0">
                            (replace: {{ conflictPageNumbers.join(', ') }})
                        </span>
                    </p>
                </div>
            </div>

            <p v-if="resolvedStrategy === 'filename_invalid'" class="text-sm text-destructive">
                Filename strategy requires all files to start with a numeric prefix (for example: 01.jpg).
            </p>

            <ul class="max-h-56 space-y-1 overflow-y-auto rounded-md border border-border/70 bg-muted/20 p-2 text-xs">
                <li
                    v-for="row in previewRows"
                    :key="row.key"
                    class="flex items-center justify-between gap-2 rounded px-2 py-1"
                    :class="row.replacesExisting ? 'bg-amber-100/80 text-amber-900' : 'bg-background'"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ row.fileName }}</p>
                        <p class="text-muted-foreground">{{ row.fileSizeText }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="font-medium">
                            {{ row.targetPageNo === null ? 'N/A' : `Page ${row.targetPageNo}` }}
                        </p>
                        <p v-if="row.replacesExisting" class="text-[11px] font-semibold uppercase tracking-wide">
                            Replace
                        </p>
                    </div>
                </li>
            </ul>
        </div>

        <p v-if="form.errors.files" class="text-sm text-destructive">
            {{ form.errors.files }}
        </p>

        <div v-if="form.progress" class="space-y-2">
            <div class="h-2 w-full overflow-hidden rounded bg-muted">
                <div class="h-full bg-primary transition-all" :style="{ width: `${progress}%` }" />
            </div>
            <p class="text-xs text-muted-foreground">Uploading {{ progress }}%</p>
        </div>

        <div class="flex flex-wrap justify-end gap-2">
            <Button
                v-if="selectedFiles.length > 0"
                variant="outline"
                :disabled="form.processing"
                @click="clearSelectedFiles"
            >
                Clear files
            </Button>
            <Button class="w-full sm:w-auto" :disabled="!canSubmit" @click="submit">
                Upload pages
            </Button>
        </div>
    </div>
</template>
