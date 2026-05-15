<script setup lang="ts">
import { Newspaper } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    logoUrl: string | null;
    siteUrl: string;
    socialFacebook?: string;
    socialX?: string;
    socialYoutube?: string;
    socialLinkedin?: string;
    socialInstagram?: string;
    socialPinterest?: string;
}>();

function todayInDhaka(): string {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Dhaka',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());
    const y = parts.find((p) => p.type === 'year')?.value ?? '';
    const m = parts.find((p) => p.type === 'month')?.value ?? '';
    const d = parts.find((p) => p.type === 'day')?.value ?? '';
    return `${y}-${m}-${d}`;
}

const formattedDate = computed(() => {
    const date = new Date(todayInDhaka() + 'T00:00:00');
    return new Intl.DateTimeFormat('en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(date);
});

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
    <header class="border-b border-slate-200 bg-white shadow-sm">
        <div class="mx-auto max-w-7xl px-4">

            <!-- Mobile: logo centered, date below, social icons below that -->
            <div class="flex flex-col items-center gap-1 py-3 sm:hidden">
                <a :href="siteUrl || '/'">
                    <img
                        v-if="logoUrl"
                        :src="logoUrl"
                        alt="Newspaper logo"
                        class="h-10 w-auto object-contain"
                    />
                    <div v-else class="inline-flex items-center gap-2">
                        <Newspaper class="size-5 text-slate-700" />
                        <span class="text-lg font-bold tracking-tight text-slate-900">ePaper</span>
                    </div>
                </a>
                <p class="text-xs font-medium text-slate-500">{{ formattedDate }}</p>
                <div v-if="socialLinks.length > 0" class="flex items-center gap-1 pt-0.5">
                    <a
                        v-for="link in socialLinks"
                        :key="link.icon"
                        :href="link.href"
                        :aria-label="link.label"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex size-7 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                    >
                        <svg v-if="link.icon === 'facebook'" xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                        <svg v-else-if="link.icon === 'x'" xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        <svg v-else-if="link.icon === 'youtube'" xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 0 0-1.95 1.96A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58A2.78 2.78 0 0 0 3.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.95A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58zM9.75 15.02V8.98L15.5 12z"/></svg>
                        <svg v-else-if="link.icon === 'linkedin'" xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg>
                        <svg v-else-if="link.icon === 'instagram'" xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/></svg>
                        <svg v-else-if="link.icon === 'pinterest'" xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.477 2 2 6.477 2 12c0 4.236 2.636 7.855 6.356 9.312-.088-.791-.167-2.005.035-2.868.181-.78 1.172-4.97 1.172-4.97s-.299-.598-.299-1.482c0-1.388.806-2.428 1.808-2.428.852 0 1.265.64 1.265 1.408 0 .858-.546 2.141-.828 3.329-.236.995.499 1.806 1.476 1.806 1.771 0 3.135-1.867 3.135-4.56 0-2.385-1.714-4.052-4.161-4.052-2.834 0-4.496 2.126-4.496 4.322 0 .856.33 1.773.741 2.274a.3.3 0 0 1 .069.286c-.076.313-.243.995-.276 1.134-.044.183-.146.222-.337.134-1.249-.581-2.03-2.407-2.03-3.874 0-3.154 2.292-6.052 6.608-6.052 3.469 0 6.165 2.473 6.165 5.776 0 3.447-2.173 6.22-5.19 6.22-1.013 0-1.966-.527-2.292-1.148l-.623 2.378c-.226.869-.835 1.958-1.244 2.621.937.29 1.931.446 2.962.446 5.522 0 10-4.477 10-10S17.522 2 12 2z"/></svg>
                    </a>
                </div>
            </div>

            <!-- Desktop: social icons (left) | logo (center) | date (right) -->
            <div class="hidden items-center justify-between py-3 sm:flex">
                <!-- Left: social icons -->
                <div class="flex items-center gap-1.5">
                    <a
                        v-for="link in socialLinks"
                        :key="link.icon"
                        :href="link.href"
                        :aria-label="link.label"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex size-8 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                    >
                        <svg v-if="link.icon === 'facebook'" xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                        <svg v-else-if="link.icon === 'x'" xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        <svg v-else-if="link.icon === 'youtube'" xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46a2.78 2.78 0 0 0-1.95 1.96A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58A2.78 2.78 0 0 0 3.41 19.6C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.95A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58zM9.75 15.02V8.98L15.5 12z"/></svg>
                        <svg v-else-if="link.icon === 'linkedin'" xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg>
                        <svg v-else-if="link.icon === 'instagram'" xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/></svg>
                        <svg v-else-if="link.icon === 'pinterest'" xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.477 2 2 6.477 2 12c0 4.236 2.636 7.855 6.356 9.312-.088-.791-.167-2.005.035-2.868.181-.78 1.172-4.97 1.172-4.97s-.299-.598-.299-1.482c0-1.388.806-2.428 1.808-2.428.852 0 1.265.64 1.265 1.408 0 .858-.546 2.141-.828 3.329-.236.995.499 1.806 1.476 1.806 1.771 0 3.135-1.867 3.135-4.56 0-2.385-1.714-4.052-4.161-4.052-2.834 0-4.496 2.126-4.496 4.322 0 .856.33 1.773.741 2.274a.3.3 0 0 1 .069.286c-.076.313-.243.995-.276 1.134-.044.183-.146.222-.337.134-1.249-.581-2.03-2.407-2.03-3.874 0-3.154 2.292-6.052 6.608-6.052 3.469 0 6.165 2.473 6.165 5.776 0 3.447-2.173 6.22-5.19 6.22-1.013 0-1.966-.527-2.292-1.148l-.623 2.378c-.226.869-.835 1.958-1.244 2.621.937.29 1.931.446 2.962.446 5.522 0 10-4.477 10-10S17.522 2 12 2z"/></svg>
                    </a>
                </div>

                <!-- Center: Logo -->
                <a :href="siteUrl || '/'">
                    <img
                        v-if="logoUrl"
                        :src="logoUrl"
                        alt="Newspaper logo"
                        class="h-12 w-auto object-contain"
                    />
                    <div v-else class="inline-flex items-center gap-2">
                        <Newspaper class="size-5 text-slate-700" />
                        <span class="text-lg font-bold tracking-tight text-slate-900">ePaper</span>
                    </div>
                </a>

                <!-- Right: date -->
                <p class="text-right text-sm font-medium text-slate-500">{{ formattedDate }}</p>
            </div>

        </div>
    </header>
</template>
