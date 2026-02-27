<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Newspaper } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
} from '@/components/ui/card';

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
        <header class="border-b border-slate-200 bg-white shadow-sm">
            <div
                class="mx-auto flex max-w-7xl items-center justify-center px-4 py-3"
            >
                <img
                    v-if="logoUrl"
                    :src="logoUrl"
                    alt="Newspaper logo"
                    class="h-10 w-auto object-contain sm:h-12"
                />
                <div v-else class="inline-flex items-center gap-2">
                    <Newspaper class="size-5 text-slate-700" />
                    <span class="text-lg font-bold tracking-tight text-slate-900"
                        >ePaper</span
                    >
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-4xl flex-1 px-4 py-6 sm:px-6 lg:py-8">
            <div class="space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <Button variant="outline" size="sm" type="button" @click="closeTab">
                        Close
                    </Button>
                    <Button variant="default" size="sm" type="button" @click="copyLink">
                        {{ copyButtonLabel }}
                    </Button>
                </div>

                <img
                    :src="previewUrl"
                    alt="Hotspot preview"
                    class="mx-auto block h-auto w-full"
                />

                <div
                    v-if="targetHotspot !== null && targetPreviewUrl !== null"
                    class="space-y-2"
                >
                    <img
                        :src="targetPreviewUrl"
                        alt="Target hotspot preview"
                        class="mx-auto block h-auto w-full"
                    />
                </div>
            </div>
        </main>

        <footer class="border-t border-slate-200 bg-white py-4">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-4 px-4 text-xs text-slate-600 sm:flex-row sm:items-end sm:justify-between sm:text-sm"
            >
                <div class="space-y-2 text-center sm:text-left">
                    <img
                        v-if="logoUrl"
                        :src="logoUrl"
                        alt="Newspaper logo"
                        class="mx-auto h-10 w-auto object-contain sm:mx-0 sm:h-12"
                    />
                    <div v-else class="inline-flex items-center gap-2">
                        <Newspaper class="size-4 text-slate-500" />
                        <span class="text-sm font-semibold text-slate-700"
                            >ePaper</span
                        >
                    </div>
                    <p class="text-slate-500">
                        {{ settings.footer_copyright }}
                    </p>
                </div>

                <div
                    class="max-w-lg space-y-1 text-center text-slate-500 sm:text-right"
                >
                    <p>{{ settings.footer_editor_info }}</p>
                    <p>{{ settings.footer_contact_info }}</p>
                </div>
            </div>
        </footer>
    </div>
</template>
