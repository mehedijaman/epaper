<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import {
    buildEpAdminMenuSections,
    type EpAdminAbilityKey,
} from '@/config/admin-menu';
import type { Auth, NavSection } from '@/types';
import AppLogo from './AppLogo.vue';

type AuthProps = {
    auth: {
        abilities?: Auth['abilities'];
    };
};

const page = usePage<AuthProps>();
const abilityFlags = computed<Record<EpAdminAbilityKey, boolean>>(() => ({
    categories_manage: page.props.auth.abilities?.categories_manage ?? false,
    ads_manage: page.props.auth.abilities?.ads_manage ?? false,
    settings_manage: page.props.auth.abilities?.settings_manage ?? false,
    editions_manage: page.props.auth.abilities?.editions_manage ?? false,
}));

const mainNavSections = computed<NavSection[]>(() => {
    return buildEpAdminMenuSections(abilityFlags.value);
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/admin">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :sections="mainNavSections" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
