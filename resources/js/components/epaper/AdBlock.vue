<script setup lang="ts">
import { computed } from 'vue';
import type { Ad } from '@/types';

const props = defineProps<{
    ad: Ad;
}>();

const sanitizedEmbedCode = computed(() =>
    props.ad.type === 'embed' && props.ad.embed_code
        ? sanitizeEmbedCode(props.ad.embed_code)
        : '',
);

function sanitizeEmbedCode(embedCode: string): string {
    const withoutScripts = embedCode.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');

    if (typeof window === 'undefined' || typeof window.DOMParser === 'undefined') {
        return withoutScripts
            .replace(/\son\w+="[^"]*"/gi, '')
            .replace(/\son\w+='[^']*'/gi, '')
            .replace(/\s(href|src)=["']\s*javascript:[^"']*["']/gi, '');
    }

    const parser = new window.DOMParser();
    const documentNode = parser.parseFromString(`<div>${withoutScripts}</div>`, 'text/html');
    const wrapper = documentNode.body.firstElementChild;

    if (!(wrapper instanceof HTMLElement)) {
        return '';
    }

    wrapper.querySelectorAll('script').forEach((element) => element.remove());

    wrapper.querySelectorAll('*').forEach((element) => {
        for (const attribute of Array.from(element.attributes)) {
            const name = attribute.name.toLowerCase();
            const value = attribute.value;

            if (name.startsWith('on')) {
                element.removeAttribute(attribute.name);
                continue;
            }

            if ((name === 'href' || name === 'src') && /^\s*javascript:/i.test(value)) {
                element.removeAttribute(attribute.name);
            }
        }
    });

    return wrapper.innerHTML;
}
</script>

<template>
    <div class="rounded-lg border border-border/70 bg-card p-2">
        <a
            v-if="ad.type === 'image' && ad.image_url"
            :href="ad.click_url || '#'"
            target="_blank"
            rel="noopener noreferrer"
            class="block"
        >
            <img :src="ad.image_url" alt="Advertisement" class="h-auto w-full rounded object-cover" loading="lazy" />
        </a>

        <div
            v-else-if="ad.type === 'embed' && sanitizedEmbedCode"
            class="prose max-w-none"
            v-html="sanitizedEmbedCode"
        />

        <p v-else class="text-xs text-muted-foreground">Ad content unavailable.</p>
    </div>
</template>
