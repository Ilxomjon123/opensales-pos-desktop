<script setup lang="ts">
import { Head, InfiniteScroll, router, usePage } from '@inertiajs/vue3';
import {
    ArrowUpDown,
    Box,
    DollarSign,
    History,
    Layers,
    LayoutGrid,
    List,
    MoreVertical,
    Package,
    PackagePlus,
    Pencil,
    Plus,
    Search,
    Sparkles,
    Table2,
    Trash2,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import StockBadge from '@/components/dealer/products/StockBadge.vue';
import StockInBulkModal from '@/components/dealer/products/StockInBulkModal.vue';
import StockInModal from '@/components/dealer/products/StockInModal.vue';
import ImageLightbox from '@/components/ImageLightbox.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { confirm } from '@/composables/useConfirm';
import { useCurrency } from '@/composables/useCurrency';
import { useUnitLabel } from '@/composables/useUnitLabel';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Product, Paginated, Filters } from '@/types';

const props = defineProps<{
    products: Paginated<Product>;
    suppliers: { id: number; name: string }[];
    categories: { id: number; name: string }[];
    typeCounts?: { all: number; with: number; without: number };
    stockValue?: number;
    filters: Filters;
}>();

const { t } = useI18n();
const { formatWithSymbol } = useCurrency();
const unitLabel = useUnitLabel();

const page = usePage();
const role = computed(() => page.props.auth?.role ?? '');
const canEdit = computed(() => role.value === 'dealer');
const canCreate = computed(() => ['dealer', 'warehouse'].includes(role.value));
const canStockIn = computed(() => ['dealer', 'warehouse'].includes(role.value));
const canToggleActive = computed(() =>
    ['dealer', 'warehouse'].includes(role.value),
);
const canReorder = computed(() => ['dealer', 'warehouse'].includes(role.value));

const imageErrors = ref<Set<number>>(new Set());
const hasImage = (p: Product) => !!p.image_url && !imageErrors.value.has(p.id);
const onImageError = (id: number) => {
    imageErrors.value.add(id);
    imageErrors.value = new Set(imageErrors.value);
};

const search = ref((props.filters.search as string) ?? '');
const sort = ref<string>((props.filters.sort as string) ?? 'low_stock');
const categoryId = ref<string>(
    props.filters.category_id != null
        ? String(props.filters.category_id)
        : 'all',
);
const hasTypes = ref<string>((props.filters.has_types as string) ?? 'all');

const SORT_OPTIONS = computed<{ value: string; label: string }[]>(() => [
    { value: 'low_stock', label: t('pageDealer.products.index.sortLowStock') },
    { value: 'newest', label: t('pageDealer.products.index.sortNewest') },
    { value: 'oldest', label: t('pageDealer.products.index.sortOldest') },
    { value: 'name_asc', label: t('pageDealer.products.index.sortNameAsc') },
    { value: 'name_desc', label: t('pageDealer.products.index.sortNameDesc') },
    { value: 'price_asc', label: t('pageDealer.products.index.sortPriceAsc') },
    {
        value: 'price_desc',
        label: t('pageDealer.products.index.sortPriceDesc'),
    },
    { value: 'stock_asc', label: t('pageDealer.products.index.sortStockAsc') },
    {
        value: 'stock_desc',
        label: t('pageDealer.products.index.sortStockDesc'),
    },
]);

function buildQuery() {
    return {
        search: search.value || undefined,
        sort: sort.value === 'low_stock' ? undefined : sort.value,
        category_id: categoryId.value === 'all' ? undefined : categoryId.value,
        has_types: hasTypes.value === 'all' ? undefined : hasTypes.value,
    };
}

function applySort(value: string) {
    sort.value = value;
    router.get('/dealer/products', buildQuery(), {
        preserveState: true,
        preserveScroll: true,
    });
}

function applyCategory(value: string) {
    categoryId.value = value;
    router.get('/dealer/products', buildQuery(), {
        preserveState: true,
        preserveScroll: true,
    });
}

function applyHasTypes(value: string) {
    hasTypes.value = value;
    router.get('/dealer/products', buildQuery(), {
        preserveState: true,
        preserveScroll: true,
    });
}
type ViewMode = 'grid' | 'list' | 'table';
const viewMode = ref<ViewMode>('grid');

function setView(mode: ViewMode) {
    viewMode.value = mode;
    localStorage.setItem('products_view', mode);
}

// Mobile da table mode mavjud emas, list ga fallback
const isMobile = ref(
    typeof window !== 'undefined' ? window.innerWidth < 640 : false,
);

function handleResize() {
    isMobile.value = window.innerWidth < 640;
}

onMounted(() => {
    const saved = localStorage.getItem('products_view') as ViewMode | null;

    if (saved) {
        viewMode.value = saved;
    }

    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
});

const effectiveViewMode = computed(() => {
    if (viewMode.value === 'table' && isMobile.value) {
        return 'list';
    }

    return viewMode.value;
});

function applySearch() {
    router.get('/dealer/products', buildQuery(), {
        preserveState: true,
        preserveScroll: true,
    });
}

async function toggleActive(p: Product) {
    const previous = p.is_active;
    p.is_active = !previous;

    try {
        const res = await fetch(`/dealer/products/${p.id}/toggle`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement | null
                    )?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!res.ok) {
            throw new Error(String(res.status));
        }
    } catch {
        p.is_active = previous;
    }
}

async function deleteProduct(p: Product) {
    const ok = await confirm({
        title: t('pageDealer.products.index.deleteTitle'),
        description: t('pageDealer.products.index.deleteDescription', {
            name: p.name,
        }),
        confirmText: t('pageDealer.products.index.delete'),
        variant: 'destructive',
    });

    if (!ok) {
        return;
    }

    router.delete(`/dealer/products/${p.id}`);
}

function formatMoney(amount: number): string {
    return String(Math.round(amount)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

// Stock-in modals
const stockInOpen = ref(false);
const stockInProduct = ref<Product | null>(null);
const stockInBulkOpen = ref(false);

function openStockIn(p: Product) {
    stockInProduct.value = p;
    stockInOpen.value = true;
}

function onStockInSuccess() {
    router.reload({ only: ['products'] });
}

// Lightbox
const lightboxImages = ref<string[]>([]);
const lightboxOpen = ref(false);
const lightboxIdx = ref(0);

function openZoom(p: Product, idx: number = 0) {
    const imgs = (p.images ?? []).map((i) => i.url);

    if (imgs.length === 0) {
        return;
    }

    lightboxImages.value = imgs;
    lightboxIdx.value = idx;
    lightboxOpen.value = true;
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.products.index.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <!-- Header -->
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">
                    {{ t('pageDealer.products.index.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        t('pageDealer.products.index.totalCount', {
                            n: products.meta.total,
                        })
                    }}
                </p>
                <p
                    v-if="stockValue != null"
                    class="mt-0.5 flex items-center gap-1.5 text-sm font-medium text-foreground"
                >
                    <Box class="h-4 w-4 text-muted-foreground" />
                    {{
                        t('pageDealer.products.index.stockValue', {
                            value: formatWithSymbol(stockValue),
                        })
                    }}
                </p>
            </div>
            <div
                v-if="canEdit || canStockIn"
                class="flex shrink-0 items-center gap-2"
            >
                <!-- Desktop: secondary actions inline -->
                <div class="hidden items-center gap-2 lg:flex">
                    <Button
                        v-if="canStockIn"
                        variant="outline"
                        size="sm"
                        @click="router.get('/dealer/stock-transactions')"
                    >
                        <History class="mr-2 h-4 w-4" />
                        {{ t('pageDealer.products.index.stockHistory') }}
                    </Button>
                    <Button
                        v-if="canStockIn"
                        variant="outline"
                        size="sm"
                        @click="stockInBulkOpen = true"
                    >
                        <PackagePlus class="mr-2 h-4 w-4" />
                        {{ t('pageDealer.products.index.bulkStock') }}
                    </Button>
                    <Button
                        v-if="canEdit"
                        variant="outline"
                        size="sm"
                        @click="router.get('/dealer/products/bulk')"
                    >
                        <DollarSign class="mr-2 h-4 w-4" />
                        {{ t('pageDealer.products.index.bulkPrice') }}
                    </Button>
                    <Button
                        v-if="canReorder"
                        variant="outline"
                        size="sm"
                        @click="router.get('/dealer/products/reorder')"
                    >
                        <ArrowUpDown class="mr-2 h-4 w-4" />
                        {{ t('pageDealer.products.index.reorder') }}
                    </Button>
                </div>

                <!-- Primary action: always visible -->
                <Button
                    v-if="canCreate"
                    size="sm"
                    @click="router.get('/dealer/products/create')"
                >
                    <Plus class="h-4 w-4 sm:mr-2" />
                    <span class="hidden sm:inline">{{
                        t('pageDealer.products.index.newProduct')
                    }}</span>
                </Button>

                <!-- Mobile/Tablet: secondary actions in dropdown -->
                <DropdownMenu v-if="canEdit || canStockIn">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="icon"
                            class="lg:hidden"
                            :aria-label="
                                t('pageDealer.products.index.moreActions')
                            "
                        >
                            <MoreVertical class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-52">
                        <DropdownMenuItem
                            v-if="canStockIn"
                            @select="router.get('/dealer/stock-transactions')"
                        >
                            <History class="mr-2 h-4 w-4" />
                            {{ t('pageDealer.products.index.stockHistory') }}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="canStockIn"
                            @select="stockInBulkOpen = true"
                        >
                            <PackagePlus class="mr-2 h-4 w-4" />
                            {{ t('pageDealer.products.index.bulkStock') }}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="canEdit"
                            @select="router.get('/dealer/products/bulk')"
                        >
                            <DollarSign class="mr-2 h-4 w-4" />
                            {{ t('pageDealer.products.index.bulkPrice') }}
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="canReorder"
                            @select="router.get('/dealer/products/reorder')"
                        >
                            <ArrowUpDown class="mr-2 h-4 w-4" />
                            {{ t('pageDealer.products.index.reorder') }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- Filters: search + selects + view toggle -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
            <div class="relative w-full sm:flex-1">
                <Search
                    class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    :placeholder="
                        t('pageDealer.products.index.searchPlaceholder')
                    "
                    class="pl-10"
                    @keyup.enter="applySearch"
                />
            </div>
            <div class="flex items-center gap-2">
                <Select
                    :model-value="categoryId"
                    @update:model-value="(v) => applyCategory(String(v))"
                >
                    <SelectTrigger
                        class="min-w-0 flex-1 sm:w-44 sm:flex-initial"
                    >
                        <SelectValue
                            :placeholder="
                                t(
                                    'pageDealer.products.index.categoryPlaceholder',
                                )
                            "
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{{
                            t('pageDealer.products.index.allCategories')
                        }}</SelectItem>
                        <SelectItem
                            v-for="c in categories"
                            :key="c.id"
                            :value="String(c.id)"
                        >
                            {{ c.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select
                    :model-value="sort"
                    @update:model-value="(v) => applySort(String(v))"
                >
                    <SelectTrigger
                        class="min-w-0 flex-1 sm:w-44 sm:flex-initial"
                    >
                        <SelectValue
                            :placeholder="
                                t('pageDealer.products.index.sortPlaceholder')
                            "
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="o in SORT_OPTIONS"
                            :key="o.value"
                            :value="o.value"
                        >
                            {{ o.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <div class="flex shrink-0 rounded-lg border p-0.5">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8"
                        :class="effectiveViewMode === 'grid' ? 'bg-muted' : ''"
                        :title="t('pageDealer.products.index.viewGrid')"
                        @click="setView('grid')"
                    >
                        <LayoutGrid class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8"
                        :class="effectiveViewMode === 'list' ? 'bg-muted' : ''"
                        :title="t('pageDealer.products.index.viewList')"
                        @click="setView('list')"
                    >
                        <List class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="hidden h-8 w-8 sm:flex"
                        :class="effectiveViewMode === 'table' ? 'bg-muted' : ''"
                        :title="t('pageDealer.products.index.viewTable')"
                        @click="setView('table')"
                    >
                        <Table2 class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- Assortment toggle -->
        <div class="flex w-full rounded-lg border p-0.5">
            <Button
                variant="ghost"
                size="sm"
                class="h-8 flex-1 gap-1.5 text-xs"
                :class="hasTypes === 'all' ? 'bg-muted' : ''"
                @click="applyHasTypes('all')"
            >
                <Sparkles class="h-3.5 w-3.5" />
                {{ t('pageDealer.products.index.all') }}
            </Button>
            <Button
                variant="ghost"
                size="sm"
                class="h-8 flex-1 gap-1.5 text-xs"
                :class="hasTypes === 'with' ? 'bg-muted' : ''"
                @click="applyHasTypes('with')"
            >
                <Layers class="h-3.5 w-3.5" />
                {{ t('pageDealer.products.index.withTypes') }}
            </Button>
            <Button
                variant="ghost"
                size="sm"
                class="h-8 flex-1 gap-1.5 text-xs"
                :class="hasTypes === 'without' ? 'bg-muted' : ''"
                @click="applyHasTypes('without')"
            >
                <Package class="h-3.5 w-3.5" />
                {{ t('pageDealer.products.index.withoutTypes') }}
            </Button>
        </div>

        <InfiniteScroll data="products">
            <!-- GRID VIEW -->
            <div
                v-if="products.data.length > 0 && effectiveViewMode === 'grid'"
                class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6"
            >
                <div
                    v-for="(p, i) in products.data"
                    :key="p.id"
                    class="group overflow-hidden rounded-xl border bg-card transition-shadow hover:shadow-md"
                    :class="[
                        { 'opacity-60': !p.is_active },
                        canStockIn ? 'cursor-pointer active:scale-[0.98]' : '',
                    ]"
                    @click="canStockIn ? openStockIn(p) : null"
                >
                    <div
                        class="relative aspect-square overflow-hidden bg-muted"
                    >
                        <img
                            v-if="hasImage(p)"
                            :src="p.image_url!"
                            :alt="p.name"
                            class="h-full w-full cursor-zoom-in object-cover transition-transform group-hover:scale-105"
                            @click.stop="openZoom(p)"
                            @error="onImageError(p.id)"
                        />
                        <div
                            v-else
                            class="flex h-full w-full items-center justify-center"
                        >
                            <Package
                                class="h-10 w-10 text-muted-foreground/30"
                            />
                        </div>
                        <Badge
                            variant="secondary"
                            class="pointer-events-none absolute top-1.5 left-1.5 bg-background/85 px-1.5 py-0 font-mono text-[10px] backdrop-blur"
                        >
                            #{{ i + 1 }}
                        </Badge>
                        <Badge
                            v-if="!p.is_active"
                            variant="destructive"
                            class="absolute top-7 left-1.5 px-1.5 py-0 text-[10px]"
                        >
                            {{ t('pageDealer.products.index.inactive') }}
                        </Badge>
                        <div
                            v-if="canToggleActive"
                            class="absolute top-1.5 right-1.5 z-10"
                            @click.stop
                        >
                            <Switch
                                :model-value="p.is_active"
                                :aria-label="
                                    p.is_active
                                        ? t(
                                              'pageDealer.products.index.deactivate',
                                          )
                                        : t(
                                              'pageDealer.products.index.activate',
                                          )
                                "
                                class="origin-top-right scale-75 bg-background/80 backdrop-blur"
                                @update:model-value="toggleActive(p)"
                            />
                        </div>
                        <Badge
                            v-if="p.images && p.images.length > 1"
                            variant="secondary"
                            :class="
                                canToggleActive
                                    ? 'pointer-events-none absolute top-1.5 right-10 px-1.5 py-0 text-[10px]'
                                    : 'pointer-events-none absolute top-1.5 right-1.5 px-1.5 py-0 text-[10px]'
                            "
                        >
                            +{{ p.images.length - 1 }}
                        </Badge>
                        <div
                            v-if="canEdit || canStockIn"
                            class="pointer-events-none absolute inset-x-0 bottom-0 flex items-center justify-center gap-1.5 bg-gradient-to-t from-black/60 to-transparent pt-5 pb-2"
                        >
                            <Button
                                v-if="canStockIn"
                                size="icon"
                                variant="secondary"
                                class="pointer-events-auto h-7 w-7"
                                :title="t('pageDealer.products.index.stockIn')"
                                @click.stop="openStockIn(p)"
                            >
                                <PackagePlus class="h-3.5 w-3.5" />
                            </Button>
                            <Button
                                v-if="canEdit"
                                size="icon"
                                variant="secondary"
                                class="pointer-events-auto h-7 w-7"
                                :title="t('pageDealer.products.index.edit')"
                                @click.stop="
                                    router.get(`/dealer/products/${p.id}/edit`)
                                "
                            >
                                <Pencil class="h-3.5 w-3.5" />
                            </Button>
                            <Button
                                v-if="canEdit"
                                size="icon"
                                variant="destructive"
                                class="pointer-events-auto h-7 w-7"
                                :title="t('pageDealer.products.index.delete')"
                                @click.stop="deleteProduct(p)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5 p-2 sm:p-2.5">
                        <h3
                            class="line-clamp-1 text-[13px] leading-tight font-semibold sm:text-sm"
                        >
                            {{ p.name }}
                        </h3>
                        <p
                            v-if="p.category"
                            class="truncate text-[10px] text-muted-foreground sm:text-[11px]"
                        >
                            {{ p.category.name }}
                        </p>
                        <div class="flex items-baseline justify-between gap-1">
                            <p
                                class="text-sm leading-none font-bold sm:text-base"
                            >
                                <span
                                    v-if="p.has_types"
                                    class="text-[10px] font-normal text-muted-foreground"
                                    >{{ t('pageDealer.products.index.from') }}
                                </span>
                                {{
                                    formatMoney(
                                        p.has_types
                                            ? (p.starting_price ?? p.price)
                                            : p.price,
                                    )
                                }}
                                <span
                                    class="text-[10px] font-normal text-muted-foreground"
                                    >/ {{ unitLabel(p.unit) }}</span
                                >
                            </p>
                            <Badge
                                v-if="p.has_types"
                                variant="secondary"
                                class="shrink-0 px-1.5 py-0 text-[10px]"
                            >
                                🎨 {{ p.types_count ?? 0 }}
                            </Badge>
                        </div>
                        <StockBadge
                            :stock="
                                p.has_types ? (p.total_stock ?? 0) : p.stock
                            "
                            :min-stock="p.min_stock"
                            :unit="p.unit"
                            :pack-size="p.pack_size"
                            :stock-packs="p.stock_packs"
                            show-packs
                        />
                    </div>
                </div>
            </div>

            <!-- LIST VIEW (Mobile-friendly) -->
            <div
                v-if="products.data.length > 0 && effectiveViewMode === 'list'"
                class="divide-y rounded-xl border"
            >
                <div
                    v-for="(p, i) in products.data"
                    :key="p.id"
                    class="flex items-center gap-3 p-3"
                    :class="{ 'opacity-60': !p.is_active }"
                >
                    <!-- Tartib raqam -->
                    <div
                        class="w-6 flex-shrink-0 text-center font-mono text-xs text-muted-foreground tabular-nums"
                    >
                        {{ i + 1 }}
                    </div>
                    <!-- Image -->
                    <div
                        class="relative h-16 w-16 flex-shrink-0 overflow-hidden rounded-lg bg-muted"
                    >
                        <img
                            v-if="hasImage(p)"
                            :src="p.image_url!"
                            :alt="p.name"
                            class="h-full w-full cursor-zoom-in object-cover"
                            @click="openZoom(p)"
                            @error="onImageError(p.id)"
                        />
                        <div
                            v-else
                            class="flex h-full w-full items-center justify-center"
                        >
                            <Package class="h-6 w-6 text-muted-foreground/30" />
                        </div>
                        <Badge
                            v-if="p.images && p.images.length > 1"
                            variant="secondary"
                            class="pointer-events-none absolute -top-1 -right-1 h-5 min-w-5 px-1 text-[10px]"
                        >
                            +{{ p.images.length - 1 }}
                        </Badge>
                    </div>

                    <!-- Info -->
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate leading-tight font-medium">
                                    {{ p.name }}
                                    <Badge
                                        v-if="p.has_types"
                                        variant="secondary"
                                        class="ml-1 text-[10px]"
                                    >
                                        🎨 {{ p.types_count ?? 0 }}
                                    </Badge>
                                </h3>
                                <p
                                    v-if="p.category"
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ p.category.name }}
                                </p>
                            </div>
                            <Badge
                                v-if="!p.is_active"
                                variant="destructive"
                                class="flex-shrink-0 text-[10px]"
                            >
                                {{ t('pageDealer.products.index.inactive') }}
                            </Badge>
                        </div>
                        <div class="mt-2 flex items-end justify-between">
                            <div>
                                <p class="font-semibold">
                                    <span
                                        v-if="p.has_types"
                                        class="text-xs font-normal text-muted-foreground"
                                        >{{
                                            t('pageDealer.products.index.from')
                                        }}
                                    </span>
                                    {{
                                        formatMoney(
                                            p.has_types
                                                ? (p.starting_price ?? p.price)
                                                : p.price,
                                        )
                                    }}
                                    <span
                                        class="text-xs font-normal text-muted-foreground"
                                        >/ {{ unitLabel(p.unit) }}</span
                                    >
                                </p>
                            </div>
                            <StockBadge
                                :stock="
                                    p.has_types ? (p.total_stock ?? 0) : p.stock
                                "
                                :min-stock="p.min_stock"
                                :unit="p.unit"
                                :pack-size="p.pack_size"
                                :stock-packs="p.stock_packs"
                                class="text-xs"
                            />
                        </div>
                    </div>

                    <!-- Actions -->
                    <div
                        v-if="canEdit || canStockIn || canToggleActive"
                        class="flex flex-shrink-0 flex-col items-center gap-1"
                    >
                        <Switch
                            v-if="canToggleActive"
                            :model-value="p.is_active"
                            :aria-label="
                                p.is_active
                                    ? t('pageDealer.products.index.deactivate')
                                    : t('pageDealer.products.index.activate')
                            "
                            @update:model-value="toggleActive(p)"
                        />
                        <Button
                            v-if="canStockIn"
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            :title="t('pageDealer.products.index.stockIn')"
                            @click="openStockIn(p)"
                        >
                            <PackagePlus class="h-4 w-4" />
                        </Button>
                        <Button
                            v-if="canEdit"
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            :title="t('pageDealer.products.index.edit')"
                            @click="router.get(`/dealer/products/${p.id}/edit`)"
                        >
                            <Pencil class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- TABLE VIEW -->
            <div
                v-if="products.data.length > 0 && effectiveViewMode === 'table'"
                class="hidden overflow-x-auto rounded-xl border sm:block"
            >
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th
                                class="w-12 px-4 py-3 font-medium text-muted-foreground"
                            >
                                #
                            </th>
                            <th class="w-14 px-4 py-3"></th>
                            <th class="px-4 py-3 font-medium">
                                {{ t('pageDealer.products.index.colName') }}
                            </th>
                            <th class="px-4 py-3 font-medium">
                                {{ t('pageDealer.products.index.colCategory') }}
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                {{ t('pageDealer.products.index.colPrice') }}
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                {{ t('pageDealer.products.index.colStock') }}
                            </th>
                            <th class="px-4 py-3 text-center font-medium">
                                {{ t('pageDealer.products.index.colStatus') }}
                            </th>
                            <th class="w-28 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="(p, i) in products.data"
                            :key="p.id"
                            class="group hover:bg-muted/20"
                            :class="{ 'opacity-60': !p.is_active }"
                        >
                            <td
                                class="px-4 py-2 font-mono text-xs text-muted-foreground tabular-nums"
                            >
                                {{ i + 1 }}
                            </td>
                            <td class="px-4 py-2">
                                <div
                                    class="h-10 w-10 overflow-hidden rounded-lg bg-muted"
                                >
                                    <img
                                        v-if="hasImage(p)"
                                        :src="p.image_url!"
                                        :alt="p.name"
                                        class="h-full w-full cursor-zoom-in object-cover"
                                        @click="openZoom(p)"
                                        @error="onImageError(p.id)"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center"
                                    >
                                        <Package
                                            class="h-4 w-4 text-muted-foreground/40"
                                        />
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-2">
                                <p class="font-medium">
                                    {{ p.name }}
                                    <Badge
                                        v-if="p.has_types"
                                        variant="secondary"
                                        class="ml-1 text-[10px]"
                                    >
                                        🎨
                                        {{
                                            t(
                                                'pageDealer.products.index.typesCount',
                                                { n: p.types_count ?? 0 },
                                            )
                                        }}
                                    </Badge>
                                </p>
                                <p
                                    v-if="p.description"
                                    class="line-clamp-1 text-xs text-muted-foreground"
                                >
                                    {{ p.description }}
                                </p>
                            </td>
                            <td class="px-4 py-2">
                                <span
                                    v-if="p.category"
                                    class="text-xs text-muted-foreground"
                                    >{{ p.category.name }}</span
                                >
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground/50"
                                    >—</span
                                >
                            </td>
                            <td class="px-4 py-2 text-right">
                                <div class="font-mono">
                                    <span
                                        v-if="p.has_types"
                                        class="text-xs text-muted-foreground"
                                        >{{
                                            t('pageDealer.products.index.from')
                                        }}
                                    </span>
                                    {{
                                        formatMoney(
                                            p.has_types
                                                ? (p.starting_price ?? p.price)
                                                : p.price,
                                        )
                                    }}
                                    <span class="text-xs text-muted-foreground"
                                        >/ {{ unitLabel(p.unit) }}</span
                                    >
                                </div>
                                <div
                                    v-if="!p.has_types && p.pack_size > 1"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ formatMoney(p.pack_price) }}
                                    {{
                                        t('pageDealer.products.index.perPack', {
                                            size: p.pack_size,
                                        })
                                    }}
                                </div>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <StockBadge
                                    :stock="
                                        p.has_types
                                            ? (p.total_stock ?? 0)
                                            : p.stock
                                    "
                                    :min-stock="p.min_stock"
                                    :unit="p.unit"
                                    :pack-size="p.pack_size"
                                    :stock-packs="p.stock_packs"
                                    show-packs
                                />
                            </td>
                            <td class="px-4 py-2 text-center">
                                <div
                                    class="flex items-center justify-center gap-2"
                                >
                                    <Switch
                                        v-if="canToggleActive"
                                        :model-value="p.is_active"
                                        :aria-label="
                                            p.is_active
                                                ? t(
                                                      'pageDealer.products.index.deactivate',
                                                  )
                                                : t(
                                                      'pageDealer.products.index.activate',
                                                  )
                                        "
                                        @update:model-value="toggleActive(p)"
                                    />
                                    <span class="text-xs text-muted-foreground">
                                        {{
                                            p.is_active
                                                ? t(
                                                      'pageDealer.products.index.active',
                                                  )
                                                : t(
                                                      'pageDealer.products.index.inactive',
                                                  )
                                        }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-2">
                                <div
                                    v-if="canEdit || canStockIn"
                                    class="flex justify-end gap-1"
                                >
                                    <Button
                                        v-if="canStockIn"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8"
                                        :title="
                                            t(
                                                'pageDealer.products.index.stockIn',
                                            )
                                        "
                                        @click="openStockIn(p)"
                                    >
                                        <PackagePlus class="h-3.5 w-3.5" />
                                    </Button>
                                    <Button
                                        v-if="canEdit"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8"
                                        :title="
                                            t('pageDealer.products.index.edit')
                                        "
                                        @click="
                                            router.get(
                                                `/dealer/products/${p.id}/edit`,
                                            )
                                        "
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                    </Button>
                                    <Button
                                        v-if="canEdit"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-destructive"
                                        :title="
                                            t(
                                                'pageDealer.products.index.delete',
                                            )
                                        "
                                        @click="deleteProduct(p)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </InfiniteScroll>

        <!-- Empty state -->
        <div
            v-if="products.data.length === 0"
            class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed py-16"
        >
            <Box class="h-12 w-12 text-muted-foreground/40" />
            <div class="text-center">
                <p class="font-medium">
                    {{ t('pageDealer.products.index.emptyTitle') }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{ t('pageDealer.products.index.emptyHint') }}
                </p>
            </div>
            <Button
                v-if="canCreate"
                @click="router.get('/dealer/products/create')"
            >
                <Plus class="mr-2 h-4 w-4" />
                {{ t('pageDealer.products.index.newProduct') }}
            </Button>
        </div>

        <ImageLightbox
            :images="lightboxImages"
            :initial-idx="lightboxIdx"
            :open="lightboxOpen"
            @close="lightboxOpen = false"
        />

        <StockInModal
            v-model:open="stockInOpen"
            :product="stockInProduct"
            :suppliers="suppliers"
            @success="onStockInSuccess"
        />

        <StockInBulkModal
            v-model:open="stockInBulkOpen"
            :products="products.data"
            :suppliers="suppliers"
            @success="onStockInSuccess"
        />
    </div>
</template>
