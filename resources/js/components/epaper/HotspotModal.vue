<script setup lang="ts">
import { Copy, Facebook, MessageCircle, Printer, Send, Twitter } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

const props = defineProps<{
    open: boolean;
    activeHotspotId: number | null;
    currentPageNo: number | null;
    previewUrl: string | null;
    loading: boolean;
    linkedPreviewUrl: string | null;
    linkedLoading: boolean;
    linkedPageNo: number | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

function handleOpenChange(isOpen: boolean): void {
    if (!isOpen) {
        emit('close');
    }
}

function hotspotShareUrl(hotspotId: number | null): string {
    if (typeof window === 'undefined') {
        return '';
    }

    const shareUrl = new URL(window.location.href);
    shareUrl.hash = hotspotId !== null ? `hotspot-${hotspotId}` : '';

    return shareUrl.toString();
}

function shareText(): string {
    if (props.currentPageNo === null) {
        return 'Check this ePaper hotspot';
    }

    return `Check this ePaper hotspot on page ${props.currentPageNo}`;
}

async function copyLink(): Promise<void> {
    const url = hotspotShareUrl(props.activeHotspotId);

    if (url === '') {
        return;
    }

    if (typeof navigator === 'undefined' || navigator.clipboard === undefined) {
        return;
    }

    try {
        await navigator.clipboard.writeText(url);
    } catch {
        // Ignore clipboard errors silently in icon-only footer UI.
    }
}

function openShareUrl(url: string): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.open(url, '_blank', 'noopener,noreferrer');
}

function shareToWhatsApp(): void {
    const url = hotspotShareUrl(props.activeHotspotId);

    if (url === '') {
        return;
    }

    const text = encodeURIComponent(`${shareText()} ${url}`);
    openShareUrl(`https://wa.me/?text=${text}`);
}

function shareToFacebook(): void {
    const url = hotspotShareUrl(props.activeHotspotId);

    if (url === '') {
        return;
    }

    openShareUrl(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`);
}

function shareToX(): void {
    const url = hotspotShareUrl(props.activeHotspotId);

    if (url === '') {
        return;
    }

    openShareUrl(
        `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(shareText())}`,
    );
}

function shareToTelegram(): void {
    const url = hotspotShareUrl(props.activeHotspotId);

    if (url === '') {
        return;
    }

    openShareUrl(
        `https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(shareText())}`,
    );
}

function printPreview(): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (props.previewUrl === null || props.previewUrl === '') {
        return;
    }

    const printWindow = window.open('', '_blank', 'noopener,noreferrer');

    if (printWindow === null) {
        return;
    }

    const printDocument = printWindow.document;
    printDocument.open();
    printDocument.write('<!doctype html><html><head><title>Hotspot Preview</title></head><body></body></html>');
    printDocument.close();

    if (printDocument.head === null || printDocument.body === null) {
        printWindow.close();
        return;
    }

    const style = printDocument.createElement('style');
    style.textContent = `
        @page { margin: 12mm; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
        }
        img {
            max-width: 100%;
            max-height: 100vh;
            object-fit: contain;
        }
    `;
    printDocument.head.append(style);

    const image = printDocument.createElement('img');
    image.alt = 'Hotspot preview';
    image.src = props.previewUrl;
    printDocument.body.append(image);

    const triggerPrint = () => {
        printWindow.focus();
        printWindow.print();
    };

    if (image.complete && image.naturalWidth > 0) {
        window.setTimeout(triggerPrint, 60);
    } else {
        image.addEventListener(
            'load',
            () => {
                window.setTimeout(triggerPrint, 60);
            },
            { once: true },
        );
        image.addEventListener(
            'error',
            () => {
                printWindow.close();
            },
            { once: true },
        );
    }

    printWindow.addEventListener(
        'afterprint',
        () => {
            printWindow.close();
        },
        { once: true },
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent :show-close-button="false" class="max-h-[90vh] overflow-y-auto p-4 sm:max-w-4xl sm:p-6">
            <div class="space-y-4">
                <div class="space-y-2">
                    <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                        <div v-if="loading" class="flex h-52 items-center justify-center">
                            <Spinner class="size-6" />
                        </div>
                        <img
                            v-else-if="previewUrl"
                            :src="previewUrl"
                            alt="Hotspot cropped preview"
                            class="h-auto max-h-[42vh] w-full object-contain"
                        />
                        <div v-else class="flex h-32 items-center justify-center px-4 text-sm text-muted-foreground">
                            Preview unavailable for this hotspot.
                        </div>
                    </div>
                </div>

                <div class="space-y-2">
                    <h4 class="text-sm font-semibold">
                        Linked hotspot
                        <span v-if="linkedPageNo !== null" class="font-normal text-muted-foreground">
                            (Page {{ linkedPageNo }})
                        </span>
                    </h4>
                    <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                        <div v-if="linkedLoading" class="flex h-52 items-center justify-center">
                            <Spinner class="size-6" />
                        </div>
                        <img
                            v-else-if="linkedPreviewUrl"
                            :src="linkedPreviewUrl"
                            alt="Linked hotspot cropped preview"
                            class="h-auto max-h-[42vh] w-full object-contain"
                        />
                        <div v-else class="flex h-24 items-center justify-center px-4 text-sm text-muted-foreground">
                            No linked hotspot preview found.
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    <Button variant="outline" size="icon" type="button" title="Copy link" aria-label="Copy link" @click="copyLink">
                        <Copy class="size-4" />
                    </Button>
                    <Button variant="outline" size="icon" type="button" title="Share to WhatsApp" aria-label="Share to WhatsApp" @click="shareToWhatsApp">
                        <MessageCircle class="size-4" />
                    </Button>
                    <Button variant="outline" size="icon" type="button" title="Share to Facebook" aria-label="Share to Facebook" @click="shareToFacebook">
                        <Facebook class="size-4" />
                    </Button>
                    <Button variant="outline" size="icon" type="button" title="Share to X" aria-label="Share to X" @click="shareToX">
                        <Twitter class="size-4" />
                    </Button>
                    <Button variant="outline" size="icon" type="button" title="Share to Telegram" aria-label="Share to Telegram" @click="shareToTelegram">
                        <Send class="size-4" />
                    </Button>
                    <Button variant="outline" size="icon" type="button" title="Print hotspot preview" aria-label="Print hotspot preview" @click="printPreview">
                        <Printer class="size-4" />
                    </Button>
                </div>

                <Button variant="outline" class="w-full sm:w-auto" @click="emit('close')">
                    Close
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
