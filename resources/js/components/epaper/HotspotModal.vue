<script setup lang="ts">
import { Copy, Facebook, MessageCircle, Printer, Send, Twitter } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Hotspot } from '@/types';

const props = defineProps<{
    open: boolean;
    hotspots: Hotspot[];
    activeHotspotId: number | null;
    currentPageNo: number | null;
    previewUrl: string | null;
    loading: boolean;
    linkedPreviewUrl: string | null;
    linkedLoading: boolean;
    linkedPageNo: number | null;
    linkedHotspotId: number | null;
}>();

const emit = defineEmits<{
    close: [];
    goTarget: [targetPageNo: number];
    goLinked: [payload: { linkedPageNo: number; linkedHotspotId: number | null }];
    selectHotspot: [hotspotId: number];
}>();

const shareFeedback = ref('');

const activeHotspot = computed<Hotspot | null>(() => {
    if (props.activeHotspotId === null) {
        return null;
    }

    return props.hotspots.find((hotspot) => hotspot.id === props.activeHotspotId) ?? null;
});

const canGoTarget = computed(() => {
    return activeHotspot.value !== null && activeHotspot.value.target_page_no > 0;
});

const canGoLinked = computed(() => {
    if (props.linkedPageNo === null || props.linkedPageNo <= 0) {
        return false;
    }

    if (props.currentPageNo === null) {
        return true;
    }

    if (props.linkedPageNo !== props.currentPageNo) {
        return true;
    }

    if (props.linkedHotspotId === null) {
        return false;
    }

    return props.linkedHotspotId !== props.activeHotspotId;
});

const linkedActionLabel = computed(() => {
    if (props.linkedPageNo === null) {
        return 'Go linked page -';
    }

    if (props.currentPageNo !== null && props.linkedPageNo === props.currentPageNo) {
        return `Open linked hotspot ${props.linkedHotspotId ?? '-'}`;
    }

    return `Go linked page ${props.linkedPageNo}`;
});

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
    const url = hotspotShareUrl(activeHotspot.value?.id ?? null);

    if (url === '') {
        shareFeedback.value = 'Unable to build share link.';
        return;
    }

    if (typeof navigator === 'undefined' || navigator.clipboard === undefined) {
        shareFeedback.value = 'Clipboard is not available on this browser.';
        return;
    }

    try {
        await navigator.clipboard.writeText(url);
        shareFeedback.value = 'Link copied.';
    } catch {
        shareFeedback.value = 'Copy failed.';
    }
}

function openShareUrl(url: string): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.open(url, '_blank', 'noopener,noreferrer');
}

function shareToWhatsApp(): void {
    const url = hotspotShareUrl(activeHotspot.value?.id ?? null);

    if (url === '') {
        return;
    }

    const text = encodeURIComponent(`${shareText()} ${url}`);
    openShareUrl(`https://wa.me/?text=${text}`);
}

function shareToFacebook(): void {
    const url = hotspotShareUrl(activeHotspot.value?.id ?? null);

    if (url === '') {
        return;
    }

    openShareUrl(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`);
}

function shareToX(): void {
    const url = hotspotShareUrl(activeHotspot.value?.id ?? null);

    if (url === '') {
        return;
    }

    openShareUrl(
        `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(shareText())}`,
    );
}

function shareToTelegram(): void {
    const url = hotspotShareUrl(activeHotspot.value?.id ?? null);

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
        shareFeedback.value = 'Preview unavailable for print.';
        return;
    }

    const printWindow = window.open('', '_blank', 'noopener,noreferrer');

    if (printWindow === null) {
        shareFeedback.value = 'Unable to open print window.';
        return;
    }

    const printDocument = printWindow.document;
    printDocument.open();
    printDocument.write('<!doctype html><html><head><title>Hotspot Preview</title></head><body></body></html>');
    printDocument.close();

    if (printDocument.head === null || printDocument.body === null) {
        shareFeedback.value = 'Unable to prepare print view.';
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
                shareFeedback.value = 'Unable to load preview for print.';
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

function goToTargetPage(): void {
    const targetPageNo = activeHotspot.value?.target_page_no ?? 0;

    if (targetPageNo <= 0) {
        return;
    }

    emit('goTarget', targetPageNo);
}

function goToLinkedPage(): void {
    if (!canGoLinked.value || props.linkedPageNo === null) {
        return;
    }

    emit('goLinked', {
        linkedPageNo: props.linkedPageNo,
        linkedHotspotId: props.linkedHotspotId,
    });
}

watch(
    () => props.activeHotspotId,
    () => {
        shareFeedback.value = '';
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="max-h-[90vh] overflow-y-auto p-4 sm:max-w-4xl sm:p-6">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
                <div class="space-y-4">
                    <div class="space-y-2">
                        <h4 class="text-sm font-semibold">Selected hotspot</h4>
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

                <section class="space-y-2">
                    <h4 class="text-sm font-semibold">Mapped hotspots on this page</h4>
                    <div class="max-h-[45vh] overflow-auto rounded-md border sm:max-h-[55vh]">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>ID</TableHead>
                                    <TableHead>Relation</TableHead>
                                    <TableHead>Target</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="hotspot in hotspots"
                                    :key="hotspot.id"
                                    class="cursor-pointer"
                                    :class="hotspot.id === activeHotspot?.id ? 'bg-sky-50' : ''"
                                    @click="emit('selectHotspot', hotspot.id)"
                                >
                                    <TableCell class="font-medium">#{{ hotspot.id }}</TableCell>
                                    <TableCell>{{ hotspot.relation_kind }}</TableCell>
                                    <TableCell>Page {{ hotspot.target_page_no }}</TableCell>
                                </TableRow>
                                <TableRow v-if="hotspots.length === 0">
                                    <TableCell colspan="3" class="text-center text-sm text-muted-foreground">
                                        No hotspots on this page.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </section>
            </div>

            <DialogFooter class="mt-2 flex-col items-stretch gap-3 sm:items-stretch">
                <section class="w-full space-y-2 rounded border bg-background p-3">
                    <h4 class="text-sm font-semibold">Share</h4>
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
                    <p v-if="shareFeedback !== ''" class="text-xs text-muted-foreground">
                        {{ shareFeedback }}
                    </p>
                </section>

                <div class="flex w-full flex-col gap-2 sm:flex-row sm:justify-end">
                    <Button variant="outline" class="w-full sm:w-auto" @click="emit('close')">
                        Close
                    </Button>
                    <Button
                        variant="outline"
                        class="w-full sm:w-auto"
                        :disabled="!canGoLinked"
                        @click="goToLinkedPage"
                    >
                        {{ linkedActionLabel }}
                    </Button>
                    <Button class="w-full sm:w-auto" :disabled="!canGoTarget" @click="goToTargetPage">
                        Go to page {{ activeHotspot?.target_page_no ?? '-' }}
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
