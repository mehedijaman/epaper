<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import EditionContextBar from '@/components/epaper/EditionContextBar.vue';
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from '@/components/ui/alert';
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

type PublishReadiness = {
    is_ready: boolean;
    blockers: string[];
};

type Props = {
    date: string;
    date_error: string | null;
    date_notice: string | null;
    edition: EditionSummary | null;
    publish_readiness: PublishReadiness | null;
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
const publishReadiness = computed(() => props.publish_readiness);
const publishBlockers = computed(() => publishReadiness.value?.blockers ?? []);
const canPublishDraftEdition = computed(() => publishReadiness.value?.is_ready === true);
const statusBadgeVariant = computed(() => (isPublished.value ? 'default' : 'secondary'));
const actionLabel = computed(() => (pendingAction.value === 'publish' ? 'Publish' : 'Unpublish'));
const actionUrl = computed(() =>
    pendingAction.value === 'publish'
        ? '/admin/editions/publish'
        : '/admin/editions/unpublish',
);
const publishButtonDisabled = computed(() => form.processing || !canPublishDraftEdition.value);
const confirmActionDisabled = computed(() => (
    form.processing
    || (pendingAction.value === 'publish' && !canPublishDraftEdition.value)
));
const publishedAtText = computed(() => {
    const value = props.edition?.published_at;

    if (value === null || value === undefined) {
        return null;
    }

    return new Date(value).toLocaleString();
});
const manageSearchHref = computed(() => (
    date.value !== '' ? `/admin/editions/manage?date=${date.value}` : '/admin/editions/manage'
));
const manageCreateHref = computed(() => (
    date.value !== '' ? `/admin/editions/manage?date=${date.value}&create=1` : '/admin/editions/manage'
));

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

    if (action === 'publish' && !canPublishDraftEdition.value) {
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
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                        <Input v-model="date" type="date" class="sm:max-w-xs" />
                        <Button @click="searchByDate">Search</Button>
                        <Button as-child variant="outline">
                            <Link :href="manageSearchHref">Open in Manage Pages</Link>
                        </Button>
                    </div>
                    <p v-if="props.date_error" class="text-sm text-destructive">
                        {{ props.date_error }}
                    </p>
                    <p v-if="props.date_notice" class="text-sm text-muted-foreground">
                        {{ props.date_notice }}
                    </p>
                </CardContent>
            </Card>

            <div v-if="hasEdition" class="space-y-4">
                <EditionContextBar
                    v-if="props.edition"
                    :edition-date="props.edition.edition_date"
                    :status="props.edition.status"
                    :pages-count="props.edition.pages_count"
                    :published-at="props.edition.published_at"
                    :manage-href="`/admin/editions/manage?date=${props.edition.edition_date}`"
                    :publish-href="`/admin/editions/publish?date=${props.edition.edition_date}`"
                />

                <Card>
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
                        <Alert
                            v-if="!isPublished && publishBlockers.length > 0"
                            class="border-destructive/40 text-destructive"
                        >
                            <AlertTitle>Cannot publish yet</AlertTitle>
                            <AlertDescription>
                                <ul class="list-disc space-y-1 pl-4 text-sm">
                                    <li v-for="blocker in publishBlockers" :key="blocker">
                                        {{ blocker }}
                                    </li>
                                </ul>
                            </AlertDescription>
                        </Alert>
                        <p
                            v-if="!isPublished && publishReadiness?.is_ready"
                            class="text-sm text-muted-foreground"
                        >
                            This edition is ready to publish.
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
                            :disabled="publishButtonDisabled"
                            @click="openConfirm('publish')"
                        >
                            Publish Edition
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <Card v-else>
                <CardContent class="space-y-3 py-6 text-sm text-muted-foreground">
                    <p>Select a date and click Search to load an existing edition.</p>
                    <Button as-child variant="outline" class="w-full sm:w-auto">
                        <Link :href="manageCreateHref">Create draft edition in Manage Pages</Link>
                    </Button>
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
                        <Button :variant="pendingAction === 'publish' ? 'default' : 'destructive'" :disabled="confirmActionDisabled" @click="submitStatusChange">
                            {{ actionLabel }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </EpAdminLayout>
</template>
