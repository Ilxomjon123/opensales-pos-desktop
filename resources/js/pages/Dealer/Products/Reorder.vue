<script setup lang="ts">
import { Head, InfiniteScroll, router } from '@inertiajs/vue3';
import { ArrowLeft, Image as ImageIcon, LayoutGrid, Loader2, Package, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import draggable from 'vuedraggable';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useUnitLabel } from '@/composables/useUnitLabel';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Paginated, Product } from '@/types';

const props = defineProps<{
    products: Paginated<Product>;
    categories: { id: number; name: string }[];
    filters: { search?: string; category_id?: number | string };
}>();

const { t } = useI18n();
const unitLabel = useUnitLabel();

type ViewMode = 'gallery' | 'detailed';

const VIEW_KEY = 'products_reorder_view';

const viewMode = ref<ViewMode>(
    (typeof window !== 'undefined' && (localStorage.getItem(VIEW_KEY) as ViewMode)) || 'gallery',
);

function setView(m: ViewMode) {
    viewMode.value = m;

    try {
 localStorage.setItem(VIEW_KEY, m); 
} catch { /* */ }
}

const search = ref<string>((props.filters.search as string) ?? '');
const categoryId = ref<string>(
    props.filters.category_id != null ? String(props.filters.category_id) : 'all',
);

// Lokal items massivi — drag list ustida ishlaydi.
// InfiniteScroll props.products.data ga yangi sahifa qo'shganda, watcher
// orqali yangi mahsulotlarni lokal ro'yxat oxiriga qo'shamiz (foydalanuvchi
// qilgan reordering buzilmaydi).
const items = ref<Product[]>([...props.products.data]);

watch(
    () => props.products.data,
    (next) => {
        const seen = new Set(items.value.map((p) => p.id));
        const additions = next.filter((p) => !seen.has(p.id));

        if (additions.length > 0) {
            items.value = [...items.value, ...additions];
        }
    },
    { deep: false },
);

// Boshlang'ich tartibni eslab qolamiz — o'zgarish bormi yo'qmi shu orqali aniqlanadi.
const initialOrder = ref<number[]>(props.products.data.map((p) => p.id));

const dirty = computed(() => {
    if (items.value.length !== initialOrder.value.length) {
        return true;
    }

    for (let i = 0; i < items.value.length; i++) {
        if (items.value[i].id !== initialOrder.value[i]) {
            return true;
        }
    }

    return false;
});

// Filter o'zgarganda Inertia get bilan qayta yuklaymiz (server-side filter).
let searchTimer: ReturnType<typeof setTimeout> | null = null;

function buildQuery() {
    return {
        search: search.value.trim() || undefined,
        category_id: categoryId.value === 'all' ? undefined : categoryId.value,
    };
}

function reloadPage() {
    if (dirty.value) {
        const ok = confirm(t('pageDealer.products.reorder.confirmFilterChange'));

        if (!ok) {
            return;
        }
    }

    router.get('/dealer/products/reorder', buildQuery(), {
        preserveState: false,
        preserveScroll: false,
    });
}

watch(search, () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(reloadPage, 350);
});

function selectCategory(id: number | null) {
    const value = id === null ? 'all' : String(id);

    if (categoryId.value === value) {
        return;
    }

    categoryId.value = value;
    reloadPage();
}

const saving = ref(false);

async function save() {
    if (!dirty.value || saving.value) {
        return;
    }

    saving.value = true;

    try {
        const res = await fetch('/dealer/products/reorder', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ order: items.value.map((p) => p.id) }),
        });

        if (!res.ok) {
            throw new Error(String(res.status));
        }

        initialOrder.value = items.value.map((p) => p.id);
        router.visit('/dealer/products', {
            preserveState: false,
        });
    } catch {
        alert(t('pageDealer.products.reorder.saveError'));
    } finally {
        saving.value = false;
    }
}

function reset() {
    if (!dirty.value) {
        router.visit('/dealer/products');

        return;
    }

    const ok = confirm(t('pageDealer.products.reorder.confirmDiscard'));

    if (!ok) {
        return;
    }

    router.visit('/dealer/products');
}

function firstImageOf(p: Product): string | null {
    if (p.images && p.images.length > 0) {
        return p.images[0].url;
    }

    return p.image_url ?? null;
}

function imageCountOf(p: Product): number {
    if (p.images && p.images.length > 0) {
        return p.images.length;
    }

    return p.image_url ? 1 : 0;
}

function formatMoney(n: number): string {
    return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.products.reorder.headTitle')" />

    <div class="flex min-h-[calc(100vh-4rem)] flex-col">
        <!-- Sticky header -->
        <div class="sticky top-0 z-20 -mx-3 border-b bg-background/90 px-3 pb-2 pt-3 backdrop-blur-md sm:-mx-4 sm:px-4 sm:pt-4">
            <div class="mb-2 flex items-center gap-2">
                <Button variant="ghost" size="icon" class="h-9 w-9 shrink-0" @click="reset">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-base font-bold sm:text-lg">{{ t('pageDealer.products.reorder.title') }}</h1>
                    <p class="truncate text-[11px] text-muted-foreground sm:text-xs">
                        {{ t('pageDealer.products.reorder.subtitle') }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" :placeholder="t('pageDealer.products.reorder.searchPlaceholder')" class="pl-10" />
                </div>
                <div class="flex shrink-0 items-center rounded-xl border bg-background p-0.5">
                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-lg transition-colors"
                        :class="viewMode === 'gallery' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                        :title="t('pageDealer.products.reorder.viewGallery')"
                        @click="setView('gallery')"
                    >
                        <ImageIcon class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-lg transition-colors"
                        :class="viewMode === 'detailed' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                        :title="t('pageDealer.products.reorder.viewCard')"
                        @click="setView('detailed')"
                    >
                        <LayoutGrid class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Kategoriya chip bar -->
            <div v-if="categories.length > 0" class="-mx-3 mt-2.5 overflow-x-auto px-3 pb-2 sm:-mx-4 sm:px-4">
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="categoryId === 'all'
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border bg-background text-foreground/80 hover:bg-muted'"
                        @click="selectCategory(null)"
                    >
                        {{ t('pageDealer.products.reorder.categoryAll') }}
                    </button>
                    <button
                        v-for="c in categories"
                        :key="c.id"
                        type="button"
                        class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="categoryId === String(c.id)
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border bg-background text-foreground/80 hover:bg-muted'"
                        @click="selectCategory(c.id)"
                    >
                        {{ c.name }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Mahsulotlar grid (drag enabled) — pb mobile nav + save bar uchun joy qoldiradi -->
        <div class="flex-1 pb-32 pt-3 sm:pb-24 sm:pt-4">
            <InfiniteScroll data="products">
                <draggable
                    v-if="items.length > 0"
                    v-model="items"
                    item-key="id"
                    tag="div"
                    :animation="180"
                    :delay="180"
                    :delay-on-touch-only="true"
                    :touch-start-threshold="5"
                    ghost-class="opacity-40"
                    :class="viewMode === 'gallery'
                        ? 'grid grid-cols-3 gap-1 sm:grid-cols-4 sm:gap-1.5 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-8'
                        : 'grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6'"
                >
                    <template #item="{ element: p, index: idx }">
                        <div
                            :class="viewMode === 'gallery'
                                ? 'group relative cursor-grab overflow-hidden rounded-md bg-card select-none active:cursor-grabbing'
                                : 'group relative cursor-grab overflow-hidden rounded-xl border bg-card select-none active:cursor-grabbing'"
                        >
                            <div class="relative aspect-square overflow-hidden bg-muted">
                                <img
                                    v-if="firstImageOf(p)"
                                    :src="firstImageOf(p)!"
                                    :alt="p.name"
                                    loading="lazy"
                                    decoding="async"
                                    draggable="false"
                                    class="pointer-events-none h-full w-full object-cover"
                                />
                                <div v-else class="flex h-full w-full items-center justify-center text-muted-foreground/30">
                                    <Package class="h-8 w-8" />
                                </div>
                                <!-- Tartib raqami -->
                                <div class="pointer-events-none absolute left-1 top-1 rounded bg-black/55 px-1.5 py-0.5 text-[10px] font-mono font-medium text-white shadow">
                                    #{{ idx + 1 }}
                                </div>
                                <div v-if="imageCountOf(p) > 1" class="pointer-events-none absolute right-1 top-1 rounded bg-black/50 px-1.5 py-0.5 text-[10px] text-white">
                                    +{{ imageCountOf(p) - 1 }}
                                </div>
                                <div
                                    v-if="(p.discount_percent ?? 0) > 0"
                                    class="pointer-events-none absolute bottom-1 left-1 rounded-md bg-rose-500 px-1.5 py-0.5 text-[10px] font-bold text-white shadow"
                                >
                                    −{{ p.discount_percent }}%
                                </div>
                                <div v-if="!p.is_active" class="pointer-events-none absolute inset-0 flex items-center justify-center bg-background/65">
                                    <span class="rounded-full bg-destructive px-2 py-0.5 text-[10px] font-medium text-destructive-foreground">{{ t('pageDealer.products.reorder.inactiveBadge') }}</span>
                                </div>
                            </div>
                            <div v-if="viewMode === 'detailed'" class="p-2">
                                <p class="text-sm font-bold leading-tight line-clamp-2">{{ p.name }}</p>
                                <p class="mt-1 truncate text-sm font-bold" :class="p.original_price ? 'text-rose-600 dark:text-rose-400' : ''">
                                    {{ formatMoney(p.has_types ? (p.starting_price ?? p.price) : p.price) }}
                                    <span class="text-[10px] font-normal text-muted-foreground">/ {{ unitLabel(p.unit) }}</span>
                                </p>
                                <p v-if="p.original_price" class="truncate text-[10px] text-muted-foreground line-through">
                                    {{ formatMoney(p.original_price) }}
                                </p>
                                <p v-if="p.pack_size > 1" class="truncate text-[10px] text-muted-foreground">
                                    {{ t('pageDealer.products.reorder.packLabel') }} {{ formatMoney(p.pack_price) }}
                                </p>
                            </div>
                        </div>
                    </template>
                </draggable>

                <!-- Empty -->
                <div v-else class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                        <Package class="h-7 w-7" />
                    </div>
                    <p class="mt-4 text-base font-semibold">{{ t('pageDealer.products.reorder.emptyTitle') }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ t('pageDealer.products.reorder.emptyHint') }}</p>
                </div>
            </InfiniteScroll>
        </div>

        <!-- Sticky save bar — mobile bottom nav (z-40) ustida -->
        <div class="fixed inset-x-0 bottom-0 z-50 border-t bg-background/95 px-3 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-4px_12px_rgba(0,0,0,0.08)] backdrop-blur-md sm:px-4">
            <div class="mx-auto flex w-full max-w-7xl items-center justify-between gap-3">
                <div class="min-w-0 text-xs text-muted-foreground">
                    <span v-if="dirty" class="font-medium text-amber-600 dark:text-amber-400">{{ t('pageDealer.products.reorder.dirtyStatus') }}</span>
                    <span v-else>{{ t('pageDealer.products.reorder.idleStatus') }}</span>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <Button variant="outline" size="sm" @click="reset">{{ t('pageDealer.products.reorder.cancel') }}</Button>
                    <Button size="sm" :disabled="!dirty || saving" @click="save">
                        <Loader2 v-if="saving" class="mr-1.5 h-4 w-4 animate-spin" />
                        {{ t('pageDealer.products.reorder.save') }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
