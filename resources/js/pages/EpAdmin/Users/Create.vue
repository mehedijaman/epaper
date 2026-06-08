<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import EpAdminLayout from '@/layouts/EpAdminLayout.vue';
import type { BreadcrumbItem } from '@/types';
import UserForm from './UserForm.vue';

type RoleAclItem = {
    id: number;
    name: string;
    label: string;
    permissions: string[];
    users_count: number;
};

type PermissionAclItem = {
    id: number;
    name: string;
    label: string;
    roles_count: number;
    users_count: number;
};

defineProps<{
    roles: RoleAclItem[];
    permissions: PermissionAclItem[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ACL', href: '/admin/users' },
    { title: 'Create User', href: '/admin/users/accounts/create' },
];

function handleCancel() {
    router.visit('/admin/users');
}

function handleSuccess() {
    router.visit('/admin/users');
}
</script>

<template>
    <Head title="Create User" />
    <EpAdminLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Create User
                </h1>
                <p class="text-sm text-muted-foreground">
                    Add a new system user and configure their roles and
                    permissions.
                </p>
            </div>

            <div
                class="rounded-xl border border-border/70 bg-card p-6 shadow-sm"
            >
                <UserForm
                    :user="null"
                    :roles="roles"
                    :permissions="permissions"
                    @success="handleSuccess"
                    @cancel="handleCancel"
                />
            </div>
        </div>
    </EpAdminLayout>
</template>
