<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
}>();

const emit = defineEmits<{
    (e: 'uploaded'): void;
}>();

type PageNoStrategy = 'auto' | 'filename' | 'next_available';

const selectedFiles = ref<File[]>([]);
const fileInputKey = ref(0);
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

const progress = computed(() => form.progress?.percentage ?? 0);
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

function onFilesChanged(event: Event): void {
    const input = event.target as HTMLInputElement;
    selectedFiles.value = Array.from(input.files ?? []);
    form.files = selectedFiles.value;
}

function submit(): void {
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
                You can upload multiple page images at once and assign a category.
            </p>
        </div>

        <div class="grid gap-3 md:grid-cols-3">
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

            <div class="space-y-2">
                <label class="text-sm font-medium">Images</label>
                <Input
                    :key="fileInputKey"
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    multiple
                    @change="onFilesChanged"
                />
            </div>
        </div>

        <div v-if="selectedFiles.length" class="space-y-2">
            <p class="text-sm font-medium">Selected files ({{ selectedFiles.length }})</p>
            <ul class="max-h-32 space-y-1 overflow-y-auto rounded-md border border-border/70 bg-muted/30 p-2 text-xs text-muted-foreground">
                <li v-for="file in selectedFiles" :key="file.name + file.size" class="truncate">
                    {{ file.name }}
                </li>
            </ul>
        </div>

        <div v-if="form.progress" class="space-y-2">
            <div class="h-2 w-full overflow-hidden rounded bg-muted">
                <div class="h-full bg-primary transition-all" :style="{ width: `${progress}%` }" />
            </div>
            <p class="text-xs text-muted-foreground">Uploading {{ progress }}%</p>
        </div>

        <div class="flex justify-end">
            <Button class="w-full sm:w-auto" :disabled="form.processing || selectedFiles.length === 0" @click="submit">
                Upload pages
            </Button>
        </div>
    </div>
</template>
