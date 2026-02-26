<script setup lang="ts">
import { computed } from 'vue';
import PublicViewerShell from '@/components/epaper/PublicViewerShell.vue';
import type {
    Edition,
    Page,
    ViewerCategoryItem,
    ViewerEditionItem,
    ViewerPageListItem,
} from '@/types';

const props = defineProps<{
    edition: Edition | null;
    selected_edition: ViewerEditionItem | null;
    editions_for_date: ViewerEditionItem[];
    current_page: Page | null;
    selected_page_no: number | null;
    selected_date: string | null;
    categories: ViewerCategoryItem[];
    available_dates: string[];
    logo_url: string | null;
    settings: {
        footer_editor_info: string;
        footer_contact_info: string;
        footer_copyright: string;
    };
}>();

const pages = computed<ViewerPageListItem[]>(() => {
    if (props.edition === null) {
        return [];
    }

    return props.edition.pages.map((item) => ({
        id: item.id,
        edition_id: item.edition_id,
        page_no: item.page_no,
        category_id: item.category_id,
        category_name: item.category_name,
        image_thumb_url: item.image_thumb_url,
        image_large_url: item.image_large_url,
        image_original_url: item.image_original_url,
        hotspots: item.hotspots,
    }));
});

const currentPage = computed<Page | null>(() => {
    if (props.current_page !== null) {
        return props.current_page;
    }

    if (props.edition === null || props.edition.pages.length === 0) {
        return null;
    }

    if (props.selected_page_no !== null) {
        const selected = props.edition.pages.find((item) => item.page_no === props.selected_page_no);

        if (selected !== undefined) {
            return selected;
        }
    }

    return props.edition.pages[0] ?? null;
});

const editionDate = computed<string | null>(() => {
    return props.selected_date ?? props.edition?.edition_date ?? null;
});
</script>

<template>
    <PublicViewerShell
        title="ePaper"
        :edition-date="editionDate"
        :page="currentPage"
        :pages="pages"
        :categories="categories"
        :editions-for-date="editions_for_date"
        :selected-edition="selected_edition"
        :available-dates="available_dates"
        :logo-url="logo_url"
        :settings="settings"
    />
</template>
