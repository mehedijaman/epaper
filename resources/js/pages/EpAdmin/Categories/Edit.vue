<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import EpAdminLayout from '@/layouts/EpAdminLayout.vue';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Categories', href: '/admin/categories' },
    { title: 'Edit' },
];
</script>

<template>
    <Head title="Edit Category" />
    <EpAdminLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <h1 class="text-xl font-semibold">Edit Category</h1>
            <p class="text-sm text-muted-foreground">Placeholder edit page.</p>
        </div>
    </EpAdminLayout>
</template>
