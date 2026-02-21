<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import EpAdminLayout from '@/layouts/EpAdminLayout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Categories', href: '/admin/categories' },
    { title: 'Create', href: '/admin/categories/create' },
];
</script>

<template>
    <Head title="Create Category" />
    <EpAdminLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <h1 class="text-xl font-semibold">Create Category</h1>
            <p class="text-sm text-muted-foreground">Placeholder create page.</p>
        </div>
    </EpAdminLayout>
</template>
