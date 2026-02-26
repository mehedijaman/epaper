<script setup lang="ts">
import PublicViewerShell from '@/components/epaper/PublicViewerShell.vue';
import type {
    Ad,
    Page,
    ViewerCategoryItem,
    ViewerEditionItem,
    ViewerPageListItem,
} from '@/types';

defineProps<{
    edition_date: string;
    current_page_no: number;
    page: Page;
    pages: ViewerPageListItem[];
    categories: ViewerCategoryItem[];
    editions_for_date: ViewerEditionItem[];
    selected_edition: ViewerEditionItem | null;
    available_dates: string[];
    prev_page_no: number | null;
    next_page_no: number | null;
    logo_url: string | null;
    adsBySlot: Record<string, Ad[]>;
    settings: {
        footer_editor_info: string;
        footer_contact_info: string;
        footer_copyright: string;
    };
}>();
</script>

<template>
    <PublicViewerShell
        :title="`Edition ${edition_date} - Page ${page.page_no}`"
        :edition-date="edition_date"
        :page="page"
        :pages="pages"
        :categories="categories"
        :editions-for-date="editions_for_date"
        :selected-edition="selected_edition"
        :available-dates="available_dates"
        :logo-url="logo_url"
        :settings="settings"
        :ads-by-slot="adsBySlot"
    />
</template>
