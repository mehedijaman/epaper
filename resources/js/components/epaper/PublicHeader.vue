<script setup lang="ts">
import { Newspaper } from 'lucide-vue-next';

defineProps<{
    logoUrl: string | null;
    siteUrl: string;
}>();
</script>

<template>
    <header class="border-b border-slate-200 bg-white shadow-sm">
        <div class="mx-auto flex max-w-7xl items-center justify-center px-4 py-3">
            <a :href="siteUrl || '/'">
                <img
                    v-if="logoUrl"
                    :src="logoUrl"
                    alt="Newspaper logo"
                    class="h-10 w-auto object-contain sm:h-12"
                />
                <div v-else class="inline-flex items-center gap-2">
                    <Newspaper class="size-5 text-slate-700" />
                    <span class="text-lg font-bold tracking-tight text-slate-900">ePaper</span>
                </div>
            </a>
        </div>
    </header>
</template>
