<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { AlertCircle, LoaderCircle, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
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
    uploadConstraints: {
        maxFileSizeKb: number;
        maxFiles: number;
        serverUploadMaxBytes: number;
        serverPostMaxBytes: number;
        serverMaxFileUploads: number;
    };
}>();

const emit = defineEmits<{
    (e: 'uploaded'): void;
}>();

type PageNoStrategy = 'auto' | 'filename' | 'next_available';
type ResolvedPageNoStrategy = 'filename' | 'next_available' | 'filename_invalid';

type UploadPreviewRow = {
    fileKey: string;
    fileName: string;
    fileSizeText: string;
    targetPageNo: number | null;
    replacesExisting: boolean;
};

const selectedFiles = ref<File[]>([]);
const fileInputRef = ref<HTMLInputElement | null>(null);
const fileInputKey = ref(0);
const isDropzoneActive = ref(false);
const selectionNotes = ref<string[]>([]);

const supportedExtensions = new Set(['jpg', 'jpeg', 'png', 'webp']);
const supportedMimeTypes = new Set([
    'image/jpg',
    'image/jpeg',
    'image/png',
    'image/webp',
]);

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
const configuredMaxFiles = computed(() => {
    const value = Number.isFinite(props.uploadConstraints.maxFiles)
        ? Math.trunc(props.uploadConstraints.maxFiles)
        : 200;

    return Math.max(1, value);
});
const configuredMaxFileSizeKb = computed(() => {
    const value = Number.isFinite(props.uploadConstraints.maxFileSizeKb)
        ? Math.trunc(props.uploadConstraints.maxFileSizeKb)
        : 15360;

    return Math.max(1, value);
});
const configuredMaxFileSizeBytes = computed(() => configuredMaxFileSizeKb.value * 1024);
const serverUploadMaxBytes = computed(() => {
    const value = Number.isFinite(props.uploadConstraints.serverUploadMaxBytes)
        ? Math.trunc(props.uploadConstraints.serverUploadMaxBytes)
        : 0;

    return Math.max(0, value);
});
const serverPostMaxBytes = computed(() => {
    const value = Number.isFinite(props.uploadConstraints.serverPostMaxBytes)
        ? Math.trunc(props.uploadConstraints.serverPostMaxBytes)
        : 0;

    return Math.max(0, value);
});
const serverMaxFileUploads = computed(() => {
    const value = Number.isFinite(props.uploadConstraints.serverMaxFileUploads)
        ? Math.trunc(props.uploadConstraints.serverMaxFileUploads)
        : 0;

    if (value <= 0) {
        return configuredMaxFiles.value;
    }

    return value;
});
const maxFilesAllowed = computed(() => Math.max(1, Math.min(configuredMaxFiles.value, serverMaxFileUploads.value)));
const maxFileSizeBytesAllowed = computed(() => {
    if (serverUploadMaxBytes.value <= 0) {
        return configuredMaxFileSizeBytes.value;
    }

    return Math.max(1, Math.min(configuredMaxFileSizeBytes.value, serverUploadMaxBytes.value));
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
        fileKey: fileFingerprint(file),
        fileName: file.name,
        fileSizeText: formatFileSize(file.size),
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

const hasSelectedFiles = computed(() => selectedFiles.value.length > 0);
const totalSelectedBytes = computed(() => selectedFiles.value.reduce((sum, file) => sum + file.size, 0));
const totalSelectedSizeText = computed(() => formatFileSize(totalSelectedBytes.value));
const selectedCountLabel = computed(() => `${selectedFiles.value.length} ${pluralize(selectedFiles.value.length, 'file', 'files')}`);
const hasConflicts = computed(() => conflictPageNumbers.value.length > 0);
const conflictPagePreview = computed(() => {
    if (!hasConflicts.value) {
        return '';
    }

    const preview = conflictPageNumbers.value.slice(0, 10);
    const suffix = conflictPageNumbers.value.length > preview.length ? ', ...' : '';

    return `${preview.join(', ')}${suffix}`;
});
const maxFileSizeLabel = computed(() => formatFileSize(maxFileSizeBytesAllowed.value));
const maxRequestSizeLabel = computed(() => (serverPostMaxBytes.value > 0 ? formatFileSize(serverPostMaxBytes.value) : 'Unknown'));
const runtimeLimitNotes = computed<string[]>(() => {
    const notes: string[] = [];

    if (serverPostMaxBytes.value > 0) {
        notes.push(`Server request limit: ${maxRequestSizeLabel.value}`);
    }

    if (serverUploadMaxBytes.value > 0) {
        notes.push(`Server per-file limit: ${formatFileSize(serverUploadMaxBytes.value)}`);
    }

    if (serverMaxFileUploads.value > 0) {
        notes.push(`Server max files/request: ${serverMaxFileUploads.value}`);
    }

    return notes;
});
const serverLimitErrors = computed<string[]>(() => {
    if (!hasSelectedFiles.value) {
        return [];
    }

    const errors: string[] = [];
    const oversizedFiles = selectedFiles.value.filter((file) => file.size > maxFileSizeBytesAllowed.value);

    if (oversizedFiles.length > 0) {
        errors.push(
            `${oversizedFiles.length} ${pluralize(oversizedFiles.length, 'file exceeds', 'files exceed')} the per-file server limit of ${maxFileSizeLabel.value}.`,
        );
    }

    if (serverPostMaxBytes.value > 0 && totalSelectedBytes.value > serverPostMaxBytes.value) {
        errors.push(
            `Selected batch size (${totalSelectedSizeText.value}) exceeds current server request limit (${maxRequestSizeLabel.value}).`,
        );
    }

    return errors;
});
const canSubmit = computed(() => {
    if (form.processing || selectedFiles.value.length === 0) {
        return false;
    }

    if (serverLimitErrors.value.length > 0) {
        return false;
    }

    return resolvedStrategy.value !== 'filename_invalid';
});
const progress = computed(() => Math.max(0, Math.min(100, Math.round(form.progress?.percentage ?? 0))));
const uploadButtonLabel = computed(() => (form.processing ? `Uploading ${progress.value}%` : 'Upload pages'));
const uploadErrors = computed(() => {
    const messages: string[] = [];

    for (const [key, message] of Object.entries(form.errors)) {
        if (
            (key === 'files' || key.startsWith('files.') || key === 'edition_id')
            && typeof message === 'string'
            && message.trim() !== ''
        ) {
            messages.push(message);
        }
    }

    return Array.from(new Set(messages));
});

function pluralize(count: number, singular: string, plural: string): string {
    return count === 1 ? singular : plural;
}

function fileFingerprint(file: File): string {
    return `${file.name}::${file.size}::${file.lastModified}`;
}

function formatFileSize(bytes: number): string {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '0 B';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let value = bytes;
    let unitIndex = 0;

    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }

    const precision = unitIndex === 0 ? 0 : 2;

    return `${value.toFixed(precision)} ${units[unitIndex]}`;
}

function extensionFromFileName(fileName: string): string {
    const lastDotIndex = fileName.lastIndexOf('.');

    if (lastDotIndex < 0 || lastDotIndex === fileName.length - 1) {
        return '';
    }

    return fileName.slice(lastDotIndex + 1).toLowerCase();
}

function isSupportedImage(file: File): boolean {
    const extension = extensionFromFileName(file.name);

    if (supportedExtensions.has(extension)) {
        return true;
    }

    return supportedMimeTypes.has(file.type.toLowerCase());
}

function mergeUniqueFiles(existingFiles: File[], incomingFiles: File[]): { files: File[]; duplicatesSkipped: number } {
    const entries = new Map<string, File>();

    for (const file of existingFiles) {
        entries.set(fileFingerprint(file), file);
    }

    let duplicatesSkipped = 0;

    for (const file of incomingFiles) {
        const key = fileFingerprint(file);

        if (entries.has(key)) {
            duplicatesSkipped += 1;
            continue;
        }

        entries.set(key, file);
    }

    return {
        files: Array.from(entries.values()),
        duplicatesSkipped,
    };
}

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

function applySelectedFiles(files: File[], options?: { append?: boolean }): void {
    const append = options?.append === true;

    if (append && files.length === 0) {
        return;
    }

    const merged = append ? mergeUniqueFiles(selectedFiles.value, files) : { files, duplicatesSkipped: 0 };

    const accepted: File[] = [];
    const unsupported: string[] = [];

    for (const file of merged.files) {
        if (isSupportedImage(file)) {
            accepted.push(file);
            continue;
        }

        unsupported.push(file.name);
    }

    let trimmedCount = 0;
    let normalizedFiles = accepted;

    if (accepted.length > maxFilesAllowed.value) {
        trimmedCount = accepted.length - maxFilesAllowed.value;
        normalizedFiles = accepted.slice(0, maxFilesAllowed.value);
    }

    selectedFiles.value = normalizedFiles;
    form.files = normalizedFiles;
    clearUploadErrors();

    const notes: string[] = [];

    if (merged.duplicatesSkipped > 0) {
        notes.push(`${merged.duplicatesSkipped} duplicate ${pluralize(merged.duplicatesSkipped, 'file was', 'files were')} skipped.`);
    }

    if (unsupported.length > 0) {
        notes.push(`${unsupported.length} unsupported ${pluralize(unsupported.length, 'file was', 'files were')} ignored. Use JPG, JPEG, PNG, or WEBP.`);
    }

    if (trimmedCount > 0) {
        notes.push(`Only the first ${maxFilesAllowed.value} files were kept for this upload.`);
    }

    selectionNotes.value = notes;
}

function clearUploadErrors(): void {
    form.clearErrors();
}

function onFilesChanged(event: Event): void {
    if (form.processing) {
        return;
    }

    const input = event.target as HTMLInputElement;
    applySelectedFiles(Array.from(input.files ?? []), { append: true });
    input.value = '';
}

function openFilePicker(): void {
    if (form.processing) {
        return;
    }

    fileInputRef.value?.click();
}

function onDropzoneDragEnter(): void {
    if (form.processing) {
        return;
    }

    isDropzoneActive.value = true;
}

function onDropzoneDragOver(event: DragEvent): void {
    if (form.processing) {
        return;
    }

    isDropzoneActive.value = true;

    if (event.dataTransfer !== null) {
        event.dataTransfer.dropEffect = 'copy';
    }
}

function onDropzoneDragLeave(event: DragEvent): void {
    if (form.processing) {
        return;
    }

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
    if (form.processing) {
        return;
    }

    isDropzoneActive.value = false;
    applySelectedFiles(Array.from(event.dataTransfer?.files ?? []), { append: true });
}

function clearSelectedFiles(): void {
    if (form.processing) {
        return;
    }

    applySelectedFiles([]);
    selectionNotes.value = [];
    fileInputKey.value += 1;
}

function removeSelectedFile(fileKey: string): void {
    if (form.processing) {
        return;
    }

    const remaining = selectedFiles.value.filter((file) => fileFingerprint(file) !== fileKey);
    applySelectedFiles(remaining);
    fileInputKey.value += 1;
}

function submit(): void {
    if (!canSubmit.value) {
        return;
    }

    if (props.editionId <= 0) {
        form.setError('edition_id', 'Select an edition before uploading.');
        return;
    }

    form.edition_id = props.editionId;
    form.files = orderedFilesForUpload.value;
    clearUploadErrors();
    selectionNotes.value = [];

    form.post('/admin/pages/upload', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('files');
            selectedFiles.value = [];
            selectionNotes.value = [];
            fileInputKey.value += 1;
            emit('uploaded');
        },
    });
}
</script>

<template>
    <div class="space-y-4 rounded-xl border border-border/70 bg-card p-4 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="space-y-1">
                <h3 class="text-base font-semibold">Upload pages</h3>
                <p class="text-xs text-muted-foreground">
                    Drop images, preview target page numbers, and check replacement conflicts before upload.
                </p>
            </div>
            <Badge :variant="form.processing ? 'secondary' : 'outline'" class="capitalize">
                {{ form.processing ? 'Uploading in progress' : 'Ready for upload' }}
            </Badge>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
            <div class="space-y-2">
                <label class="text-sm font-medium">Page number strategy</label>
                <Select v-model="form.page_no_strategy">
                    <SelectTrigger :disabled="form.processing">
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
                    <SelectTrigger :disabled="form.processing">
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
            accept="image/*"
            multiple
            @change="onFilesChanged"
        />

        <div
            role="button"
            tabindex="0"
            class="rounded-xl border-2 border-dashed px-4 py-8 text-center transition-colors"
            :class="isDropzoneActive
                ? 'border-primary bg-primary/10'
                : 'border-slate-300 bg-slate-50/80 hover:border-primary/70 hover:bg-primary/5'"
            :aria-disabled="form.processing"
            @click="openFilePicker"
            @keydown.enter.prevent="openFilePicker"
            @keydown.space.prevent="openFilePicker"
            @dragenter.prevent="onDropzoneDragEnter"
            @dragover.prevent="onDropzoneDragOver"
            @dragleave.prevent="onDropzoneDragLeave"
            @drop.prevent="onDropzoneDrop"
        >
            <p class="text-sm font-semibold">
                {{ isDropzoneActive ? 'Release to add files' : 'Drop image files here or click to browse' }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                Supported: JPG, JPEG, PNG, WEBP · Max {{ maxFileSizeLabel }} each · Up to {{ maxFilesAllowed }} files · Request up to {{ maxRequestSizeLabel }}
            </p>

            <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                <Button
                    size="sm"
                    variant="secondary"
                    :disabled="form.processing"
                    @click.stop="openFilePicker"
                >
                    Browse files
                </Button>
                <Button
                    v-if="hasSelectedFiles"
                    size="sm"
                    variant="outline"
                    :disabled="form.processing"
                    @click.stop="clearSelectedFiles"
                >
                    Clear all
                </Button>
            </div>
        </div>

        <p class="text-[11px] text-muted-foreground">
            Tip: drag and drop multiple times to keep adding files to the same upload batch.
        </p>

        <Alert v-if="runtimeLimitNotes.length > 0">
            <AlertCircle class="size-4" />
            <AlertTitle>Current server limits</AlertTitle>
            <AlertDescription>
                <ul class="list-disc pl-4">
                    <li v-for="note in runtimeLimitNotes" :key="note">
                        {{ note }}
                    </li>
                </ul>
            </AlertDescription>
        </Alert>

        <Alert v-if="selectionNotes.length > 0" class="border-amber-500/40 text-amber-800">
            <AlertCircle class="size-4" />
            <AlertTitle>Selection notice</AlertTitle>
            <AlertDescription>
                <ul class="list-disc pl-4">
                    <li v-for="note in selectionNotes" :key="note">
                        {{ note }}
                    </li>
                </ul>
            </AlertDescription>
        </Alert>

        <Alert v-if="serverLimitErrors.length > 0" variant="destructive">
            <AlertCircle class="size-4" />
            <AlertTitle>Selection exceeds server limits</AlertTitle>
            <AlertDescription>
                <ul class="list-disc pl-4">
                    <li v-for="error in serverLimitErrors" :key="error">
                        {{ error }}
                    </li>
                </ul>
            </AlertDescription>
        </Alert>

        <Alert v-if="hasConflicts" class="border-amber-500/40 text-amber-800">
            <AlertCircle class="size-4" />
            <AlertTitle>Existing pages will be replaced</AlertTitle>
            <AlertDescription>
                Conflicting page numbers: {{ conflictPagePreview }}
            </AlertDescription>
        </Alert>

        <div v-if="hasSelectedFiles" class="space-y-3">
            <div class="grid gap-2 sm:grid-cols-4">
                <div class="rounded-md border border-border/70 bg-muted/20 p-3 text-xs">
                    <p class="font-medium text-foreground">Selected files</p>
                    <p class="mt-1 text-muted-foreground">{{ selectedCountLabel }}</p>
                </div>
                <div class="rounded-md border border-border/70 bg-muted/20 p-3 text-xs">
                    <p class="font-medium text-foreground">Total size</p>
                    <p class="mt-1 text-muted-foreground">{{ totalSelectedSizeText }}</p>
                </div>
                <div class="rounded-md border border-border/70 bg-muted/20 p-3 text-xs">
                    <p class="font-medium text-foreground">Effective strategy</p>
                    <p class="mt-1 text-muted-foreground">{{ strategySummary }}</p>
                </div>
                <div class="rounded-md border border-border/70 bg-muted/20 p-3 text-xs">
                    <p class="font-medium text-foreground">Target range</p>
                    <p class="mt-1 text-muted-foreground">{{ targetRangeSummary ?? 'Unavailable' }}</p>
                </div>
            </div>

            <p v-if="resolvedStrategy === 'filename_invalid'" class="text-sm text-destructive">
                Filename strategy requires all files to start with a numeric prefix (for example: 01.jpg).
            </p>

            <ul class="max-h-64 space-y-1 overflow-y-auto rounded-md border border-border/70 bg-muted/20 p-2 text-xs">
                <li
                    v-for="row in previewRows"
                    :key="row.fileKey"
                    class="flex items-start gap-2 rounded border border-border/50 bg-background px-2 py-2"
                >
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ row.fileName }}</p>
                        <p class="text-muted-foreground">{{ row.fileSizeText }}</p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="font-medium">
                            {{ row.targetPageNo === null ? 'N/A' : `Page ${row.targetPageNo}` }}
                        </p>
                        <Badge :variant="row.replacesExisting ? 'destructive' : 'secondary'" class="mt-1">
                            {{ row.replacesExisting ? 'Replace' : 'New' }}
                        </Badge>
                    </div>
                    <Button
                        size="icon-sm"
                        variant="ghost"
                        :disabled="form.processing"
                        @click="removeSelectedFile(row.fileKey)"
                    >
                        <Trash2 class="size-4" />
                        <span class="sr-only">Remove file</span>
                    </Button>
                </li>
            </ul>
        </div>

        <Alert v-if="uploadErrors.length > 0" variant="destructive">
            <AlertCircle class="size-4" />
            <AlertTitle>Upload failed</AlertTitle>
            <AlertDescription>
                <ul class="list-disc pl-4">
                    <li v-for="error in uploadErrors" :key="error">
                        {{ error }}
                    </li>
                </ul>
            </AlertDescription>
        </Alert>

        <div v-if="form.progress" class="space-y-2 rounded-md border border-border/70 bg-muted/20 p-3">
            <div class="flex items-center justify-between text-xs text-muted-foreground">
                <p class="inline-flex items-center gap-1">
                    <LoaderCircle class="size-3.5 animate-spin" />
                    Uploading files
                </p>
                <p>{{ progress }}%</p>
            </div>
            <div class="h-2 w-full overflow-hidden rounded bg-muted">
                <div class="h-full bg-primary transition-all" :style="{ width: `${progress}%` }" />
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-2">
            <Button class="w-full sm:w-auto" :disabled="!canSubmit" @click="submit">
                <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                {{ uploadButtonLabel }}
            </Button>
        </div>
    </div>
</template>
