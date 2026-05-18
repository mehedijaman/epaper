<script setup lang="ts">
import { Newspaper } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    logoUrl: string | null;
    siteUrl: string;
    copyright: string;
    editorInfo: string;
    contactInfo: string;
    socialFacebook?: string;
    socialX?: string;
    socialYoutube?: string;
    socialLinkedin?: string;
    socialInstagram?: string;
    socialPinterest?: string;
}>();

type SocialLink = { href: string; label: string; icon: string };

const socialLinks = computed<SocialLink[]>(() => {
    const links: SocialLink[] = [];
    if (props.socialFacebook) {
        links.push({ href: props.socialFacebook, label: 'Facebook', icon: 'facebook' });
    }
    if (props.socialX) {
        links.push({ href: props.socialX, label: 'X', icon: 'x' });
    }
    if (props.socialYoutube) {
        links.push({ href: props.socialYoutube, label: 'YouTube', icon: 'youtube' });
    }
    if (props.socialLinkedin) {
        links.push({ href: props.socialLinkedin, label: 'LinkedIn', icon: 'linkedin' });
    }
    if (props.socialInstagram) {
        links.push({ href: props.socialInstagram, label: 'Instagram', icon: 'instagram' });
    }
    if (props.socialPinterest) {
        links.push({ href: props.socialPinterest, label: 'Pinterest', icon: 'pinterest' });
    }
    return links;
});
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
