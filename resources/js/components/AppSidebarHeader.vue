<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ExternalLink } from 'lucide-vue-next';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

const page = usePage();
const isAdminPanelRoute = computed(() => page.url.startsWith('/admin'));

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div v-if="isAdminPanelRoute" class="ml-auto">
            <Button as-child size="sm" variant="outline" class="gap-2">
                <a href="/" target="_blank" rel="noopener noreferrer">
                    <ExternalLink class="size-4" />
                    <span class="hidden sm:inline">Open Public Home</span>
                    <span class="sm:hidden">Public Home</span>
                </a>
            </Button>
        </div>
    </header>
</template>
