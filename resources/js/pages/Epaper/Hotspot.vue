<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Check, Copy, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import PublicFooter from '@/components/epaper/PublicFooter.vue';
import PublicHeader from '@/components/epaper/PublicHeader.vue';

type HotspotDetail = {
    id: number;
    x: number;
    y: number;
    w: number;
    h: number;
    label: string | null;
    relation_kind: 'next' | 'previous';
    target_page_no: number;
};

type FooterSettings = {
    footer_editor_info: string;
    footer_contact_info: string;
    footer_copyright: string;
    site_url: string;
    social_facebook: string;
    social_x: string;
    social_youtube: string;
    social_linkedin: string;
    social_instagram: string;
    social_pinterest: string;
};

const props = defineProps<{
    date: string;
    pageNo: number;
    viewerUrl: string;
    targetUrl: string | null;
    previewUrl: string;
    targetPreviewUrl: string | null;
    logoUrl: string | null;
    settings: FooterSettings;
    hotspot: HotspotDetail;
    targetHotspot: HotspotDetail | null;
}>();

const copyState = ref<'idle' | 'copied' | 'error'>('idle');
let copyTimeoutId: number | null = null;

const currentUrl = computed(() =>
    typeof window !== 'undefined' ? window.location.href : '',
);

const shareTitle = computed(() => props.hotspot.label?.trim() || `Hotspot ${props.hotspot.id}`);

const copyButtonLabel = computed(() => {
    if (copyState.value === 'copied') {
        return 'Copied';
    }

    if (copyState.value === 'error') {
        return 'Copy failed';
    }

    return 'Copy Link';
});

type PreviewItem = {
    key: 'current' | 'target';
    src: string;
    alt: string;
};

const orderedPreviews = computed<PreviewItem[]>(() => {
    const currentPreview: PreviewItem = {
        key: 'current',
        src: props.previewUrl,
        alt: 'Hotspot preview',
    };

    if (props.targetHotspot === null || props.targetPreviewUrl === null) {
        return [currentPreview];
    }

    const targetPreview: PreviewItem = {
        key: 'target',
        src: props.targetPreviewUrl,
        alt: 'Target hotspot preview',
    };

    return props.hotspot.relation_kind === 'previous'
        ? [targetPreview, currentPreview]
        : [currentPreview, targetPreview];
});

async function copyLink(): Promise<void> {
    if (typeof window === 'undefined') {
        return;
    }

    const url = window.location.href;

    try {
        if (navigator.clipboard !== undefined && window.isSecureContext) {
            await navigator.clipboard.writeText(url);
        } else {
            const textArea = document.createElement('textarea');
            textArea.value = url;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
        }

        copyState.value = 'copied';
    } catch {
        copyState.value = 'error';
    }

    if (copyTimeoutId !== null) {
        window.clearTimeout(copyTimeoutId);
    }

    copyTimeoutId = window.setTimeout(() => {
        copyState.value = 'idle';
        copyTimeoutId = null;
    }, 1800);
}

onBeforeUnmount(() => {
    if (copyTimeoutId !== null) {
        window.clearTimeout(copyTimeoutId);
    }
});

function closeTab(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.close();

    window.setTimeout(() => {
        if (!window.closed) {
            window.location.href = props.viewerUrl;
        }
    }, 120);
}

function openSharePopup(url: string): void {
    window.open(url, '_blank', 'width=640,height=480,noopener,noreferrer');
}

function shareOnFacebook(): void {
    openSharePopup(
        `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(currentUrl.value)}`,
    );
}

function shareOnX(): void {
    openSharePopup(
        `https://twitter.com/intent/tweet?url=${encodeURIComponent(currentUrl.value)}&text=${encodeURIComponent(shareTitle.value)}`,
    );
}

function shareOnWhatsApp(): void {
    openSharePopup(
        `https://wa.me/?text=${encodeURIComponent(`${shareTitle.value} ${currentUrl.value}`)}`,
    );
}

function shareOnLinkedIn(): void {
    openSharePopup(
        `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(currentUrl.value)}`,
    );
}
</script>

<template>
    <Head :title="`Hotspot ${hotspot.id}`" />

    <div class="flex min-h-screen flex-col bg-slate-100 text-slate-900">
        <PublicHeader :logo-url="logoUrl" :site-url="settings.site_url" />

        <main class="mx-auto w-full max-w-4xl flex-1 px-4 py-6 sm:px-6 lg:py-8">
            <div>
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-t-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <!-- Close button -->
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 transition-all hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                        @click="closeTab"
                    >
                        <X class="size-3.5" />
                        Close
                    </button>

                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Social share group -->
                        <div class="flex items-center gap-1.5">
                            <span class="text-[11px] font-semibold tracking-widest text-slate-400 uppercase">Share</span>
                            <!-- Facebook -->
                            <button
                                type="button"
                                title="Share on Facebook"
                                class="inline-flex size-8 items-center justify-center rounded-full bg-[#1877F2]/10 text-[#1877F2] transition-all hover:bg-[#1877F2] hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#1877F2]/50"
                                @click="shareOnFacebook"
                            >
                                <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />
                                </svg>
                            </button>
                            <!-- X / Twitter -->
                            <button
                                type="button"
                                title="Share on X"
                                class="inline-flex size-8 items-center justify-center rounded-full bg-slate-100 text-slate-800 transition-all hover:bg-slate-900 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                                @click="shareOnX"
                            >
                                <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.76l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
                                </svg>
                            </button>
                            <!-- WhatsApp -->
                            <button
                                type="button"
                                title="Share on WhatsApp"
                                class="inline-flex size-8 items-center justify-center rounded-full bg-[#25D366]/10 text-[#25D366] transition-all hover:bg-[#25D366] hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#25D366]/50"
                                @click="shareOnWhatsApp"
                            >
                                <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z" />
                                </svg>
                            </button>
                            <!-- LinkedIn -->
                            <button
                                type="button"
                                title="Share on LinkedIn"
                                class="inline-flex size-8 items-center justify-center rounded-full bg-[#0A66C2]/10 text-[#0A66C2] transition-all hover:bg-[#0A66C2] hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0A66C2]/50"
                                @click="shareOnLinkedIn"
                            >
                                <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z" />
                                    <rect x="2" y="9" width="4" height="12" />
                                    <circle cx="4" cy="4" r="2" />
                                </svg>
                            </button>
                        </div>

                        <!-- Divider -->
                        <div class="h-5 w-px bg-slate-200"></div>

                        <!-- Copy link button -->
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition-all focus-visible:outline-none focus-visible:ring-2"
                            :class="
                                copyState === 'copied'
                                    ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 focus-visible:ring-emerald-400'
                                    : copyState === 'error'
                                      ? 'bg-red-50 text-red-700 ring-1 ring-red-200 focus-visible:ring-red-400'
                                      : 'bg-slate-900 text-white hover:bg-slate-700 focus-visible:ring-slate-400'
                            "
                            @click="copyLink"
                        >
                            <Check v-if="copyState === 'copied'" class="size-3.5" />
                            <Copy v-else class="size-3.5" />
                            {{ copyButtonLabel }}
                        </button>
                    </div>
                </div>

                <div
                    v-for="preview in orderedPreviews"
                    :key="preview.key"
                >
                    <img
                        :src="preview.src"
                        :alt="preview.alt"
                        class="mx-auto block h-auto w-full"
                    />
                </div>
            </div>
        </main>

        <PublicFooter
            :logo-url="logoUrl"
            :site-url="settings.site_url"
            :copyright="settings.footer_copyright"
            :editor-info="settings.footer_editor_info"
            :contact-info="settings.footer_contact_info"
            :social-facebook="settings.social_facebook"
            :social-x="settings.social_x"
            :social-youtube="settings.social_youtube"
            :social-linkedin="settings.social_linkedin"
            :social-instagram="settings.social_instagram"
            :social-pinterest="settings.social_pinterest"
        />
    </div>
</template>
