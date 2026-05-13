<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpen,
    ExternalLink,
    LayoutDashboard,
    Newspaper,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4 sm:p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Welcome back</h1>
                <p class="text-sm text-muted-foreground">
                    Manage your ePaper publication from the admin panel.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card class="border-border/70">
                    <CardHeader class="pb-3">
                        <div class="flex items-center gap-2">
                            <LayoutDashboard class="size-5 text-primary" />
                            <CardTitle class="text-base">ePaper Admin</CardTitle>
                        </div>
                        <CardDescription>
                            Manage editions, pages, ads, categories, and site settings.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button as-child class="w-full">
                            <Link href="/admin">
                                Go to Admin Panel
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                <Card class="border-border/70">
                    <CardHeader class="pb-3">
                        <div class="flex items-center gap-2">
                            <Newspaper class="size-5 text-primary" />
                            <CardTitle class="text-base">Manage Editions</CardTitle>
                        </div>
                        <CardDescription>
                            Upload pages, reorder content, and manage your editions.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button variant="outline" as-child class="w-full">
                            <Link href="/admin/editions/manage">
                                Open Edition Manager
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                <Card class="border-border/70">
                    <CardHeader class="pb-3">
                        <div class="flex items-center gap-2">
                            <BookOpen class="size-5 text-primary" />
                            <CardTitle class="text-base">Public ePaper</CardTitle>
                        </div>
                        <CardDescription>
                            View the public-facing ePaper reader as your visitors see it.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button variant="outline" as-child class="w-full">
                            <Link href="/" target="_blank">
                                <ExternalLink class="size-4" />
                                Open ePaper Viewer
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
