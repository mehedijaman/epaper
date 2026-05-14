<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { login } from '@/routes';

const page = usePage();
const logoUrl = computed(() => page.props.site?.logo_url ?? null);
const siteName = computed(() => page.props.site?.site_name || 'ePaper');

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="login()"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <div class="mb-1 flex items-center justify-center">
                            <img
                                v-if="logoUrl"
                                :src="logoUrl"
                                :alt="siteName"
                                class="max-h-10 w-auto max-w-[160px] object-contain"
                            />
                            <div
                                v-else
                                class="flex h-9 w-9 items-center justify-center rounded-md"
                            >
                                <AppLogoIcon
                                    class="size-9 fill-current text-[var(--foreground)] dark:text-white"
                                />
                            </div>
                        </div>
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-center text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
