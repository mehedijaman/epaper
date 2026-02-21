<script setup lang="ts">
import FlashMessages from '@/components/epaper/FlashMessages.vue';
import ValidationErrors from '@/components/epaper/ValidationErrors.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-3 p-4">
            <FlashMessages />
            <ValidationErrors />
        </div>

        <slot />
    </AppLayout>
</template>
