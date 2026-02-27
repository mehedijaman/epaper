export interface Category {
    id: number;
    name: string;
    position: number;
    is_active?: boolean;
}

export interface Hotspot {
    id: number;
    relation_kind: 'next' | 'previous';
    target_page_no: number | null;
    x: number;
    y: number;
    w: number;
    h: number;
    label: string | null;
    target_hotspot_id?: number | null;
    linked_hotspot_id?: number | null;
    target_hotspot?: LinkedHotspotRef | null;
    linked_hotspot?: LinkedHotspotRef | null;
}

export interface LinkedHotspotRef {
    id: number;
    page_no: number | null;
    relation_kind: 'next' | 'previous';
    target_page_no: number | null;
    x: number;
    y: number;
    w: number;
    h: number;
    label: string | null;
}

export interface Page {
    id: number;
    edition_id: number;
    page_no: number;
    category_id: number | null;
    category_name: string | null;
    width: number;
    height: number;
    image_original_path: string;
    image_large_path: string;
    image_thumb_path: string;
    image_original_url: string;
    image_large_url: string;
    image_thumb_url: string;
    hotspots: Hotspot[];
}

export interface ViewerPageListItem {
    id: number;
    edition_id: number;
    page_no: number;
    category_id: number | null;
    category_name: string | null;
    image_thumb_url: string;
    image_large_url: string;
    image_original_url: string;
    hotspots: Hotspot[];
}

export interface ViewerCategoryItem {
    id: number;
    name: string;
    position: number;
}

export interface ViewerEditionItem {
    id: number;
    name: string | null;
    edition_date: string;
}

export interface Edition {
    id: number;
    edition_date: string;
    name?: string | null;
    status: 'draft' | 'published';
    published_at: string | null;
    pages: Page[];
}

export interface Ad {
    id: number;
    ad_slot_id: number;
    slot_no: number | null;
    slot_name: string | null;
    type: 'image' | 'embed';
    image_url: string | null;
    click_url: string | null;
    embed_code: string | null;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
}

export interface AdSlot {
    id: number;
    slot_no: number;
    name: string;
}
