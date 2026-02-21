<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { Ad, Edition } from '@/types';

const props = defineProps<{
    edition: Edition;
    adsBySlot: Record<string, Ad[]>;
    settings: {
        footer_editor_info: string;
        footer_contact_info: string;
        footer_copyright: string;
    };
}>();
</script>

<template>
    <Head :title="`Edition ${edition.edition_date}`" />

    <div class="min-h-screen bg-slate-100/70">
        <div class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:py-8">
            <section class="rounded-xl border border-border/70 bg-background p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            Daily ePaper
                        </p>
                        <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                            Edition {{ edition.edition_date }}
                        </h1>
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge variant="secondary">{{ edition.pages.length }} pages</Badge>
                            <Badge variant="outline">Digital archive</Badge>
                        </div>
                    </div>
                    <Link
                        href="/"
                        class="inline-flex items-center justify-center rounded-md border border-border bg-background px-3 py-2 text-sm font-medium transition-colors hover:bg-muted"
                    >
                        Back to archive
                    </Link>
                </div>
            </section>

            <Card class="border-border/70 shadow-sm">
                <CardHeader class="pb-3">
                    <CardTitle>Browse Pages</CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="edition.pages.length === 0"
                        class="rounded-lg border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        No pages available in this edition yet.
                    </div>

                    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <Link
                            v-for="page in edition.pages"
                            :key="page.id"
                            :href="`/epaper/${edition.edition_date}/page/${page.page_no}`"
                            class="group rounded-xl border border-border/70 bg-card p-2.5 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                        >
                            <img
                                :src="page.image_thumb_url"
                                :alt="`Page ${page.page_no}`"
                                loading="lazy"
                                class="h-auto w-full rounded-md border border-border/60"
                            />
                            <div class="mt-2.5 flex items-center justify-between">
                                <p class="text-sm font-medium">Page {{ page.page_no }}</p>
                                <p class="text-xs text-muted-foreground">Open</p>
                            </div>
                        </Link>
                    </div>
                </CardContent>
            </Card>
        </div>

        <footer class="border-t bg-background py-6">
            <div class="mx-auto max-w-7xl space-y-1 px-4 text-sm text-muted-foreground">
                <p>{{ settings.footer_editor_info }}</p>
                <p>{{ settings.footer_contact_info }}</p>
                <p>{{ settings.footer_copyright }}</p>
            </div>
        </footer>
    </div>
</template>
