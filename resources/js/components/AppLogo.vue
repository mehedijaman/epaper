<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
const logoUrl = computed(() => page.props.site?.logo_url ?? null);
const siteName = computed(() => page.props.site?.site_name || 'ePaper');
</script>

<template>
    <template v-if="logoUrl">
        <img
            :src="logoUrl"
            :alt="siteName"
            class="max-h-10 w-auto  object-contain"
        />
    </template>
    <template v-else>
        <div
            class="flex aspect-square size-8 shrink-0 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
        >
            <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
        </div>
        <div class="ml-1 grid flex-1 text-left text-sm">
            <span class="mb-0.5 truncate leading-tight font-semibold">{{ siteName }}</span>
        </div>
    </template>
</template>
