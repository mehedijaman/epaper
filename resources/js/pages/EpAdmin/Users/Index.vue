<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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

type AclOption = {
    name: string;
    label: string;
};

type PermissionGroup = {
    key: string;
    label: string;
    items: AclOption[];
};

const props = defineProps<{
    users: UserAclItem[];
    roles: RoleAclItem[];
    permissions: PermissionAclItem[];
}>();

const page = usePage<{
    auth: {
        user: {
            id: number;
        } | null;
    };
    errors?: {
        user?: string;
        role?: string;
        permission?: string;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ACL', href: '/admin/users' },
];

const searchQuery = ref('');
const editDialogOpen = ref(false);
const editingUserId = ref<number | null>(null);
const editingUserName = ref('');
const editingUserEmail = ref('');
const userDialogOpen = ref(false);
const userDialogMode = ref<'create' | 'edit'>('create');
const editingManagedUserId = ref<number | null>(null);

const roleDialogOpen = ref(false);
const roleDialogMode = ref<'create' | 'edit'>('create');
const editingRoleId = ref<number | null>(null);
const permissionDialogOpen = ref(false);
const permissionDialogMode = ref<'create' | 'edit'>('create');
const editingPermissionId = ref<number | null>(null);

const aclForm = useForm<{
    roles: string[];
    permissions: string[];
}>({
    roles: [],
    permissions: [],
});
const userForm = useForm<{
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    roles: string[];
    permissions: string[];
}>({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [],
    permissions: [],
});

const roleForm = useForm<{
    name: string;
    permissions: string[];
}>({
    name: '',
    permissions: [],
});
const permissionForm = useForm<{
    name: string;
}>({
    name: '',
});

const currentUserId = computed<number | null>(() => page.props.auth.user?.id ?? null);
const userMutationError = computed(() => page.props.errors?.user ?? '');
const roleMutationError = computed(() => page.props.errors?.role ?? '');
const permissionMutationError = computed(() => page.props.errors?.permission ?? '');

const sortedRoles = computed<RoleAclItem[]>(() =>
    [...props.roles].sort((a, b) => a.name.localeCompare(b.name)),
);

const editingRole = computed<RoleAclItem | null>(() => {
    if (editingRoleId.value === null) {
        return null;
    }

    return sortedRoles.value.find((item) => item.id === editingRoleId.value) ?? null;
});
const sortedPermissions = computed<PermissionAclItem[]>(() =>
    [...props.permissions].sort((a, b) => a.name.localeCompare(b.name)),
);
const permissionOptions = computed<AclOption[]>(() =>
    props.permissions.map((permission) => ({
        name: permission.name,
        label: permission.label,
    })),
);
const editingPermission = computed<PermissionAclItem | null>(() => {
    if (editingPermissionId.value === null) {
        return null;
    }

    return sortedPermissions.value.find((item) => item.id === editingPermissionId.value) ?? null;
});

const isEditingSystemRole = computed<boolean>(() => {
    if (editingRole.value === null) {
        return false;
    }

    return isSystemRole(editingRole.value.name);
});
const isEditingSystemPermission = computed<boolean>(() => {
    if (editingPermission.value === null) {
        return false;
    }

    return isSystemPermission(editingPermission.value.name);
});

const totalUsers = computed(() => props.users.length);
const totalRoles = computed(() => props.roles.length);
const totalPermissions = computed(() => props.permissions.length);
const adminUsers = computed(() => props.users.filter((user) => user.roles.includes('admin')).length);
const usersWithDirectPermissions = computed(() =>
    props.users.filter((user) => user.direct_permissions.length > 0).length,
);

const filteredUsers = computed<UserAclItem[]>(() => {
    const query = searchQuery.value.trim().toLowerCase();

    if (query === '') {
        return props.users;
    }

    return props.users.filter((user) => {
        const haystack = [
            user.name,
            user.email,
            user.roles.join(' '),
            user.effective_permissions.join(' '),
        ]
            .join(' ')
            .toLowerCase();

        return haystack.includes(query);
    });
});

const permissionGroups = computed<PermissionGroup[]>(() => {
    const map = new Map<string, AclOption[]>();

    for (const permission of permissionOptions.value) {
        const moduleKey = permission.name.split('.')[0] ?? 'general';
        const list = map.get(moduleKey) ?? [];
        list.push(permission);
        map.set(moduleKey, list);
    }

    return Array.from(map.entries())
        .map(([key, items]) => ({
            key,
            label: formatKeyAsTitle(key),
            items: [...items].sort((a, b) => a.label.localeCompare(b.label)),
        }))
        .sort((a, b) => a.label.localeCompare(b.label));
});

const isEditingSelf = computed(() => {
    if (editingUserId.value === null || currentUserId.value === null) {
        return false;
    }

    return editingUserId.value === currentUserId.value;
});
const isEditingCurrentManagedUser = computed<boolean>(() => {
    if (editingManagedUserId.value === null || currentUserId.value === null) {
        return false;
    }

    return editingManagedUserId.value === currentUserId.value;
});

function formatKeyAsTitle(value: string): string {
    return value
        .replace(/[._-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (match) => match.toUpperCase());
}

function isSystemRole(roleName: string): boolean {
    return roleName === 'admin' || roleName === 'operator';
}

function isSystemPermission(permissionName: string): boolean {
    return [
        'categories.manage',
        'users.manage',
        'ads.manage',
        'settings.manage',
        'editions.manage',
    ].includes(permissionName);
}

function openAclDialog(user: UserAclItem): void {
    editingUserId.value = user.id;
    editingUserName.value = user.name;
    editingUserEmail.value = user.email;
    aclForm.roles = [...user.roles].sort((a, b) => a.localeCompare(b));
    aclForm.permissions = [...user.direct_permissions].sort((a, b) => a.localeCompare(b));
    aclForm.clearErrors();
    editDialogOpen.value = true;
}

function closeAclDialog(): void {
    if (aclForm.processing) {
        return;
    }

    editDialogOpen.value = false;
}

function onAclDialogOpenChange(open: boolean): void {
    if (!open) {
        closeAclDialog();
        return;
    }

    editDialogOpen.value = true;
}

function updateAclSelection(field: 'roles' | 'permissions', value: string, checked: boolean | 'indeterminate'): void {
    const source = field === 'roles' ? aclForm.roles : aclForm.permissions;
    const next = new Set(source);

    if (checked === true) {
        next.add(value);
    } else {
        next.delete(value);
    }

    const sorted = Array.from(next).sort((a, b) => a.localeCompare(b));

    if (field === 'roles') {
        aclForm.roles = sorted;
        return;
    }

    aclForm.permissions = sorted;
}

function onUserRoleToggle(roleName: string, checked: boolean | 'indeterminate'): void {
    updateAclSelection('roles', roleName, checked);
}

function onUserPermissionToggle(permissionName: string, checked: boolean | 'indeterminate'): void {
    updateAclSelection('permissions', permissionName, checked);
}

function saveAcl(): void {
    if (editingUserId.value === null) {
        return;
    }

    aclForm.put(`/admin/users/${editingUserId.value}/acl`, {
        preserveScroll: true,
        onSuccess: () => {
            editDialogOpen.value = false;
        },
    });
}

function openCreateUserDialog(): void {
    userDialogMode.value = 'create';
    editingManagedUserId.value = null;
    userForm.reset();
    userForm.roles = [];
    userForm.permissions = [];
    userForm.clearErrors();
    userDialogOpen.value = true;
}

function openEditUserDialog(user: UserAclItem): void {
    userDialogMode.value = 'edit';
    editingManagedUserId.value = user.id;
    userForm.name = user.name;
    userForm.email = user.email;
    userForm.password = '';
    userForm.password_confirmation = '';
    userForm.roles = [...user.roles].sort((a, b) => a.localeCompare(b));
    userForm.permissions = [...user.direct_permissions].sort((a, b) => a.localeCompare(b));
    userForm.clearErrors();
    userDialogOpen.value = true;
}

function closeUserDialog(): void {
    if (userForm.processing) {
        return;
    }

    userDialogOpen.value = false;
}

function onUserDialogOpenChange(open: boolean): void {
    if (!open) {
        closeUserDialog();
        return;
    }

    userDialogOpen.value = true;
}

function updateUserFormSelection(field: 'roles' | 'permissions', value: string, checked: boolean | 'indeterminate'): void {
    const source = field === 'roles' ? userForm.roles : userForm.permissions;
    const next = new Set(source);

    if (checked === true) {
        next.add(value);
    } else {
        next.delete(value);
    }

    const sorted = Array.from(next).sort((a, b) => a.localeCompare(b));

    if (field === 'roles') {
        userForm.roles = sorted;
        return;
    }

    userForm.permissions = sorted;
}

function onManagedUserRoleToggle(roleName: string, checked: boolean | 'indeterminate'): void {
    updateUserFormSelection('roles', roleName, checked);
}

function onManagedUserPermissionToggle(permissionName: string, checked: boolean | 'indeterminate'): void {
    updateUserFormSelection('permissions', permissionName, checked);
}

function saveManagedUser(): void {
    if (userDialogMode.value === 'edit' && editingManagedUserId.value !== null) {
        userForm.put(`/admin/users/accounts/${editingManagedUserId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                userDialogOpen.value = false;
            },
        });
        return;
    }

    userForm.post('/admin/users/accounts', {
        preserveScroll: true,
        onSuccess: () => {
            userDialogOpen.value = false;
        },
    });
}

function canDeleteUser(user: UserAclItem): boolean {
    if (currentUserId.value !== null && user.id === currentUserId.value) {
        return false;
    }

    if (user.roles.includes('admin') && adminUsers.value <= 1) {
        return false;
    }

    return true;
}

function userDeleteReason(user: UserAclItem): string | undefined {
    if (currentUserId.value !== null && user.id === currentUserId.value) {
        return 'You cannot delete your own account.';
    }

    if (user.roles.includes('admin') && adminUsers.value <= 1) {
        return 'Cannot delete the last admin user.';
    }

    return undefined;
}

function deleteUser(user: UserAclItem): void {
    if (!canDeleteUser(user)) {
        return;
    }

    if (!window.confirm(`Delete user "${user.name}"?`)) {
        return;
    }

    router.delete(`/admin/users/accounts/${user.id}`, {
        preserveScroll: true,
    });
}

function openCreateRoleDialog(): void {
    roleDialogMode.value = 'create';
    editingRoleId.value = null;
    roleForm.reset();
    roleForm.permissions = [];
    roleForm.clearErrors();
    roleDialogOpen.value = true;
}

function openEditRoleDialog(role: RoleAclItem): void {
    roleDialogMode.value = 'edit';
    editingRoleId.value = role.id;
    roleForm.name = role.name;
    roleForm.permissions = [...role.permissions].sort((a, b) => a.localeCompare(b));
    roleForm.clearErrors();
    roleDialogOpen.value = true;
}

function closeRoleDialog(): void {
    if (roleForm.processing) {
        return;
    }

    roleDialogOpen.value = false;
}

function onRoleDialogOpenChange(open: boolean): void {
    if (!open) {
        closeRoleDialog();
        return;
    }

    roleDialogOpen.value = true;
}

function onRolePermissionToggle(permissionName: string, checked: boolean | 'indeterminate'): void {
    const next = new Set(roleForm.permissions);

    if (checked === true) {
        next.add(permissionName);
    } else {
        next.delete(permissionName);
    }

    roleForm.permissions = Array.from(next).sort((a, b) => a.localeCompare(b));
}

function saveRole(): void {
    if (roleDialogMode.value === 'edit' && editingRoleId.value !== null) {
        roleForm.put(`/admin/users/roles/${editingRoleId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                roleDialogOpen.value = false;
            },
        });
        return;
    }

    roleForm.post('/admin/users/roles', {
        preserveScroll: true,
        onSuccess: () => {
            roleDialogOpen.value = false;
        },
    });
}

function canDeleteRole(role: RoleAclItem): boolean {
    return !isSystemRole(role.name) && role.users_count === 0;
}

function roleDeleteReason(role: RoleAclItem): string | undefined {
    if (isSystemRole(role.name)) {
        return 'System roles cannot be deleted.';
    }

    if (role.users_count > 0) {
        return 'Remove this role from all users before deletion.';
    }

    return undefined;
}

function deleteRole(role: RoleAclItem): void {
    if (!canDeleteRole(role)) {
        return;
    }

    if (!window.confirm(`Delete role "${role.name}"?`)) {
        return;
    }

    router.delete(`/admin/users/roles/${role.id}`, {
        preserveScroll: true,
    });
}

function openCreatePermissionDialog(): void {
    permissionDialogMode.value = 'create';
    editingPermissionId.value = null;
    permissionForm.reset();
    permissionForm.clearErrors();
    permissionDialogOpen.value = true;
}

function openEditPermissionDialog(permission: PermissionAclItem): void {
    permissionDialogMode.value = 'edit';
    editingPermissionId.value = permission.id;
    permissionForm.name = permission.name;
    permissionForm.clearErrors();
    permissionDialogOpen.value = true;
}

function closePermissionDialog(): void {
    if (permissionForm.processing) {
        return;
    }

    permissionDialogOpen.value = false;
}

function onPermissionDialogOpenChange(open: boolean): void {
    if (!open) {
        closePermissionDialog();
        return;
    }

    permissionDialogOpen.value = true;
}

function savePermission(): void {
    if (permissionDialogMode.value === 'edit' && editingPermissionId.value !== null) {
        permissionForm.put(`/admin/users/permissions/${editingPermissionId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                permissionDialogOpen.value = false;
            },
        });
        return;
    }

    permissionForm.post('/admin/users/permissions', {
        preserveScroll: true,
        onSuccess: () => {
            permissionDialogOpen.value = false;
        },
    });
}

function canDeletePermission(permission: PermissionAclItem): boolean {
    return !isSystemPermission(permission.name) && permission.roles_count === 0 && permission.users_count === 0;
}

function permissionDeleteReason(permission: PermissionAclItem): string | undefined {
    if (isSystemPermission(permission.name)) {
        return 'System permissions cannot be deleted.';
    }

    if (permission.roles_count > 0 || permission.users_count > 0) {
        return 'Remove this permission from roles/users before deletion.';
    }

    return undefined;
}

function deletePermission(permission: PermissionAclItem): void {
    if (!canDeletePermission(permission)) {
        return;
    }

    if (!window.confirm(`Delete permission "${permission.name}"?`)) {
        return;
    }

    router.delete(`/admin/users/permissions/${permission.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Access Control" />

    <EpAdminLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-7xl space-y-5 p-4 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="space-y-1">
                    <h1 class="text-2xl font-semibold tracking-tight">Access Control</h1>
                    <p class="text-sm text-muted-foreground">
                        Manage users, roles, and direct permissions for admin panel access.
                    </p>
                </div>

                <div class="w-full sm:w-[320px]">
                    <Input v-model="searchQuery" placeholder="Search by name, email, role, permission..." />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Card class="border-border/70">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Users</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-semibold">{{ totalUsers }}</p>
                    </CardContent>
                </Card>

                <Card class="border-border/70">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Roles</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-semibold">{{ totalRoles }}</p>
                    </CardContent>
                </Card>

                <Card class="border-border/70">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Permissions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-semibold">{{ totalPermissions }}</p>
                    </CardContent>
                </Card>

                <Card class="border-border/70">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-medium text-muted-foreground">Admins</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-semibold">{{ adminUsers }}</p>
                    </CardContent>
                </Card>
            </div>

            <Card class="border-border/70">
                <CardHeader class="flex flex-col gap-2 pb-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-1">
                        <CardTitle class="text-base">User Access</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            Users with direct permissions: {{ usersWithDirectPermissions }}
                        </p>
                    </div>
                    <Button @click="openCreateUserDialog">Create User</Button>
                </CardHeader>
                <CardContent class="space-y-4">
                    <InputError :message="userMutationError" />

                    <div
                        v-if="filteredUsers.length === 0"
                        class="rounded-lg border border-border/70 bg-card px-4 py-10 text-center text-sm text-muted-foreground"
                    >
                        No users match your current search.
                    </div>

                    <div v-else class="space-y-3 sm:hidden">
                        <article
                            v-for="user in filteredUsers"
                            :key="user.id"
                            class="space-y-3 rounded-lg border border-border/70 bg-card p-4"
                        >
                            <div class="space-y-0.5">
                                <p class="font-medium">{{ user.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ user.email }}</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <Badge
                                    v-for="role in user.roles"
                                    :key="`${user.id}-role-${role}`"
                                    variant="default"
                                >
                                    {{ role }}
                                </Badge>
                                <Badge v-if="user.roles.length === 0" variant="secondary">
                                    No role
                                </Badge>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs text-muted-foreground">
                                <p>Direct: {{ user.direct_permissions.length }}</p>
                                <p>Effective: {{ user.effective_permissions.length }}</p>
                            </div>

                            <div class="grid grid-cols-3 gap-2">
                                <Button size="sm" variant="outline" @click="openAclDialog(user)">
                                    ACL
                                </Button>
                                <Button size="sm" variant="outline" @click="openEditUserDialog(user)">
                                    Edit
                                </Button>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    :disabled="!canDeleteUser(user)"
                                    :title="userDeleteReason(user)"
                                    @click="deleteUser(user)"
                                >
                                    Delete
                                </Button>
                            </div>
                        </article>
                    </div>

                    <div
                        v-if="filteredUsers.length > 0"
                        class="hidden overflow-x-auto rounded-lg border border-border/70 bg-card sm:block"
                    >
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="min-w-[240px]">User</TableHead>
                                    <TableHead class="min-w-[220px]">Roles</TableHead>
                                    <TableHead>Direct Permissions</TableHead>
                                    <TableHead>Effective Permissions</TableHead>
                                    <TableHead class="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="user in filteredUsers" :key="user.id">
                                    <TableCell>
                                        <div class="space-y-1">
                                            <p class="font-medium">{{ user.name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ user.email }}</p>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex flex-wrap gap-1.5">
                                            <Badge
                                                v-for="role in user.roles"
                                                :key="`${user.id}-desktop-role-${role}`"
                                                variant="default"
                                            >
                                                {{ role }}
                                            </Badge>
                                            <Badge v-if="user.roles.length === 0" variant="secondary">
                                                No role
                                            </Badge>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline">{{ user.direct_permissions.length }}</Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline">{{ user.effective_permissions.length }}</Badge>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <Button size="sm" variant="outline" @click="openAclDialog(user)">
                                                ACL
                                            </Button>
                                            <Button size="sm" variant="outline" @click="openEditUserDialog(user)">
                                                Edit
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                :disabled="!canDeleteUser(user)"
                                                :title="userDeleteReason(user)"
                                                @click="deleteUser(user)"
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border/70">
                <CardHeader class="flex flex-col gap-2 pb-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-1">
                        <CardTitle class="text-base">Roles</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            Create and manage role-level permission bundles.
                        </p>
                    </div>
                    <Button @click="openCreateRoleDialog">Create Role</Button>
                </CardHeader>
                <CardContent class="space-y-4">
                    <InputError :message="roleMutationError" />

                    <div
                        v-if="sortedRoles.length === 0"
                        class="rounded-lg border border-border/70 bg-card px-4 py-10 text-center text-sm text-muted-foreground"
                    >
                        No roles found.
                    </div>

                    <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="role in sortedRoles"
                            :key="role.id"
                            class="space-y-3 rounded-lg border border-border/70 bg-card p-4"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-medium">{{ role.label }}</p>
                                    <p class="text-xs text-muted-foreground">{{ role.name }}</p>
                                </div>
                                <Badge v-if="isSystemRole(role.name)" variant="secondary">
                                    System
                                </Badge>
                            </div>

                            <div class="flex flex-wrap gap-2 text-xs">
                                <Badge variant="outline">Users {{ role.users_count }}</Badge>
                                <Badge variant="outline">Permissions {{ role.permissions.length }}</Badge>
                            </div>

                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="permission in role.permissions.slice(0, 4)"
                                    :key="`${role.id}-permission-${permission}`"
                                    variant="secondary"
                                >
                                    {{ permission }}
                                </Badge>
                                <Badge v-if="role.permissions.length > 4" variant="secondary">
                                    +{{ role.permissions.length - 4 }} more
                                </Badge>
                                <Badge v-if="role.permissions.length === 0" variant="secondary">
                                    No permissions
                                </Badge>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <Button size="sm" variant="outline" @click="openEditRoleDialog(role)">
                                    Edit
                                </Button>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    :disabled="!canDeleteRole(role)"
                                    :title="roleDeleteReason(role)"
                                    @click="deleteRole(role)"
                                >
                                    Delete
                                </Button>
                            </div>
                        </article>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border/70">
                <CardHeader class="flex flex-col gap-2 pb-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-1">
                        <CardTitle class="text-base">Permissions</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            Manage permission definitions used in ACL assignments.
                        </p>
                    </div>
                    <Button @click="openCreatePermissionDialog">Create Permission</Button>
                </CardHeader>
                <CardContent class="space-y-4">
                    <InputError :message="permissionMutationError" />

                    <div
                        v-if="sortedPermissions.length === 0"
                        class="rounded-lg border border-border/70 bg-card px-4 py-10 text-center text-sm text-muted-foreground"
                    >
                        No permissions found.
                    </div>

                    <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        <article
                            v-for="permission in sortedPermissions"
                            :key="permission.id"
                            class="space-y-3 rounded-lg border border-border/70 bg-card p-4"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-medium">{{ permission.label }}</p>
                                    <p class="text-xs text-muted-foreground">{{ permission.name }}</p>
                                </div>
                                <Badge v-if="isSystemPermission(permission.name)" variant="secondary">
                                    System
                                </Badge>
                            </div>

                            <div class="flex flex-wrap gap-2 text-xs">
                                <Badge variant="outline">Roles {{ permission.roles_count }}</Badge>
                                <Badge variant="outline">Users {{ permission.users_count }}</Badge>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <Button size="sm" variant="outline" @click="openEditPermissionDialog(permission)">
                                    Edit
                                </Button>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    :disabled="!canDeletePermission(permission)"
                                    :title="permissionDeleteReason(permission)"
                                    @click="deletePermission(permission)"
                                >
                                    Delete
                                </Button>
                            </div>
                        </article>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Dialog :open="userDialogOpen" @update:open="onUserDialogOpenChange">
            <DialogContent class="w-[95vw] max-w-4xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ userDialogMode === 'create' ? 'Create User' : 'Edit User' }}
                    </DialogTitle>
                    <DialogDescription>
                        Manage account profile, roles, and direct permissions.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Name</label>
                        <Input v-model="userForm.name" placeholder="Full name" />
                        <InputError :message="userForm.errors.name" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Email</label>
                        <Input v-model="userForm.email" type="email" placeholder="user@example.com" />
                        <InputError :message="userForm.errors.email" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">
                            {{ userDialogMode === 'create' ? 'Password' : 'New Password (optional)' }}
                        </label>
                        <Input v-model="userForm.password" type="password" autocomplete="new-password" />
                        <InputError :message="userForm.errors.password" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Confirm Password</label>
                        <Input v-model="userForm.password_confirmation" type="password" autocomplete="new-password" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-[220px_minmax(0,1fr)]">
                    <section class="space-y-2">
                        <h3 class="text-sm font-medium">Roles</h3>
                        <div class="space-y-2 rounded-lg border border-border/70 bg-card p-3">
                            <label
                                v-for="role in roles"
                                :key="`managed-user-role-${role.id}`"
                                class="flex cursor-pointer items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    :checked="userForm.roles.includes(role.name)"
                                    @update:checked="onManagedUserRoleToggle(role.name, $event)"
                                />
                                <span>{{ role.label }}</span>
                            </label>
                        </div>
                        <InputError :message="userForm.errors.roles" />
                    </section>

                    <section class="space-y-2">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-medium">Direct Permissions</h3>
                            <Badge variant="outline">{{ userForm.permissions.length }} selected</Badge>
                        </div>
                        <div class="max-h-[320px] space-y-3 overflow-y-auto rounded-lg border border-border/70 bg-card p-3">
                            <section
                                v-for="group in permissionGroups"
                                :key="`managed-user-perm-group-${group.key}`"
                                class="space-y-2"
                            >
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    {{ group.label }}
                                </p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label
                                        v-for="permission in group.items"
                                        :key="`managed-user-permission-${permission.name}`"
                                        class="flex cursor-pointer items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            :checked="userForm.permissions.includes(permission.name)"
                                            @update:checked="onManagedUserPermissionToggle(permission.name, $event)"
                                        />
                                        <span>{{ permission.label }}</span>
                                    </label>
                                </div>
                            </section>
                        </div>
                        <InputError :message="userForm.errors.permissions" />
                    </section>
                </div>

                <p v-if="isEditingCurrentManagedUser" class="text-xs text-muted-foreground">
                    You are editing your own account.
                </p>

                <DialogFooter class="gap-2">
                    <Button variant="outline" :disabled="userForm.processing" @click="closeUserDialog">
                        Cancel
                    </Button>
                    <Button :disabled="userForm.processing" @click="saveManagedUser">
                        {{ userForm.processing ? 'Saving...' : (userDialogMode === 'create' ? 'Create User' : 'Save User') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="editDialogOpen" @update:open="onAclDialogOpenChange">
            <DialogContent class="w-[95vw] max-w-4xl">
                <DialogHeader>
                    <DialogTitle>Manage User ACL</DialogTitle>
                    <DialogDescription>
                        Update roles and direct permissions for {{ editingUserName }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="rounded-lg border border-border/70 bg-muted/20 p-3 text-sm">
                    <p class="font-medium">{{ editingUserName }}</p>
                    <p class="text-xs text-muted-foreground">{{ editingUserEmail }}</p>
                    <p v-if="isEditingSelf" class="mt-1 text-xs text-muted-foreground">
                        You are editing your own access.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-[220px_minmax(0,1fr)]">
                    <section class="space-y-2">
                        <h3 class="text-sm font-medium">Roles</h3>
                        <div class="space-y-2 rounded-lg border border-border/70 bg-card p-3">
                            <label
                                v-for="role in roles"
                                :key="`user-role-choice-${role.name}`"
                                class="flex cursor-pointer items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    :checked="aclForm.roles.includes(role.name)"
                                    @update:checked="onUserRoleToggle(role.name, $event)"
                                />
                                <span>{{ role.label }}</span>
                            </label>
                        </div>
                        <InputError :message="aclForm.errors.roles" />
                    </section>

                    <section class="space-y-2">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-medium">Direct Permissions</h3>
                            <Badge variant="outline">{{ aclForm.permissions.length }} selected</Badge>
                        </div>

                        <div class="max-h-[360px] space-y-3 overflow-y-auto rounded-lg border border-border/70 bg-card p-3">
                            <section
                                v-for="group in permissionGroups"
                                :key="`user-perm-group-${group.key}`"
                                class="space-y-2"
                            >
                                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                    {{ group.label }}
                                </p>

                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label
                                        v-for="permission in group.items"
                                        :key="`user-permission-choice-${permission.name}`"
                                        class="flex cursor-pointer items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            :checked="aclForm.permissions.includes(permission.name)"
                                            @update:checked="onUserPermissionToggle(permission.name, $event)"
                                        />
                                        <span>{{ permission.label }}</span>
                                    </label>
                                </div>
                            </section>
                        </div>
                        <InputError :message="aclForm.errors.permissions" />
                    </section>
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="outline" :disabled="aclForm.processing" @click="closeAclDialog">
                        Cancel
                    </Button>
                    <Button :disabled="aclForm.processing" @click="saveAcl">
                        {{ aclForm.processing ? 'Saving...' : 'Save ACL' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="roleDialogOpen" @update:open="onRoleDialogOpenChange">
            <DialogContent class="w-[95vw] max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ roleDialogMode === 'create' ? 'Create Role' : 'Edit Role' }}
                    </DialogTitle>
                    <DialogDescription>
                        Define role name and permissions.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Role Name</label>
                    <Input
                        v-model="roleForm.name"
                        placeholder="e.g. content_manager"
                        :disabled="isEditingSystemRole"
                    />
                    <p v-if="isEditingSystemRole" class="text-xs text-muted-foreground">
                        System role names cannot be changed.
                    </p>
                    <InputError :message="roleForm.errors.name" />
                </div>

                <section class="space-y-2">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium">Permissions</h3>
                        <Badge variant="outline">{{ roleForm.permissions.length }} selected</Badge>
                    </div>

                    <div class="max-h-[340px] space-y-3 overflow-y-auto rounded-lg border border-border/70 bg-card p-3">
                        <section
                            v-for="group in permissionGroups"
                            :key="`role-perm-group-${group.key}`"
                            class="space-y-2"
                        >
                            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                {{ group.label }}
                            </p>

                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    v-for="permission in group.items"
                                    :key="`role-permission-choice-${permission.name}`"
                                    class="flex cursor-pointer items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        :checked="roleForm.permissions.includes(permission.name)"
                                        @update:checked="onRolePermissionToggle(permission.name, $event)"
                                    />
                                    <span>{{ permission.label }}</span>
                                </label>
                            </div>
                        </section>
                    </div>
                    <InputError :message="roleForm.errors.permissions" />
                </section>

                <DialogFooter class="gap-2">
                    <Button variant="outline" :disabled="roleForm.processing" @click="closeRoleDialog">
                        Cancel
                    </Button>
                    <Button :disabled="roleForm.processing" @click="saveRole">
                        {{ roleForm.processing ? 'Saving...' : (roleDialogMode === 'create' ? 'Create Role' : 'Save Role') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="permissionDialogOpen" @update:open="onPermissionDialogOpenChange">
            <DialogContent class="w-[95vw] max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ permissionDialogMode === 'create' ? 'Create Permission' : 'Edit Permission' }}
                    </DialogTitle>
                    <DialogDescription>
                        Define permission key used by roles and user ACL.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Permission Key</label>
                    <Input
                        v-model="permissionForm.name"
                        placeholder="e.g. reports.manage"
                        :disabled="isEditingSystemPermission"
                    />
                    <p v-if="isEditingSystemPermission" class="text-xs text-muted-foreground">
                        System permission names cannot be changed.
                    </p>
                    <InputError :message="permissionForm.errors.name" />
                </div>

                <DialogFooter class="gap-2">
                    <Button variant="outline" :disabled="permissionForm.processing" @click="closePermissionDialog">
                        Cancel
                    </Button>
                    <Button :disabled="permissionForm.processing" @click="savePermission">
                        {{ permissionForm.processing ? 'Saving...' : (permissionDialogMode === 'create' ? 'Create Permission' : 'Save Permission') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </EpAdminLayout>
</template>
