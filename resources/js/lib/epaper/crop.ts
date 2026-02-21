export type NormalizedRect = {
    x: number;
    y: number;
    w: number;
    h: number;
};

type PixelRect = {
    sx: number;
    sy: number;
    sw: number;
    sh: number;
};

export type CropPreviewOptions = {
    maxWidth?: number;
    minWidth?: number;
    quality?: number;
    mimeType?: 'image/jpeg' | 'image/png' | 'image/webp';
};

const MIN_RECT_SIZE = 0.000001;

function clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
}

function toPixelRect(rect: NormalizedRect, naturalWidth: number, naturalHeight: number): PixelRect | null {
    const normalizedX = clamp(rect.x, 0, 1);
    const normalizedY = clamp(rect.y, 0, 1);
    const normalizedW = clamp(rect.w, MIN_RECT_SIZE, 1);
    const normalizedH = clamp(rect.h, MIN_RECT_SIZE, 1);

    const startX = normalizedX * naturalWidth;
    const startY = normalizedY * naturalHeight;
    const endX = clamp((normalizedX + normalizedW) * naturalWidth, startX + 1, naturalWidth);
    const endY = clamp((normalizedY + normalizedH) * naturalHeight, startY + 1, naturalHeight);

    const sw = Math.max(1, endX - startX);
    const sh = Math.max(1, endY - startY);

    if (!Number.isFinite(sw) || !Number.isFinite(sh) || sw <= 0 || sh <= 0) {
        return null;
    }

    return {
        sx: clamp(startX, 0, naturalWidth - 1),
        sy: clamp(startY, 0, naturalHeight - 1),
        sw,
        sh,
    };
}

export function createHotspotPreviewDataUrl(
    image: HTMLImageElement,
    rect: NormalizedRect,
    options: CropPreviewOptions = {},
): string | null {
    const naturalWidth = image.naturalWidth;
    const naturalHeight = image.naturalHeight;

    if (naturalWidth <= 0 || naturalHeight <= 0) {
        return null;
    }

    const pixelRect = toPixelRect(rect, naturalWidth, naturalHeight);

    if (pixelRect === null) {
        return null;
    }

    const maxWidth = options.maxWidth ?? 600;
    const minWidth = options.minWidth ?? 220;
    const mimeType = options.mimeType ?? 'image/jpeg';
    const quality = options.quality ?? 0.9;

    const targetWidth = Math.round(clamp(Math.max(minWidth, pixelRect.sw), 1, maxWidth));
    const targetHeight = Math.max(1, Math.round(targetWidth * (pixelRect.sh / pixelRect.sw)));

    const canvas = document.createElement('canvas');
    canvas.width = targetWidth;
    canvas.height = targetHeight;

    const context = canvas.getContext('2d');

    if (context === null) {
        return null;
    }

    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';
    context.drawImage(
        image,
        pixelRect.sx,
        pixelRect.sy,
        pixelRect.sw,
        pixelRect.sh,
        0,
        0,
        targetWidth,
        targetHeight,
    );

    return canvas.toDataURL(mimeType, quality);
}
