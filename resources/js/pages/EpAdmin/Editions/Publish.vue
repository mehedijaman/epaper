<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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

type PublishReadiness = {
    is_ready: boolean;
    blockers: string[];
};

type EditionSummary = {
    id: number;
    edition_date: string;
    name?: string | null;
    status: 'draft' | 'published';
    published_at: string | null;
    pages_count: number;
    publish_readiness: PublishReadiness;
};

type Props = {
    date: string;
    date_error: string | null;
    date_notice: string | null;
    selected_edition_id: number | null;
    editions_for_date: EditionSummary[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Publish Editions', href: '/admin/editions/publish' },
];

type PublishAction = 'publish' | 'unpublish';

const date = ref(props.date);
const confirmOpen = ref(false);
const pendingAction = ref<PublishAction>('publish');
const pendingEditionId = ref<number | null>(null);

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

const editionsForDate = computed(() => props.editions_for_date ?? []);
const hasEditions = computed(() => editionsForDate.value.length > 0);
const actionLabel = computed(() => (pendingAction.value === 'publish' ? 'Publish' : 'Unpublish'));
const actionUrl = computed(() => (
    pendingAction.value === 'publish'
        ? '/admin/editions/publish'
        : '/admin/editions/unpublish'
));
const currentPendingEdition = computed(() => {
    if (pendingEditionId.value === null) {
        return null;
    }

    return editionsForDate.value.find((edition) => edition.id === pendingEditionId.value) ?? null;
});
const confirmActionDisabled = computed(() => {
    if (form.processing || currentPendingEdition.value === null) {
        return true;
    }

    if (pendingAction.value === 'publish') {
        return currentPendingEdition.value.publish_readiness.is_ready !== true;
    }

    return false;
});
const manageSearchHref = computed(() => (
    date.value !== '' ? `/admin/editions/manage?date=${date.value}` : '/admin/editions/manage'
));
const manageCreateHref = computed(() => (
    date.value !== '' ? `/admin/editions/manage?date=${date.value}` : '/admin/editions/manage'
));

function formatEditionName(edition: EditionSummary): string {
    if (typeof edition.name === 'string' && edition.name.trim() !== '') {
        return edition.name.trim();
    }

    return `Edition ${edition.id}`;
}

function formatPublishedAt(value: string | null): string | null {
    if (value === null || value.trim() === '') {
        return null;
    }

    return new Date(value).toLocaleString();
}

function isEditionSelected(editionId: number): boolean {
    return props.selected_edition_id === editionId;
}

function isActionProcessing(editionId: number): boolean {
    return form.processing && form.edition_id === editionId;
}

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

function openConfirm(action: PublishAction, edition: EditionSummary): void {
    if (action === 'publish' && edition.publish_readiness.is_ready !== true) {
        return;
    }

    pendingAction.value = action;
    pendingEditionId.value = edition.id;
    form.clearErrors();
    confirmOpen.value = true;
}

function submitStatusChange(): void {
    if (currentPendingEdition.value === null) {
        return;
    }

    form.edition_id = currentPendingEdition.value.id;
    form.post(actionUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            confirmOpen.value = false;
            pendingEditionId.value = null;
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
                    Search by date, then publish or unpublish any edition from the list.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Search editions by date</CardTitle>
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

            <Card v-if="hasEditions">
                <CardHeader>
                    <CardTitle class="flex flex-wrap items-center gap-2">
                        <span>Editions for {{ props.date }}</span>
                        <Badge variant="secondary">{{ editionsForDate.length }}</Badge>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <article
                        v-for="edition in editionsForDate"
                        :key="edition.id"
                        class="space-y-3 rounded-lg border border-border/70 bg-card p-4"
                        :class="isEditionSelected(edition.id) ? 'ring-1 ring-primary/40' : ''"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold">{{ formatEditionName(edition) }}</p>
                            <Badge variant="outline">{{ edition.edition_date }}</Badge>
                            <Badge :variant="edition.status === 'published' ? 'default' : 'secondary'">
                                {{ edition.status }}
                            </Badge>
                            <Badge variant="outline">
                                {{ edition.pages_count }} {{ edition.pages_count === 1 ? 'page' : 'pages' }}
                            </Badge>
                            <Badge v-if="isEditionSelected(edition.id)" variant="outline">Selected</Badge>
                        </div>

                        <p v-if="formatPublishedAt(edition.published_at)" class="text-xs text-muted-foreground">
                            Published at: {{ formatPublishedAt(edition.published_at) }}
                        </p>

                        <Alert
                            v-if="edition.status === 'draft' && edition.publish_readiness.blockers.length > 0"
                            class="border-destructive/40 text-destructive"
                        >
                            <AlertTitle>Cannot publish yet</AlertTitle>
                            <AlertDescription>
                                <ul class="list-disc space-y-1 pl-4 text-sm">
                                    <li v-for="blocker in edition.publish_readiness.blockers" :key="blocker">
                                        {{ blocker }}
                                    </li>
                                </ul>
                            </AlertDescription>
                        </Alert>

                        <p
                            v-if="edition.status === 'draft' && edition.publish_readiness.is_ready"
                            class="text-sm text-muted-foreground"
                        >
                            This edition is ready to publish.
                        </p>

                        <div class="flex flex-wrap gap-2">
                            <Button as-child size="sm" variant="outline">
                                <Link :href="`/admin/editions/manage?date=${edition.edition_date}&edition_id=${edition.id}`">
                                    Manage
                                </Link>
                            </Button>

                            <Button
                                v-if="edition.status === 'published'"
                                size="sm"
                                variant="destructive"
                                :disabled="form.processing"
                                @click="openConfirm('unpublish', edition)"
                            >
                                {{ isActionProcessing(edition.id) ? 'Unpublishing...' : 'Unpublish' }}
                            </Button>

                            <Button
                                v-else
                                size="sm"
                                :disabled="form.processing || !edition.publish_readiness.is_ready"
                                @click="openConfirm('publish', edition)"
                            >
                                {{ isActionProcessing(edition.id) ? 'Publishing...' : 'Publish' }}
                            </Button>
                        </div>
                    </article>
                </CardContent>
            </Card>

            <Card v-else>
                <CardContent class="space-y-3 py-6 text-sm text-muted-foreground">
                    <p>Select a date and click Search to load existing editions.</p>
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
                            This will change status for
                            {{ currentPendingEdition ? `${formatEditionName(currentPendingEdition)} (${currentPendingEdition.edition_date})` : 'the selected edition' }}.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" :disabled="form.processing" @click="confirmOpen = false">
                            Cancel
                        </Button>
                        <Button
                            :variant="pendingAction === 'publish' ? 'default' : 'destructive'"
                            :disabled="confirmActionDisabled"
                            @click="submitStatusChange"
                        >
                            {{ actionLabel }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </EpAdminLayout>
</template>
