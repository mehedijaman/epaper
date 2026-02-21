import {
    BookOpen,
    LayoutGrid,
    Link2,
    Newspaper,
    Settings,
    Tag,
    Upload,
} from 'lucide-vue-next';
import type { NavItem, NavSection } from '@/types';

export type EpAdminAbilityKey =
    | 'categories_manage'
    | 'ads_manage'
    | 'settings_manage'
    | 'editions_manage';

export type EpAdminMenuItem = NavItem & {
    requiredAbility?: EpAdminAbilityKey;
};

export type EpAdminMenuSection = {
    label: string;
    items: EpAdminMenuItem[];
};

export const EPADMIN_MENU_SECTIONS: EpAdminMenuSection[] = [
    {
        label: 'General',
        items: [
            {
                title: 'Dashboard',
                href: '/admin',
                icon: LayoutGrid,
            },
        ],
    },
    {
        label: 'Editions',
        items: [
            {
                title: 'Manage Pages',
                href: '/admin/editions/manage',
                icon: Upload,
                requiredAbility: 'editions_manage',
            },
            {
                title: 'Publish',
                href: '/admin/editions/publish',
                icon: Newspaper,
                requiredAbility: 'editions_manage',
            },
            {
                title: 'Mapping',
                href: '/admin/hotspots',
                icon: Link2,
                requiredAbility: 'editions_manage',
            },
        ],
    },
    {
        label: 'Administration',
        items: [
            {
                title: 'Categories',
                href: '/admin/categories',
                icon: Tag,
                activeStartsWith: ['/admin/categories'],
                requiredAbility: 'categories_manage',
            },
            {
                title: 'Ads',
                href: '/admin/ads',
                icon: BookOpen,
                activeStartsWith: ['/admin/ads'],
                requiredAbility: 'ads_manage',
            },
            {
                title: 'Settings',
                href: '/admin/settings',
                icon: Settings,
                activeStartsWith: ['/admin/settings'],
                requiredAbility: 'settings_manage',
            },
        ],
    },
];

export function buildEpAdminMenuSections(
    abilities: Record<EpAdminAbilityKey, boolean>,
): NavSection[] {
    return EPADMIN_MENU_SECTIONS
        .map((section): NavSection => ({
            label: section.label,
            items: section.items
                .filter((item) => {
                    return item.requiredAbility === undefined || abilities[item.requiredAbility];
                })
                .map(
                    (item): NavItem => ({
                        title: item.title,
                        href: item.href,
                        icon: item.icon,
                        isActive: item.isActive,
                        activeHrefs: item.activeHrefs,
                        activeStartsWith: item.activeStartsWith,
                    }),
                ),
        }))
        .filter((section) => section.items.length > 0);
}
