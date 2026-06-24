<script setup lang="ts">
import { BarChart3, Box, FolderTree, PartyPopper, Rocket, ShoppingCart } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    dealerId: number;
}>();

const TOOLTIP_WIDTH = 300;
// Har diller uchun alohida kalit — yangi akkountda tur qayta ko'rinadi.
const storageKey = `dealer_welcome_tour_seen:${props.dealerId}`;

const { t } = useI18n();

type Step = {
    key: string;
    icon: typeof Rocket;
    /** data-tour selektori — bo'lmasa markazda ko'rsatiladi */
    target?: string;
};

const ALL_STEPS: Step[] = [
    { key: 'welcome', icon: PartyPopper },
    { key: 'stats', icon: BarChart3, target: '/dealer/stats' },
    { key: 'orders', icon: ShoppingCart, target: '/dealer/orders' },
    { key: 'products', icon: Box, target: '/dealer/products' },
    { key: 'categories', icon: FolderTree, target: '/dealer/categories' },
    { key: 'finish', icon: Rocket },
];

const open = ref(false);
const steps = ref<Step[]>([]);
const index = ref(0);
const rect = ref<{ top: number; left: number; width: number; height: number } | null>(null);

const current = computed(() => steps.value[index.value]);
const isLast = computed(() => index.value === steps.value.length - 1);

/** Faqat ko'rinadigan elementli (yoki markaziy) qadamlarni qoldiramiz. */
function visibleEl(selector: string): HTMLElement | null {
    const nodes = Array.from(document.querySelectorAll<HTMLElement>(`[data-tour="${selector}"]`));

    return nodes.find((el) => el.offsetParent !== null && el.getBoundingClientRect().width > 0) ?? null;
}

function buildSteps(): Step[] {
    return ALL_STEPS.filter((s) => !s.target || visibleEl(s.target));
}

function measure(): void {
    const step = current.value;

    if (!step?.target) {
        rect.value = null;

        return;
    }

    const el = visibleEl(step.target);

    if (!el) {
        rect.value = null;

        return;
    }

    const r = el.getBoundingClientRect();
    rect.value = { top: r.top, left: r.left, width: r.width, height: r.height };
}

async function goTo(i: number): Promise<void> {
    index.value = i;
    await nextTick();
    const step = current.value;

    if (step?.target) {
        visibleEl(step.target)?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        // scroll tugashini kutib qayta o'lchaymiz
        setTimeout(measure, 220);
    }

    measure();
}

const PAD = 6;

const highlightStyle = computed(() => {
    const r = rect.value;

    if (!r) {
        return null;
    }

    return {
        top: `${r.top - PAD}px`,
        left: `${r.left - PAD}px`,
        width: `${r.width + PAD * 2}px`,
        height: `${r.height + PAD * 2}px`,
    };
});

const tooltipStyle = computed(() => {
    const r = rect.value;

    if (!r) {
        return null;
    }

    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const clampLeft = (l: number) => Math.max(12, Math.min(l, vw - TOOLTIP_WIDTH - 12));

    // Pastki nav (mobil) — tepada ko'rsatamiz
    if (r.top > vh * 0.55) {
        return {
            left: `${clampLeft(r.left + r.width / 2 - TOOLTIP_WIDTH / 2)}px`,
            bottom: `${vh - r.top + 12}px`,
        };
    }

    // Chap sidebar (desktop) — o'ngda ko'rsatamiz
    if (r.left < vw * 0.4 && vw >= 768) {
        return {
            left: `${Math.min(r.left + r.width + 12, vw - TOOLTIP_WIDTH - 12)}px`,
            top: `${Math.max(12, Math.min(r.top, vh - 240))}px`,
        };
    }

    // Aks holda — pastda
    return {
        left: `${clampLeft(r.left + r.width / 2 - TOOLTIP_WIDTH / 2)}px`,
        top: `${r.top + r.height + 12}px`,
    };
});

// SSR: Teleport mount'gacha o'chiq (hydration mismatch'ni oldini olish).
const isMounted = ref(false);

function onViewportChange(): void {
    measure();
}

onMounted(() => {
    isMounted.value = true;

    let seen = false;

    try {
        seen = localStorage.getItem(storageKey) !== null;
    } catch {
        seen = false;
    }

    if (seen) {
        return;
    }

    // Nav render bo'lishini kutamiz
    setTimeout(() => {
        steps.value = buildSteps();

        if (steps.value.length === 0) {
            return;
        }

        open.value = true;
        goTo(0);
        window.addEventListener('resize', onViewportChange);
        window.addEventListener('scroll', onViewportChange, true);
    }, 400);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onViewportChange);
    window.removeEventListener('scroll', onViewportChange, true);
});

function finish(): void {
    try {
        localStorage.setItem(storageKey, '1');
    } catch {
        // localStorage yo'q bo'lsa — e'tiborsiz
    }

    open.value = false;
    window.removeEventListener('resize', onViewportChange);
    window.removeEventListener('scroll', onViewportChange, true);
}

function next(): void {
    if (isLast.value) {
        finish();

        return;
    }

    goTo(index.value + 1);
}

function prev(): void {
    if (index.value > 0) {
        goTo(index.value - 1);
    }
}
</script>

<template>
    <Teleport to="body" :disabled="!isMounted">
        <div v-if="open" class="fixed inset-0 z-[60]">
            <!-- Bosishlarni ushlovchi qatlam: markaziy qadamda to'liq qora,
                 anchor qadamda shaffof (spotlight box-shadow dim qiladi) -->
            <div class="absolute inset-0" :class="rect ? '' : 'bg-black/60'" />

            <!-- Spotlight ring (anchor qadam) -->
            <div
                v-if="highlightStyle"
                class="pointer-events-none absolute rounded-xl ring-2 ring-primary transition-all duration-300"
                :style="{
                    ...highlightStyle,
                    boxShadow: '0 0 0 9999px rgba(0,0,0,0.6)',
                }"
            />

            <!-- Tooltip kartasi -->
            <div
                class="absolute z-[61] w-[300px] max-w-[calc(100vw-24px)] rounded-xl border border-border bg-popover p-4 text-popover-foreground shadow-2xl"
                :style="tooltipStyle ?? { top: '50%', left: '50%', transform: 'translate(-50%, -50%)' }"
            >
                <div class="flex items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <component :is="current?.icon" class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-sm font-bold tracking-tight">{{ t(`welcomeTour.${current?.key}.title`) }}</h3>
                        <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                            {{ t(`welcomeTour.${current?.key}.body`) }}
                        </p>
                    </div>
                </div>

                <!-- Dots -->
                <div class="mt-4 flex items-center justify-center gap-1.5">
                    <span
                        v-for="(s, i) in steps"
                        :key="s.key"
                        class="h-1.5 rounded-full transition-all"
                        :class="i === index ? 'w-5 bg-primary' : 'w-1.5 bg-muted'"
                    />
                </div>

                <div class="mt-4 space-y-2">
                    <div class="flex items-center gap-2">
                        <Button v-if="index > 0" variant="outline" size="sm" class="flex-1" @click="prev">
                            {{ t('welcomeTour.back') }}
                        </Button>
                        <Button size="sm" class="flex-1" @click="next">
                            {{ isLast ? t('welcomeTour.start') : t('welcomeTour.next') }}
                        </Button>
                    </div>
                    <Button variant="ghost" size="sm" class="w-full text-muted-foreground" @click="finish">
                        {{ t('welcomeTour.skip') }}
                    </Button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
