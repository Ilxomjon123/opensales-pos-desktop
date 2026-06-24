<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavGroup, NavItem } from '@/types';

defineProps<{
    groups: NavGroup[];
}>();

const { isCurrentUrl } = useCurrentUrl();
const { isMobile } = useSidebar();
const openTooltips = ref<Record<string, boolean>>({});

function badgeClass(item: NavItem): string {
    if (item.badgeTone === 'danger') {
        return 'bg-rose-500/15 text-rose-700 dark:text-rose-300 ring-1 ring-rose-500/30';
    }

    if (item.badgeTone === 'warning') {
        return 'bg-orange-500/15 text-orange-700 dark:text-orange-300 ring-1 ring-orange-500/30';
    }

    if (item.badgeTone === 'info') {
        return 'bg-sky-500/15 text-sky-700 dark:text-sky-300 ring-1 ring-sky-500/30';
    }

    return 'bg-primary/15 text-primary ring-1 ring-primary/25';
}

function hasBadge(item: NavItem): boolean {
    return item.badge !== null && item.badge !== undefined && item.badge !== '' && item.badge !== 0;
}

function tooltipKey(groupKey: string | number, item: NavItem): string {
    return `${String(groupKey)}::${item.title}`;
}

function openTooltip(key: string): void {
    openTooltips.value = { ...openTooltips.value, [key]: true };
}

function closeTooltip(key: string): void {
    openTooltips.value = { ...openTooltips.value, [key]: false };
}

function toggleTooltip(key: string, event: Event): void {
    event.preventDefault();
    event.stopPropagation();
    openTooltips.value = { ...openTooltips.value, [key]: ! openTooltips.value[key] };
}
</script>

<template>
    <SidebarGroup
        v-for="(group, index) in groups"
        :key="group.label ?? index"
        class="px-2 py-0"
    >
        <SidebarGroupLabel v-if="group.label">{{ group.label }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in group.items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href" prefetch="hover" :cache-for="['30s', '1m']" :data-tour="String(item.href)">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
                <template v-if="hasBadge(item)">
                    <Tooltip
                        v-if="item.badgeTooltip && ! isMobile"
                        :open="openTooltips[tooltipKey(group.label ?? index, item)] ?? false"
                    >
                        <TooltipTrigger as-child>
                            <button
                                type="button"
                                :aria-label="item.badgeTooltip"
                                class="absolute right-1 top-1/2 -translate-y-1/2 cursor-help select-none"
                                @mouseenter="openTooltip(tooltipKey(group.label ?? index, item))"
                                @mouseleave="closeTooltip(tooltipKey(group.label ?? index, item))"
                                @focus="openTooltip(tooltipKey(group.label ?? index, item))"
                                @blur="closeTooltip(tooltipKey(group.label ?? index, item))"
                                @click="toggleTooltip(tooltipKey(group.label ?? index, item), $event)"
                                @touchstart.passive="openTooltip(tooltipKey(group.label ?? index, item))"
                            >
                                <SidebarMenuBadge
                                    class="static translate-y-0 pointer-events-none"
                                    :class="badgeClass(item)"
                                >
                                    {{ item.badge }}
                                </SidebarMenuBadge>
                            </button>
                        </TooltipTrigger>
                        <TooltipContent
                            side="right"
                            :side-offset="8"
                            class="max-w-xs text-xs"
                            @pointer-down-outside="closeTooltip(tooltipKey(group.label ?? index, item))"
                        >
                            {{ item.badgeTooltip }}
                        </TooltipContent>
                    </Tooltip>
                    <SidebarMenuBadge
                        v-else
                        :class="badgeClass(item)"
                    >
                        {{ item.badge }}
                    </SidebarMenuBadge>
                </template>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
