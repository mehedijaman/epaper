<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import EpAdminLayout from '@/layouts/EpAdminLayout.vue';
import type { BreadcrumbItem } from '@/types';
import UserForm from './UserForm.vue';

type UserAclItem = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    roles: string[];
    direct_permissions: string[];
    effective_permissions: string[];
};

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

const props = defineProps<{
    user: UserAclItem;
    roles: RoleAclItem[];
    permissions: PermissionAclItem[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ACL', href: '/admin/users' },
    {
        title: `Edit ${props.user.name}`,
        href: `/admin/users/accounts/${props.user.id}/edit`,
    },
];

function handleCancel() {
    router.visit('/admin/users');
}

function handleSuccess() {
    router.visit('/admin/users');
}
</script>

<template>
    <Head title="Edit User" />
    <EpAdminLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-5xl space-y-6 p-4 sm:p-6">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight">Edit User</h1>
                <p class="text-sm text-muted-foreground">
                    Update profile, credentials, and roles/permissions for
                    {{ user.name }}.
                </p>
            </div>

            <div
                class="rounded-xl border border-border/70 bg-card p-6 shadow-sm"
            >
                <UserForm
                    :user="user"
                    :roles="roles"
                    :permissions="permissions"
                    @success="handleSuccess"
                    @cancel="handleCancel"
                />
            </div>
        </div>
    </EpAdminLayout>
</template>
