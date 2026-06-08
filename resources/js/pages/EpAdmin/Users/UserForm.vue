<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    Search,
    Info,
    Shield,
    Key,
    Wand2,
    Copy,
    Check,
    Eye,
    EyeOff,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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
    user: UserAclItem | null;
    roles: RoleAclItem[];
    permissions: PermissionAclItem[];
}>();

const emit = defineEmits<{
    (e: 'success'): void;
    (e: 'cancel'): void;
}>();

const isEditMode = computed(() => !!props.user);

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    password_confirmation: '',
    roles: props.user
        ? [...props.user.roles].sort((a, b) => a.localeCompare(b))
        : ([] as string[]),
    permissions: props.user
        ? [...props.user.direct_permissions].sort((a, b) => a.localeCompare(b))
        : ([] as string[]),
});

// Sync form values when the user prop changes
watch(
    () => props.user,
    (newUser) => {
        if (newUser) {
            form.name = newUser.name;
            form.email = newUser.email;
            form.password = '';
            form.password_confirmation = '';
            form.roles = [...newUser.roles].sort((a, b) => a.localeCompare(b));
            form.permissions = [...newUser.direct_permissions].sort((a, b) =>
                a.localeCompare(b),
            );
            form.clearErrors();
        } else {
            form.reset();
            form.clearErrors();
        }
    },
    { deep: true, immediate: true },
);

function toggleRole(roleName: string) {
    const next = new Set(form.roles);
    if (next.has(roleName)) {
        next.delete(roleName);
    } else {
        next.add(roleName);
    }
    form.roles = Array.from(next).sort((a, b) => a.localeCompare(b));
}

function togglePermission(permissionName: string) {
    const next = new Set(form.permissions);
    if (next.has(permissionName)) {
        next.delete(permissionName);
    } else {
        next.add(permissionName);
    }
    form.permissions = Array.from(next).sort((a, b) => a.localeCompare(b));
}

// Format module prefix as Title
function formatKeyAsTitle(value: string): string {
    return value
        .replace(/[._-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (match) => match.toUpperCase());
}

const permissionSearchQuery = ref('');
const showPassword = ref(false);
const copied = ref(false);

function generatePassword() {
    const length = 12;
    const chars =
        'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    let result = '';
    for (let i = 0; i < length; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    form.password = result;
    form.password_confirmation = result;
    showPassword.value = true;
}

function copyPassword() {
    if (!form.password) {
        return;
    }
    navigator.clipboard.writeText(form.password).then(() => {
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    });
}

const permissionGroups = computed<PermissionGroup[]>(() => {
    const query = permissionSearchQuery.value.trim().toLowerCase();
    const map = new Map<string, AclOption[]>();

    const options = props.permissions.map((p) => ({
        name: p.name,
        label: p.label,
    }));

    for (const permission of options) {
        if (
            query !== '' &&
            !permission.label.toLowerCase().includes(query) &&
            !permission.name.toLowerCase().includes(query)
        ) {
            continue;
        }
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

function isAllGroupSelected(group: PermissionGroup): boolean {
    return group.items.every((item) => form.permissions.includes(item.name));
}

function toggleGroupPermissions(group: PermissionGroup) {
    const allSelected = isAllGroupSelected(group);
    const itemNames = group.items.map((item) => item.name);

    if (allSelected) {
        form.permissions = form.permissions.filter(
            (p) => !itemNames.includes(p),
        );
    } else {
        const next = new Set([...form.permissions, ...itemNames]);
        form.permissions = Array.from(next).sort((a, b) => a.localeCompare(b));
    }
}

function selectAllPermissions() {
    const allNames = props.permissions.map((p) => p.name);
    form.permissions = [...allNames].sort((a, b) => a.localeCompare(b));
}

function clearAllPermissions() {
    form.permissions = [];
}

function submit() {
    if (isEditMode.value && props.user) {
        form.put(`/admin/users/accounts/${props.user.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                emit('success');
            },
        });
    } else {
        form.post('/admin/users/accounts', {
            preserveScroll: true,
            onSuccess: () => {
                emit('success');
                form.reset();
            },
        });
    }
}
</script>

<template>
    <form @submit.prevent="submit" class="flex flex-col gap-6">
        <!-- Account Information Section -->
        <div class="space-y-4">
            <div class="flex items-center gap-2 border-b pb-2">
                <span class="rounded bg-primary/10 p-1 text-primary">
                    <Shield class="h-4 w-4" />
                </span>
                <div>
                    <h3
                        class="text-sm font-semibold tracking-tight text-foreground"
                    >
                        Account Information
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        Provide basic user profile information.
                    </p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-1.5">
                    <Label for="user-name">Full Name</Label>
                    <Input
                        id="user-name"
                        v-model="form.name"
                        placeholder="John Doe"
                        required
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="space-y-1.5">
                    <Label for="user-email">Email Address</Label>
                    <Input
                        id="user-email"
                        v-model="form.email"
                        type="email"
                        placeholder="john.doe@example.com"
                        required
                    />
                    <InputError :message="form.errors.email" />
                </div>
            </div>
        </div>

        <!-- Password Configuration Section -->
        <div class="space-y-4">
            <div class="flex items-center gap-2 border-b pb-2">
                <span class="rounded bg-primary/10 p-1 text-primary">
                    <Key class="h-4 w-4" />
                </span>
                <div>
                    <h3
                        class="text-sm font-semibold tracking-tight text-foreground"
                    >
                        Security Credentials
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        {{
                            isEditMode
                                ? 'Set a new password if you wish to change it.'
                                : 'Define access credentials for the user.'
                        }}
                    </p>
                </div>
            </div>

            <div
                v-if="isEditMode"
                class="flex gap-2 rounded-lg border border-yellow-500/20 bg-yellow-500/5 p-3 text-xs text-yellow-600 dark:text-yellow-400"
            >
                <Info class="mt-0.5 h-4 w-4 shrink-0" />
                <span
                    >Leave password fields empty to keep the existing user
                    password.</span
                >
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <Label for="user-password">
                            {{ isEditMode ? 'New Password' : 'Password' }}
                        </Label>
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                                @click="generatePassword"
                            >
                                <Wand2 class="h-3.5 w-3.5" />
                                Generate
                            </button>
                            <button
                                v-if="form.password"
                                type="button"
                                class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                                @click="copyPassword"
                            >
                                <Check
                                    v-if="copied"
                                    class="h-3.5 w-3.5 text-green-600 dark:text-green-400"
                                />
                                <Copy v-else class="h-3.5 w-3.5" />
                                {{ copied ? 'Copied' : 'Copy' }}
                            </button>
                        </div>
                    </div>
                    <div class="relative">
                        <Input
                            id="user-password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            :required="!isEditMode"
                            class="pr-10"
                        />
                        <button
                            type="button"
                            class="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            @click="showPassword = !showPassword"
                            tabindex="-1"
                        >
                            <Eye v-if="showPassword" class="h-4 w-4" />
                            <EyeOff v-else class="h-4 w-4" />
                        </button>
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <div class="space-y-1.5">
                    <Label for="user-password-confirmation"
                        >Confirm Password</Label
                    >
                    <div class="relative">
                        <Input
                            id="user-password-confirmation"
                            v-model="form.password_confirmation"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            :required="!isEditMode"
                            class="pr-10"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Access Rights Section (Roles & Direct Permissions) -->
        <div class="grid gap-6 md:grid-cols-[260px_1fr]">
            <!-- Roles Column -->
            <div class="space-y-3">
                <div>
                    <Label class="text-sm font-semibold text-foreground"
                        >Assign Roles</Label
                    >
                    <p class="text-[11px] text-muted-foreground">
                        Select one or more user roles.
                    </p>
                </div>

                <div class="flex flex-col gap-2">
                    <div
                        v-for="role in roles"
                        :key="`role-${role.id}`"
                        class="flex cursor-pointer items-start justify-between gap-3 rounded-lg border p-3 transition-all hover:bg-muted/50"
                        :class="
                            form.roles.includes(role.name)
                                ? 'border-primary/50 bg-primary/5 dark:bg-primary/10'
                                : 'border-border/70 bg-card'
                        "
                        @click="toggleRole(role.name)"
                    >
                        <div class="flex items-start gap-2.5">
                            <Checkbox
                                :checked="form.roles.includes(role.name)"
                                class="pointer-events-none mt-1"
                            />
                            <div class="space-y-0.5">
                                <span
                                    class="text-xs leading-tight font-semibold text-foreground"
                                    >{{ role.label }}</span
                                >
                                <p class="text-[10px] text-muted-foreground">
                                    {{ role.name }}
                                </p>
                            </div>
                        </div>
                        <Badge
                            variant="secondary"
                            class="shrink-0 px-1.5 py-0 text-[10px] font-normal"
                        >
                            {{ role.permissions.length }} perm
                        </Badge>
                    </div>
                </div>
                <InputError :message="form.errors.roles" />
            </div>

            <!-- Direct Permissions Column -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <Label class="text-sm font-semibold text-foreground"
                            >Direct Permissions</Label
                        >
                        <p class="text-[11px] text-muted-foreground">
                            Grant permission directly to this user.
                        </p>
                    </div>
                    <Badge variant="outline" class="text-xs">
                        {{ form.permissions.length }} selected
                    </Badge>
                </div>

                <div class="space-y-3">
                    <!-- Search and Actions -->
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <Search
                                class="absolute top-2 left-2.5 h-3.5 w-3.5 text-muted-foreground"
                            />
                            <Input
                                v-model="permissionSearchQuery"
                                placeholder="Search permissions..."
                                class="h-8 pl-8 text-xs"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 text-xs font-normal"
                            @click="selectAllPermissions"
                        >
                            Select All
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 text-xs font-normal"
                            @click="clearAllPermissions"
                        >
                            Clear All
                        </Button>
                    </div>

                    <!-- Groups List -->
                    <div
                        class="max-h-[280px] space-y-4 overflow-y-auto rounded-lg border border-border/70 bg-card p-3.5"
                    >
                        <div
                            v-if="permissionGroups.length === 0"
                            class="py-6 text-center text-xs text-muted-foreground"
                        >
                            No permissions match your search query.
                        </div>

                        <div
                            v-for="group in permissionGroups"
                            :key="`group-${group.key}`"
                            class="space-y-2"
                        >
                            <!-- Group Title & Toggle -->
                            <div
                                class="flex items-center justify-between border-b pb-1"
                            >
                                <span
                                    class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    {{ group.label }}
                                </span>
                                <button
                                    type="button"
                                    class="text-[10px] font-semibold text-primary hover:underline"
                                    @click="toggleGroupPermissions(group)"
                                >
                                    {{
                                        isAllGroupSelected(group)
                                            ? 'Deselect Group'
                                            : 'Select Group'
                                    }}
                                </button>
                            </div>

                            <!-- Group Items Grid -->
                            <div class="grid gap-2 sm:grid-cols-2">
                                <div
                                    v-for="permission in group.items"
                                    :key="`perm-${permission.name}`"
                                    class="flex cursor-pointer items-start gap-2 rounded-md border border-transparent p-2 transition-all hover:bg-muted/50"
                                    :class="
                                        form.permissions.includes(
                                            permission.name,
                                        )
                                            ? 'border-primary/20 bg-primary/5 dark:bg-primary/10'
                                            : ''
                                    "
                                    @click="togglePermission(permission.name)"
                                >
                                    <Checkbox
                                        :checked="
                                            form.permissions.includes(
                                                permission.name,
                                            )
                                        "
                                        class="pointer-events-none mt-0.5"
                                    />
                                    <div class="space-y-0.5">
                                        <span
                                            class="block text-xs leading-tight font-medium text-foreground"
                                        >
                                            {{ permission.label }}
                                        </span>
                                        <span
                                            class="block text-[9px] leading-none text-muted-foreground"
                                        >
                                            {{ permission.name }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <InputError :message="form.errors.permissions" />
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end gap-2 border-t pt-4">
            <Button
                type="button"
                variant="outline"
                :disabled="form.processing"
                @click="emit('cancel')"
            >
                Cancel
            </Button>
            <Button type="submit" :disabled="form.processing">
                {{
                    form.processing
                        ? 'Saving...'
                        : isEditMode
                          ? 'Save Changes'
                          : 'Create User'
                }}
            </Button>
        </div>
    </form>
</template>
