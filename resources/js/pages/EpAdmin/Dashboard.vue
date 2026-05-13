<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BookOpen,
    FileStack,
    MousePointer2,
    Newspaper,
    Settings,
    Tv2,
    Users,
    Zap,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import EpAdminLayout from '@/layouts/EpAdminLayout.vue';
import type { BreadcrumbItem } from '@/types';

type RecentEdition = {
    id: number;
    name: string | null;
    edition_date: string;
    status: 'draft' | 'published';
    pages_count: number;
};

type Props = {
    todayPagesCount: number;
    activeAdsCount: number;
    totalAdsCount: number;
    totalUsersCount: number;
    totalCategoriesCount: number;
    totalEditions: number;
    publishedEditions: number;
    draftEditions: number;
    totalPages: number;
    totalHotspots: number;
    recentEditions: RecentEdition[];
};

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ePaper Dashboard', href: '/admin' },
];

function editionLabel(edition: RecentEdition): string {
    return edition.name?.trim() || `Edition ${edition.id}`;
}
</script>

<template>
    <Head title="ePaper Dashboard" />
    <EpAdminLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 sm:p-6">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
                <p class="text-sm text-muted-foreground">
                    Overview of your ePaper publication system.
                </p>
            </div>

            <!-- Editions Stats Row -->
            <div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Editions
                </p>
                <div class="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader class="pb-2">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-medium text-muted-foreground">
                                    Total Editions
                                </CardTitle>
                                <Newspaper class="size-4 text-muted-foreground" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <p class="text-3xl font-bold">{{ totalEditions }}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-medium text-muted-foreground">
                                    Published
                                </CardTitle>
                                <Zap class="size-4 text-emerald-500" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <p class="text-3xl font-bold text-emerald-600">{{ publishedEditions }}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-medium text-muted-foreground">
                                    Drafts
                                </CardTitle>
                                <FileStack class="size-4 text-muted-foreground" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <p class="text-3xl font-bold">{{ draftEditions }}</p>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Content Stats Row -->
            <div>
                <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                    Content
                </p>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <Card>
                        <CardHeader class="pb-2">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-medium text-muted-foreground">
                                    Today's Pages
                                </CardTitle>
                                <BookOpen class="size-4 text-muted-foreground" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <p class="text-3xl font-bold">{{ todayPagesCount }}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-medium text-muted-foreground">
                                    Total Pages
                                </CardTitle>
                                <BookOpen class="size-4 text-muted-foreground" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <p class="text-3xl font-bold">{{ totalPages }}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-medium text-muted-foreground">
                                    Hotspots
                                </CardTitle>
                                <MousePointer2 class="size-4 text-muted-foreground" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <p class="text-3xl font-bold">{{ totalHotspots }}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-medium text-muted-foreground">
                                    Active Ads
                                </CardTitle>
                                <Tv2 class="size-4 text-muted-foreground" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <p class="text-3xl font-bold">{{ activeAdsCount }}</p>
                            <p class="text-xs text-muted-foreground">of {{ totalAdsCount }} total</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-medium text-muted-foreground">
                                    Users
                                </CardTitle>
                                <Users class="size-4 text-muted-foreground" />
                            </div>
                        </CardHeader>
                        <CardContent>
                            <p class="text-3xl font-bold">{{ totalUsersCount }}</p>
                            <p class="text-xs text-muted-foreground">{{ totalCategoriesCount }} categories</p>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[1fr_260px]">
                <!-- Recent Editions Table -->
                <Card>
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-base">Recent Editions</CardTitle>
                            <Button variant="ghost" size="sm" as-child>
                                <Link href="/admin/editions/manage">View all</Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div
                            v-if="recentEditions.length === 0"
                            class="px-6 py-8 text-center text-sm text-muted-foreground"
                        >
                            No editions yet. Create your first edition to get started.
                        </div>
                        <Table v-else>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Edition</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Pages</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="edition in recentEditions"
                                    :key="edition.id"
                                >
                                    <TableCell class="font-medium">
                                        {{ editionLabel(edition) }}
                                    </TableCell>
                                    <TableCell class="text-muted-foreground">
                                        {{ edition.edition_date }}
                                    </TableCell>
                                    <TableCell class="text-muted-foreground">
                                        {{ edition.pages_count }}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            :variant="edition.status === 'published' ? 'default' : 'secondary'"
                                        >
                                            {{ edition.status }}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <!-- Quick Actions -->
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base">Quick Actions</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2">
                        <Button variant="outline" class="w-full justify-start gap-2" as-child>
                            <Link href="/admin/editions/manage">
                                <Newspaper class="size-4" />
                                Manage Editions
                            </Link>
                        </Button>
                        <Button variant="outline" class="w-full justify-start gap-2" as-child>
                            <Link href="/admin/editions/publish">
                                <Zap class="size-4" />
                                Publish Edition
                            </Link>
                        </Button>
                        <Button variant="outline" class="w-full justify-start gap-2" as-child>
                            <Link href="/admin/hotspots">
                                <MousePointer2 class="size-4" />
                                Map Hotspots
                            </Link>
                        </Button>
                        <Button variant="outline" class="w-full justify-start gap-2" as-child>
                            <Link href="/admin/ads">
                                <Tv2 class="size-4" />
                                Manage Ads
                            </Link>
                        </Button>
                        <Button variant="outline" class="w-full justify-start gap-2" as-child>
                            <Link href="/admin/categories">
                                <BookOpen class="size-4" />
                                Categories
                            </Link>
                        </Button>
                        <Button variant="outline" class="w-full justify-start gap-2" as-child>
                            <Link href="/admin/settings">
                                <Settings class="size-4" />
                                Site Settings
                            </Link>
                        </Button>
                        <Button variant="outline" class="w-full justify-start gap-2" as-child>
                            <Link href="/admin/users">
                                <Users class="size-4" />
                                User Management
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </EpAdminLayout>
</template>
