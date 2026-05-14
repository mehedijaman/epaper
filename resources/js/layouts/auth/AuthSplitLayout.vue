<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { login } from '@/routes';

const page = usePage();
const name = page.props.name;
const logoUrl = computed(() => page.props.site?.logo_url ?? null);
const siteName = computed(() => page.props.site?.site_name || 'ePaper');

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0"
    >
        <div
            class="relative hidden h-full flex-col bg-muted p-10 text-white lg:flex dark:border-r"
        >
            <div class="absolute inset-0 bg-zinc-900" />
            <Link
                :href="login()"
                class="relative z-20 flex items-center text-lg font-medium"
            >
                <img
                    v-if="logoUrl"
                    :src="logoUrl"
                    :alt="siteName"
                    class="max-h-9 w-auto max-w-[160px] object-contain brightness-0 invert"
                />
                <template v-else>
                    <AppLogoIcon class="mr-2 size-8 fill-current text-white" />
                    {{ name }}
                </template>
            </Link>
        </div>
        <div class="lg:p-8">
            <div
                class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]"
            >
                <div class="flex flex-col space-y-2 text-center">
                    <h1 class="text-xl font-medium tracking-tight" v-if="title">
                        {{ title }}
                    </h1>
                    <p class="text-sm text-muted-foreground" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
