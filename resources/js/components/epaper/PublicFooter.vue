<script setup lang="ts">
import { Newspaper } from 'lucide-vue-next';

defineProps<{
    logoUrl: string | null;
    siteUrl: string;
    copyright: string;
    editorInfo: string;
    contactInfo: string;
}>();
</script>

<template>
    <footer class="border-t border-slate-200 bg-white py-4">
        <div
            class="mx-auto flex max-w-7xl flex-col gap-4 px-4 text-xs text-slate-600 sm:flex-row sm:items-end sm:justify-between sm:text-sm"
        >
            <div class="space-y-2 text-center sm:text-left">
                <a :href="siteUrl || '/'">
                    <img
                        v-if="logoUrl"
                        :src="logoUrl"
                        alt="Newspaper logo"
                        class="mx-auto h-10 w-auto object-contain sm:mx-0 sm:h-12"
                    />
                    <div v-else class="inline-flex items-center gap-2">
                        <Newspaper class="size-4 text-slate-500" />
                        <span class="text-sm font-semibold text-slate-700">ePaper</span>
                    </div>
                </a>
                <div
                    class="prose prose-xs max-w-none text-slate-500 [&_a]:underline [&_a:hover]:text-slate-700"
                    v-html="copyright"
                />
            </div>

            <div class="max-w-4xl space-y-3 text-center text-slate-500 sm:text-right">
                <div
                    v-if="editorInfo"
                    class="prose prose-xs max-w-none text-slate-500 sm:[&_*]:text-right [&_a]:underline [&_a:hover]:text-slate-700"
                    v-html="editorInfo"
                />
                <div
                    v-if="contactInfo"
                    class="prose prose-xs max-w-none text-slate-500 sm:[&_*]:text-right [&_a]:underline [&_a:hover]:text-slate-700"
                    v-html="contactInfo"
                />
            </div>
        </div>
    </footer>
</template>
