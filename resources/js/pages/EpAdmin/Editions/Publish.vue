<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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
import EpAdminLayout from '@/layouts/EpAdminLayout.vue';
import type { BreadcrumbItem } from '@/types';

type EditionSummary = {
    id: number;
    edition_date: string;
    status: 'draft' | 'published';
    published_at: string | null;
    pages_count: number;
};

type Props = {
    date: string;
    date_error: string | null;
    edition: EditionSummary | null;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Publish Editions', href: '/admin/editions/publish' },
];

type PublishAction = 'publish' | 'unpublish';

const date = ref(props.date);
const confirmOpen = ref(false);
const pendingAction = ref<PublishAction>('publish');

const form = useForm<{
    edition_id: number;
}>({
    edition_id: 0,
});

watch(
    () => props.date,
    (value) => {
        date.value = value;
    },
);

const hasEdition = computed(() => props.edition !== null);
const isPublished = computed(() => props.edition?.status === 'published');
const statusBadgeVariant = computed(() => (isPublished.value ? 'default' : 'secondary'));
const actionLabel = computed(() => (pendingAction.value === 'publish' ? 'Publish' : 'Unpublish'));
const actionUrl = computed(() =>
    pendingAction.value === 'publish'
        ? '/admin/editions/publish'
        : '/admin/editions/unpublish',
);
const publishedAtText = computed(() => {
    const value = props.edition?.published_at;

    if (value === null || value === undefined) {
        return null;
    }

    return new Date(value).toLocaleString();
});

function searchByDate(): void {
    router.get(
        '/admin/editions/publish',
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

function openConfirm(action: PublishAction): void {
    if (props.edition === null) {
        return;
    }

    pendingAction.value = action;
    confirmOpen.value = true;
}

function submitStatusChange(): void {
    if (props.edition === null) {
        return;
    }

    form.edition_id = props.edition.id;
    form.post(actionUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            confirmOpen.value = false;
        },
    });
}
</script>

<template>
    <Head title="Publish Editions" />
    <EpAdminLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-4 p-4">
            <div class="space-y-2">
                <h1 class="text-xl font-semibold">Publish Editions</h1>
                <p class="text-sm text-muted-foreground">
                    Search an edition date and publish or unpublish it.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Search edition by date</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <Input v-model="date" type="date" class="sm:max-w-xs" />
                        <Button @click="searchByDate">Search</Button>
                    </div>
                    <p v-if="props.date_error" class="text-sm text-destructive">
                        {{ props.date_error }}
                    </p>
                </CardContent>
            </Card>

            <Card v-if="hasEdition">
                <CardHeader>
                    <CardTitle class="flex flex-wrap items-center gap-2">
                        <span>Edition {{ props.edition?.edition_date }}</span>
                        <Badge :variant="statusBadgeVariant">
                            {{ props.edition?.status }}
                        </Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p class="text-sm text-muted-foreground">
                        Pages count: {{ props.edition?.pages_count ?? 0 }}
                    </p>
                    <p v-if="publishedAtText" class="text-sm text-muted-foreground">
                        Published at: {{ publishedAtText }}
                    </p>

                    <Button
                        v-if="isPublished"
                        variant="destructive"
                        :disabled="form.processing"
                        @click="openConfirm('unpublish')"
                    >
                        Unpublish Edition
                    </Button>
                    <Button
                        v-else
                        :disabled="form.processing"
                        @click="openConfirm('publish')"
                    >
                        Publish Edition
                    </Button>
                </CardContent>
            </Card>

            <Card v-else>
                <CardContent class="py-6 text-sm text-muted-foreground">
                    Select a date and click Search to load or create an edition.
                </CardContent>
            </Card>

            <Dialog v-model:open="confirmOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{{ actionLabel }} this edition?</DialogTitle>
                        <DialogDescription>
                            This will change edition status for {{ props.edition?.edition_date }}.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" :disabled="form.processing" @click="confirmOpen = false">
                            Cancel
                        </Button>
                        <Button :variant="pendingAction === 'publish' ? 'default' : 'destructive'" :disabled="form.processing" @click="submitStatusChange">
                            {{ actionLabel }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </EpAdminLayout>
</template>
