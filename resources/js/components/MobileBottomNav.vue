<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import MobileMenuSheet from '@/components/MobileMenuSheet.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

const props = defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();
const page = usePage();

const primary = computed(() => props.items.slice(0, 4));
const hasMore = computed(() => props.items.length > 4);

const menuOpen = ref(false);

const itemRefs = ref<HTMLElement[]>([]);
const moreRef = ref<HTMLElement | null>(null);

const activeIndex = computed(() => {
    const idx = primary.value.findIndex((it) => isCurrentUrl(it.href));

    return idx === -1 ? null : idx;
});

const dropletStyle = ref<{ left: string; width: string; opacity: number }>({
    left: '0px',
    width: '0px',
    opacity: 0,
});

function updateDroplet(): void {
    const idx = activeIndex.value;
    const target = idx === null ? null : itemRefs.value[idx] ?? null;
    const parent = target?.parentElement ?? moreRef.value?.parentElement ?? null;

    if (!target || !parent) {
        dropletStyle.value = { ...dropletStyle.value, opacity: 0 };

        return;
    }

    const parentRect = parent.getBoundingClientRect();
    const rect = target.getBoundingClientRect();
    dropletStyle.value = {
        left: `${rect.left - parentRect.left}px`,
        width: `${rect.width}px`,
        opacity: 1,
    };
}

watch(
    () => page.url,
    () => nextTick(updateDroplet),
);
watch(() => props.items.length, () => nextTick(updateDroplet));

onMounted(() => {
    nextTick(updateDroplet);
    window.addEventListener('resize', updateDroplet);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateDroplet);
});
</script>

<template>
    <nav
        v-if="items.length > 0"
        class="fixed inset-x-0 bottom-0 z-40 border-t border-sidebar-border/70 bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur supports-[backdrop-filter]:bg-background/85 md:hidden"
    >
        <div class="relative flex items-stretch px-1 pt-1">
            <!-- Suv tomchisi (liquid glass droplet) -->
            <span
                class="pointer-events-none absolute top-1 bottom-1 rounded-2xl border border-primary/25 bg-gradient-to-b from-primary/15 to-primary/5 shadow-[0_4px_14px_-4px_rgba(0,0,0,0.18),inset_0_1px_0_rgba(255,255,255,0.6)] dark:border-primary/40 dark:from-primary/25 dark:to-primary/10 dark:shadow-[0_4px_14px_-2px_rgba(0,0,0,0.45),inset_0_1px_0_rgba(255,255,255,0.18)]"
                :style="{
                    left: dropletStyle.left,
                    width: dropletStyle.width,
                    opacity: dropletStyle.opacity,
                    transitionProperty: 'left, width, opacity',
                    transitionDuration: '480ms',
                    transitionTimingFunction: 'cubic-bezier(0.68, -0.2, 0.27, 1.35)',
                }"
            />

            <Link
                v-for="(item, idx) in primary"
                :key="item.href.toString()"
                :ref="(el) => { if (el) itemRefs[idx] = (el as any).$el ?? el; }"
                :href="item.href"
                prefetch="hover"
                :cache-for="['30s', '1m']"
                :data-tour="String(item.href)"
                class="relative z-10 flex min-w-0 flex-1 flex-col items-center justify-center gap-1 py-2 text-[10px] font-medium leading-none transition-colors"
                :class="isCurrentUrl(item.href)
                    ? 'text-primary'
                    : 'text-muted-foreground active:text-foreground'"
            >
                <span class="relative">
                    <component :is="item.icon" class="h-5 w-5 shrink-0" :stroke-width="isCurrentUrl(item.href) ? 2.5 : 2" />
                    <span
                        v-if="item.badge !== null && item.badge !== undefined && item.badge !== '' && item.badge !== 0"
                        class="absolute -right-2 -top-1.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-orange-500 px-1 text-[9px] font-bold leading-none text-white shadow ring-2 ring-background"
                    >
                        {{ item.badge }}
                    </span>
                </span>
                <span class="w-full truncate px-1 text-center">{{ item.title }}</span>
            </Link>
            <button
                v-if="hasMore"
                ref="moreRef"
                type="button"
                class="relative z-10 flex min-w-0 flex-1 flex-col items-center justify-center gap-1 py-2 text-[10px] font-medium leading-none text-muted-foreground transition-colors active:text-foreground"
                @click="menuOpen = true"
            >
                <Menu class="h-5 w-5 shrink-0" />
                <span>Menu</span>
            </button>
        </div>
    </nav>

    <MobileMenuSheet v-model:open="menuOpen" />
</template>
