<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import EpAdminLayout from '@/layouts/EpAdminLayout.vue';
import type { BreadcrumbItem } from '@/types';

type Props = {
    todayPagesCount: number;
    activeAdsCount: number;
    totalUsersCount: number;
    totalCategoriesCount: number;
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ePaper Dashboard', href: '/admin' },
];

const metrics = [
    {
        key: 'today-pages',
        label: "Today's Published Pages",
        value: props.todayPagesCount,
    },
    {
        key: 'active-ads',
        label: 'Active Ads',
        value: props.activeAdsCount,
    },
    {
        key: 'total-users',
        label: 'Total Users',
        value: props.totalUsersCount,
    },
    {
        key: 'total-categories',
        label: 'Total Categories',
        value: props.totalCategoriesCount,
    },
];
</script>

<template>
    <Head title="ePaper Dashboard" />
    <EpAdminLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-4 p-4">
            <h1 class="text-xl font-semibold">ePaper Dashboard</h1>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Card v-for="metric in metrics" :key="metric.key">
                    <CardHeader>
                        <CardTitle class="text-sm font-medium text-muted-foreground">
                            {{ metric.label }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-3xl font-bold">{{ metric.value }}</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </EpAdminLayout>
</template>
