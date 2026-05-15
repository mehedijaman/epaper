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

                <!-- Social links -->
                <div v-if="socialLinks.length > 0" class="flex items-center justify-center gap-2 pt-1 sm:justify-start">
                    <a
                        v-for="link in socialLinks"
                        :key="link.icon"
                        :href="link.href"
                        :aria-label="link.label"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex size-7 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                    >
                        <!-- Facebook -->
                        <svg v-if="link.icon === 'facebook'" xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                        </svg>
                        <!-- X / Twitter -->
                        <svg v-else-if="link.icon === 'x'" xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                        </svg>
                        <!-- YouTube -->
                        <svg v-else-if="link.icon === 'youtube'" xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 0 0-1.95 1.96A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58A2.78 2.78 0 0 0 3.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.95A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58zM9.75 15.02V8.98L15.5 12z"/>
                        </svg>
                        <!-- LinkedIn -->
                        <svg v-else-if="link.icon === 'linkedin'" xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/>
                        </svg>
                        <!-- Instagram -->
                        <svg v-else-if="link.icon === 'instagram'" xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/>
                        </svg>
                        <!-- Pinterest -->
                        <svg v-else-if="link.icon === 'pinterest'" xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 2C6.477 2 2 6.477 2 12c0 4.236 2.636 7.855 6.356 9.312-.088-.791-.167-2.005.035-2.868.181-.78 1.172-4.97 1.172-4.97s-.299-.598-.299-1.482c0-1.388.806-2.428 1.808-2.428.852 0 1.265.64 1.265 1.408 0 .858-.546 2.141-.828 3.329-.236.995.499 1.806 1.476 1.806 1.771 0 3.135-1.867 3.135-4.56 0-2.385-1.714-4.052-4.161-4.052-2.834 0-4.496 2.126-4.496 4.322 0 .856.33 1.773.741 2.274a.3.3 0 0 1 .069.286c-.076.313-.243.995-.276 1.134-.044.183-.146.222-.337.134-1.249-.581-2.03-2.407-2.03-3.874 0-3.154 2.292-6.052 6.608-6.052 3.469 0 6.165 2.473 6.165 5.776 0 3.447-2.173 6.22-5.19 6.22-1.013 0-1.966-.527-2.292-1.148l-.623 2.378c-.226.869-.835 1.958-1.244 2.621.937.29 1.931.446 2.962.446 5.522 0 10-4.477 10-10S17.522 2 12 2z"/>
                        </svg>
                    </a>
                </div>
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
