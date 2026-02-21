<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem, NavSection } from '@/types';

const props = defineProps<{
    sections: NavSection[];
}>();

const { currentUrl, isCurrentUrl } = useCurrentUrl();
const sections = computed(() => props.sections.filter((section) => section.items.length > 0));

function startsWithCurrentPath(pathPrefix: string): boolean {
    if (!pathPrefix.startsWith('/')) {
        return false;
    }

    return currentUrl.value === pathPrefix || currentUrl.value.startsWith(`${pathPrefix}/`);
}

function isItemActive(item: NavItem): boolean {
    if (isCurrentUrl(item.href, currentUrl.value)) {
        return true;
    }

    if (item.activeHrefs?.some((href) => isCurrentUrl(href, currentUrl.value))) {
        return true;
    }

    return item.activeStartsWith?.some(startsWithCurrentPath) ?? false;
}
</script>

<template>
    <template v-for="section in sections" :key="section.label">
        <SidebarGroup class="px-2 py-0">
            <SidebarGroupLabel>{{ section.label }}</SidebarGroupLabel>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in section.items" :key="item.title">
                    <SidebarMenuButton
                        as-child
                        :is-active="isItemActive(item)"
                        :tooltip="item.title"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" v-if="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    </template>
</template>
