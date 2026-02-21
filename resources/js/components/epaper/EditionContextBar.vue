<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Props = {
    editionDate: string;
    status: 'draft' | 'published';
    pagesCount?: number | null;
    publishedAt?: string | null;
    currentPageNo?: number | null;
    manageHref?: string;
    publishHref?: string;
    mappingHref?: string;
};

const props = defineProps<Props>();

const publishedAtText = computed(() => {
    if (props.publishedAt === null || props.publishedAt === undefined || props.publishedAt === '') {
        return null;
    }

    return new Date(props.publishedAt).toLocaleString();
});

const statusVariant = computed<'default' | 'secondary'>(() => (
    props.status === 'published' ? 'default' : 'secondary'
));
</script>

<template>
    <div class="rounded-xl border border-border/70 bg-muted/20 px-4 py-3">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-sm font-semibold">Edition {{ props.editionDate }}</p>
                    <Badge :variant="statusVariant">
                        {{ props.status }}
                    </Badge>
                    <Badge v-if="typeof props.pagesCount === 'number'" variant="outline">
                        {{ props.pagesCount }} {{ props.pagesCount === 1 ? 'page' : 'pages' }}
                    </Badge>
                    <Badge v-if="typeof props.currentPageNo === 'number'" variant="outline">
                        Viewing page {{ props.currentPageNo }}
                    </Badge>
                </div>
                <p v-if="publishedAtText" class="text-xs text-muted-foreground">
                    Published at: {{ publishedAtText }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button v-if="props.manageHref" as-child size="sm" variant="outline">
                    <Link :href="props.manageHref">Manage</Link>
                </Button>
                <Button v-if="props.publishHref" as-child size="sm" variant="outline">
                    <Link :href="props.publishHref">Publish</Link>
                </Button>
                <Button v-if="props.mappingHref" as-child size="sm" variant="outline">
                    <Link :href="props.mappingHref">Mapping</Link>
                </Button>
            </div>
        </div>
    </div>
</template>
