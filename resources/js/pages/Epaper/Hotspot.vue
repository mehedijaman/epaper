<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import PublicFooter from '@/components/epaper/PublicFooter.vue';
import PublicHeader from '@/components/epaper/PublicHeader.vue';
import { Button } from '@/components/ui/button';

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
</script>

<template>
    <Head :title="`Hotspot ${hotspot.id}`" />

    <div class="flex min-h-screen flex-col bg-slate-100 text-slate-900">
        <PublicHeader :logo-url="logoUrl" :site-url="settings.site_url" />

        <main class="mx-auto w-full max-w-4xl flex-1 px-4 py-6 sm:px-6 lg:py-8">
            <div>
                <div class="flex flex-wrap items-center justify-between gap-3  border-slate-200 bg-white p-4 shadow-sm">
                    <Button variant="outline" size="sm" type="button" @click="closeTab">
                        Close
                    </Button>
                    <Button variant="default" size="sm" type="button" @click="copyLink">
                        {{ copyButtonLabel }}
                    </Button>
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
        />
    </div>
</template>
