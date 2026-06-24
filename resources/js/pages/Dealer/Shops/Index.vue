<script setup lang="ts">
import { Head, InfiniteScroll, router } from '@inertiajs/vue3';
import { CalendarOff, Eye, LayoutGrid, List, MapPin, MapPinned, MapPinOff, Pencil, Phone, Plus, Search, Store, Table2, UserCheck, UserX, Users, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ImageLightbox from '@/components/ImageLightbox.vue';
import VisitModal from '@/components/dealer/VisitModal.vue';

const { t } = useI18n();
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/date';

type Shop = {
    id: number;
    name: string;
    phone: string;
    address: string | null;
    region: string | null;
    district: string | null;
    photo_url: string | null;
    balance: number;
    pending_total?: number;
    is_active: boolean;
    deliveryman: { id: number; name: string } | null;
    members_count?: number;
    active_members_count?: number;
    has_members?: boolean;
    last_order_at?: string | null;
    last_visit_at?: string | null;
    visits_count?: number;
    parent_shop_id: number | null;
    is_main_branch: boolean;
    parent: { id: number; name: string } | null;
    branches_count?: number;
    branches_balance_sum?: number;
    total_balance_with_branches?: number;
    outside_zone?: boolean;
};

type RegionOption = { name: string; districts: string[] };

type Activity = '' | 'active' | 'inactive';

type Filters = { search?: string; region?: string; district?: string; outside_zone?: boolean; activity?: Activity; inactive_days?: number };

type Paginated<T> = {
    data: T[];
    meta: { total: number; current_page: number; last_page: number; per_page: number; from: number | null; to: number | null };
};

const props = defineProps<{
    shops: Paginated<Shop>;
    filters: Filters;
    regions: RegionOption[];
    hasDeliveryZones?: boolean;
}>();

const search = ref(props.filters.search ?? '');
const region = ref(props.filters.region ?? '');
const district = ref(props.filters.district ?? '');
const onlyOutsideZone = ref(Boolean(props.filters.outside_zone));
const activity = ref<Activity>(props.filters.activity ?? '');
const inactiveDays = ref<number>(props.filters.inactive_days ?? 14);

// Faolsizlik oynasi presetlari (kun). "custom" — qo'lda kiritish.
const PRESET_DAYS = [3, 7, 14, 30, 31];
const inactivePreset = ref<string>(PRESET_DAYS.includes(inactiveDays.value) ? String(inactiveDays.value) : 'custom');

const previewUrl = ref<string | null>(null);

// Vizit modal — qaysi mijoz uchun ochiq
const visitShop = ref<Shop | null>(null);

type ViewMode = 'grid' | 'list' | 'table';
const viewMode = ref<ViewMode>('grid');

const VIEW_STORAGE_KEY = 'dealer.shops.viewMode';

const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth < 640 : false);

function handleResize() {
    isMobile.value = window.innerWidth < 640;
}

onMounted(() => {
    const saved = localStorage.getItem(VIEW_STORAGE_KEY);

    if (saved === 'card' || saved === 'grid') {
        viewMode.value = 'grid';
    } else if (saved === 'list' || saved === 'table') {
        viewMode.value = saved;
    }

    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
});

watch(viewMode, (v) => {
    localStorage.setItem(VIEW_STORAGE_KEY, v);
});

const effectiveViewMode = computed<ViewMode>(() => {
    if (viewMode.value === 'table' && isMobile.value) {
        return 'list';
    }

    return viewMode.value;
});

function setView(mode: ViewMode) {
    viewMode.value = mode;
}

const districtItems = computed<{ value: string; label: string }[]>(() => {
    const found = props.regions.find((r) => r.name === region.value);

    return (found?.districts ?? []).map((d) => ({ value: d, label: d }));
});

const hasFilters = computed(() => Boolean(search.value || region.value || district.value || onlyOutsideZone.value || activity.value));

function applyFilters() {
    router.get(
        '/dealer/shops',
        {
            search: search.value || undefined,
            region: region.value || undefined,
            district: district.value || undefined,
            outside_zone: onlyOutsideZone.value || undefined,
            activity: activity.value || undefined,
            inactive_days: activity.value && inactiveDays.value !== 14 ? inactiveDays.value : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true, reset: ['shops'] },
    );
}

function toggleOutsideZone() {
    onlyOutsideZone.value = !onlyOutsideZone.value;
    applyFilters();
}

function setActivity(v: Activity) {
    activity.value = activity.value === v ? '' : v;
    applyFilters();
}

// Preset tanlansa — darhol; custom kiritilsa — debounce bilan qayta so'rov
let daysTimer: ReturnType<typeof setTimeout> | null = null;
watch(inactiveDays, () => {
    if (inactivePreset.value !== 'custom') {
        return;
    }

    if (daysTimer) {
        clearTimeout(daysTimer);
    }
    daysTimer = setTimeout(applyFilters, 500);
});

function onPresetChange(v: string) {
    inactivePreset.value = v;

    if (v !== 'custom') {
        inactiveDays.value = Number(v);
        applyFilters();
    }
}

// Mijoz faolsizmi: faol vakili yo'q (yo'q yoki bloklagan) YOKI N kun buyurtmasiz.
// Lekin N kun ichida vizit qilingan bo'lsa — faol (vizit faollikni tiklaydi).
function isInactive(s: Shop): boolean {
    const thresholdMs = Date.now() - inactiveDays.value * 86_400_000;

    if (s.last_visit_at && new Date(s.last_visit_at).getTime() >= thresholdMs) {
        return false;
    }

    if ((s.active_members_count ?? 0) === 0) {
        return true;
    }

    if (!s.last_order_at) {
        return true;
    }

    return new Date(s.last_order_at).getTime() < thresholdMs;
}

// Bitta ham vakili yo'q — qizil bilan ajratiladi
function noMembers(s: Shop): boolean {
    return (s.members_count ?? 0) === 0;
}

function openVisit(s: Shop) {
    visitShop.value = s;
}

function onVisitSubmitted() {
    router.reload({ only: ['shops'] });
}

// Live qidiruv: yozayotganda har bosishga so'rov ketmasin —
// oxirgi bosishdan 300ms keyin yuboramiz (debounce).
let searchTimer: ReturnType<typeof setTimeout> | null = null;
watch(search, () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
    searchTimer = setTimeout(applyFilters, 300);
});

function onRegionChange() {
    district.value = '';
    applyFilters();
}

function clearFilters() {
    search.value = '';
    region.value = '';
    district.value = '';
    onlyOutsideZone.value = false;
    activity.value = '';
    inactiveDays.value = 14;
    inactivePreset.value = '14';
    applyFilters();
}

function openPreview(url: string) {
    previewUrl.value = url;
}

function formatMoney(n: number): string {
    return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

function rowNumber(index: number): number {
    return index + 1;
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.shops.indexHead')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageDealer.shops.indexTitle') }}</h1>
                <p class="text-sm text-muted-foreground">{{ shops.meta.total }} {{ t('pageDealer.shops.countSuffix') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex rounded-lg border p-0.5">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8"
                        :class="effectiveViewMode === 'grid' ? 'bg-muted' : ''"
                        :title="t('pageDealer.shops.viewCards')"
                        @click="setView('grid')"
                    >
                        <LayoutGrid class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8"
                        :class="effectiveViewMode === 'list' ? 'bg-muted' : ''"
                        :title="t('pageDealer.shops.viewList')"
                        @click="setView('list')"
                    >
                        <List class="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="hidden h-8 w-8 sm:flex"
                        :class="effectiveViewMode === 'table' ? 'bg-muted' : ''"
                        :title="t('pageDealer.shops.viewTable')"
                        @click="setView('table')"
                    >
                        <Table2 class="h-4 w-4" />
                    </Button>
                </div>
                <Button class="flex-1 sm:flex-initial" @click="router.get('/dealer/shops/create')">
                    <Plus class="mr-2 h-4 w-4" />
                    {{ t('pageDealer.shops.newShop') }}
                </Button>
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-[1fr_220px_220px]">
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="search"
                        :placeholder="t('pageDealer.shops.searchPlaceholder')"
                        class="pl-10"
                        @keyup.enter="applyFilters"
                    />
                </div>
                <SearchableSelect
                    v-model="region"
                    :items="regions"
                    value-key="name"
                    label-key="name"
                    :placeholder="t('pageDealer.shops.regionAll')"
                    :search-placeholder="t('pageDealer.shops.regionSearch')"
                    :empty-text="t('pageDealer.shops.regionEmpty')"
                    @change="onRegionChange"
                />
                <SearchableSelect
                    v-model="district"
                    :items="districtItems"
                    :disabled="!region"
                    :placeholder="t('pageDealer.shops.districtAll')"
                    :search-placeholder="t('pageDealer.shops.districtSearch')"
                    :empty-text="t('pageDealer.shops.districtEmpty')"
                    @change="applyFilters"
                />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="hasDeliveryZones"
                    size="sm"
                    :variant="onlyOutsideZone ? 'default' : 'outline'"
                    @click="toggleOutsideZone"
                >
                    <MapPinOff class="mr-1.5 h-4 w-4" />
                    {{ t('pageDealer.shops.outsideZoneFilter') }}
                </Button>
                <div class="flex rounded-md border p-0.5">
                    <Button
                        size="sm"
                        :variant="activity === 'active' ? 'default' : 'ghost'"
                        class="h-8"
                        @click="setActivity('active')"
                    >
                        <UserCheck class="mr-1.5 h-4 w-4" />
                        {{ t('pageDealer.shops.activeFilter') }}
                    </Button>
                    <Button
                        size="sm"
                        :variant="activity === 'inactive' ? 'default' : 'ghost'"
                        class="h-8"
                        @click="setActivity('inactive')"
                    >
                        <UserX class="mr-1.5 h-4 w-4" />
                        {{ t('pageDealer.shops.inactiveFilter') }}
                    </Button>
                </div>
                <template v-if="activity">
                    <Select :model-value="inactivePreset" @update:model-value="(v) => onPresetChange(String(v))">
                        <SelectTrigger class="h-9 w-[150px]" :title="t('pageDealer.shops.inactiveHint')">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="3">{{ t('pageDealer.shops.days3') }}</SelectItem>
                            <SelectItem value="7">{{ t('pageDealer.shops.week1') }}</SelectItem>
                            <SelectItem value="14">{{ t('pageDealer.shops.weeks2') }}</SelectItem>
                            <SelectItem value="30">{{ t('pageDealer.shops.month1') }}</SelectItem>
                            <SelectItem value="31">{{ t('pageDealer.shops.monthPlus') }}</SelectItem>
                            <SelectItem value="custom">{{ t('pageDealer.shops.customDays') }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <div
                        v-if="inactivePreset === 'custom'"
                        class="flex items-center gap-1.5 rounded-md border bg-muted/30 px-2 py-1"
                        :title="t('pageDealer.shops.inactiveHint')"
                    >
                        <Input
                            v-model.number="inactiveDays"
                            type="number"
                            min="1"
                            max="365"
                            class="h-7 w-16 border-0 bg-transparent px-1 text-center shadow-none focus-visible:ring-0"
                        />
                        <span class="whitespace-nowrap text-xs text-muted-foreground">{{ t('pageDealer.shops.daysSuffix') }}</span>
                    </div>
                </template>
                <Button v-if="hasFilters" size="sm" variant="ghost" class="text-muted-foreground" @click="clearFilters">
                    <X class="mr-1.5 h-4 w-4" />
                    {{ t('pageDealer.common.clear') }}
                </Button>
            </div>
        </div>

        <InfiniteScroll data="shops">
        <!-- Kartalar ko'rinishi -->
        <div v-if="effectiveViewMode === 'grid' && shops.data.length > 0" class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
            <div
                v-for="(s, i) in shops.data"
                :key="s.id"
                class="group cursor-pointer overflow-hidden rounded-xl border bg-card transition-shadow hover:shadow-md"
                :class="[{ 'opacity-60': !s.is_active }, noMembers(s) ? 'ring-2 ring-red-500/70' : (isInactive(s) ? 'ring-2 ring-amber-400/70' : '')]"
                @click="router.get(`/dealer/shops/${s.id}`)"
            >
                <div class="relative aspect-square overflow-hidden bg-muted">
                    <Badge variant="secondary" class="pointer-events-none absolute left-1.5 top-1.5 bg-background/85 px-1.5 py-0 text-[10px] font-mono backdrop-blur">
                        #{{ rowNumber(i) }}
                    </Badge>
                    <img
                        v-if="s.photo_url"
                        :src="s.photo_url"
                        :alt="s.name"
                        class="h-full w-full cursor-zoom-in object-cover transition-transform group-hover:scale-105"
                        @click.stop="openPreview(s.photo_url!)"
                    />
                    <div v-else class="flex h-full w-full items-center justify-center">
                        <Store class="h-10 w-10 text-muted-foreground/30" />
                    </div>
                    <Badge v-if="!s.is_active" variant="destructive" class="absolute left-1.5 top-7 px-1.5 py-0 text-[10px]">
                        {{ t('pageDealer.shops.inactive') }}
                    </Badge>
                    <Badge v-if="s.outside_zone" variant="destructive" class="pointer-events-none absolute left-1.5 gap-0.5 px-1.5 py-0 text-[10px]" :class="s.is_active ? 'top-7' : 'top-[3.25rem]'">
                        <MapPinOff class="h-3 w-3" />
                        {{ t('pageDealer.shops.outsideZone') }}
                    </Badge>
                    <Badge
                        class="pointer-events-none absolute right-1.5 top-1.5 gap-0.5 px-1.5 py-0 text-[10px] backdrop-blur"
                        :class="noMembers(s) ? 'border-red-500/40 bg-red-500/90 text-white' : 'bg-background/85'"
                        :variant="noMembers(s) ? 'destructive' : 'secondary'"
                    >
                        <Users class="h-3 w-3" />
                        {{ s.members_count ?? 0 }}
                    </Badge>
                    <Badge v-if="noMembers(s)" class="pointer-events-none absolute right-1.5 top-7 gap-0.5 border-red-500/40 bg-red-500/90 px-1.5 py-0 text-[10px] text-white">
                        <UserX class="h-3 w-3" />
                        {{ t('pageDealer.shops.noMembersBadge') }}
                    </Badge>
                    <Badge v-else-if="isInactive(s)" class="pointer-events-none absolute right-1.5 top-7 gap-0.5 border-amber-500/40 bg-amber-500/90 px-1.5 py-0 text-[10px] text-white">
                        <UserX class="h-3 w-3" />
                        {{ t('pageDealer.shops.inactiveBadge') }}
                    </Badge>
                    <div class="pointer-events-none absolute inset-x-0 bottom-0 flex items-center justify-center gap-1.5 bg-gradient-to-t from-black/60 to-transparent pb-2 pt-5">
                        <Button size="icon" variant="secondary" class="pointer-events-auto h-7 w-7" :title="t('pageDealer.shops.view')" @click.stop="router.get(`/dealer/shops/${s.id}`)">
                            <Eye class="h-3.5 w-3.5" />
                        </Button>
                        <Button size="icon" variant="secondary" class="pointer-events-auto h-7 w-7" :title="t('pageDealer.shops.edit')" @click.stop="router.get(`/dealer/shops/${s.id}/edit`)">
                            <Pencil class="h-3.5 w-3.5" />
                        </Button>
                        <Button size="icon" variant="secondary" class="pointer-events-auto h-7 w-7" :title="t('pageDealer.shops.visitAction')" @click.stop="openVisit(s)">
                            <MapPinned class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
                <div class="flex flex-col gap-1 p-2 sm:p-2.5">
                    <div class="flex items-center gap-1">
                        <h3 class="flex-1 text-[13px] font-semibold leading-tight line-clamp-1 sm:text-sm">{{ s.name }}</h3>
                        <Badge v-if="s.parent_shop_id" variant="outline" class="shrink-0 px-1 py-0 text-[9px]">{{ t('pageDealer.shops.branch') }}</Badge>
                        <Badge v-else-if="(s.branches_count ?? 0) > 0" variant="secondary" class="shrink-0 px-1 py-0 text-[9px]">
                            {{ s.branches_count }}
                        </Badge>
                    </div>
                    <p v-if="s.parent" class="truncate text-[10px] text-muted-foreground sm:text-[11px]">↳ {{ s.parent.name }}</p>
                    <p class="flex items-center gap-1 text-[10px] text-muted-foreground sm:text-[11px]">
                        <Phone class="h-3 w-3 flex-shrink-0" />
                        <span class="truncate">{{ s.phone }}</span>
                    </p>
                    <p v-if="s.address" class="flex items-center gap-1 text-[10px] text-muted-foreground sm:text-[11px]">
                        <MapPin class="h-3 w-3 flex-shrink-0" />
                        <span class="line-clamp-1">{{ s.address }}</span>
                    </p>
                    <p class="text-sm font-bold leading-none sm:text-base" :class="s.balance < 0 ? 'text-amber-600' : ''">
                        {{ formatMoney(s.balance) }}
                        <span class="text-[10px] font-normal text-muted-foreground">{{ t('pageDealer.common.soum') }}</span>
                    </p>
                    <p v-if="s.is_main_branch && (s.branches_count ?? 0) > 0 && (s.total_balance_with_branches ?? s.balance) !== s.balance"
                       class="text-[10px]"
                       :class="(s.total_balance_with_branches ?? s.balance) < 0 ? 'text-amber-600' : 'text-muted-foreground'">
                        {{ t('pageDealer.shops.totalLabel') }} {{ formatMoney(s.total_balance_with_branches ?? s.balance) }}
                    </p>
                    <p v-if="(s.pending_total ?? 0) > 0" class="text-[10px] text-sky-600">
                        +{{ formatMoney(s.pending_total ?? 0) }} {{ t('pageDealer.shops.pendingSuffix') }}
                    </p>
                    <p class="flex items-center gap-1 text-[10px] text-muted-foreground sm:text-[11px]">
                        <MapPinned class="h-3 w-3 flex-shrink-0" />
                        <span class="truncate">{{ t('pageDealer.shops.lastVisit') }}: {{ s.last_visit_at ? formatDate(s.last_visit_at) : t('pageDealer.shops.noVisitYet') }}</span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Ro'yxat ko'rinishi (mobile-friendly) -->
        <div v-else-if="effectiveViewMode === 'list' && shops.data.length > 0" class="divide-y rounded-xl border">
            <div
                v-for="(s, i) in shops.data"
                :key="s.id"
                class="flex cursor-pointer items-center gap-3 p-3 transition-colors hover:bg-muted/20"
                :class="noMembers(s) ? 'bg-red-50/70 dark:bg-red-500/10' : (isInactive(s) ? 'bg-amber-50/60 dark:bg-amber-500/5' : '')"
                @click="router.get(`/dealer/shops/${s.id}`)"
            >
                <span class="w-6 flex-shrink-0 text-center text-xs font-medium text-muted-foreground tabular-nums">
                    {{ rowNumber(i) }}
                </span>
                <button
                    v-if="s.photo_url"
                    type="button"
                    class="h-12 w-12 flex-shrink-0 overflow-hidden rounded-lg border bg-muted"
                    @click.stop="openPreview(s.photo_url!)"
                >
                    <img :src="s.photo_url" :alt="s.name" class="h-full w-full cursor-zoom-in object-cover" />
                </button>
                <div v-else class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Store class="h-5 w-5" />
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 truncate font-medium leading-tight">
                                {{ s.name }}
                                <Badge v-if="s.parent_shop_id" variant="outline" class="shrink-0 text-[10px] font-normal">{{ t('pageDealer.shops.branch') }}</Badge>
                                <Badge v-else-if="(s.branches_count ?? 0) > 0" variant="secondary" class="shrink-0 text-[10px] font-normal">
                                    {{ t('pageDealer.shops.mainBranch') }} ({{ s.branches_count }})
                                </Badge>
                            </p>
                            <p v-if="s.parent" class="mt-0.5 truncate text-xs text-muted-foreground">↳ {{ s.parent.name }}</p>
                            <p class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                                <Phone class="h-3 w-3 flex-shrink-0" />
                                <span class="truncate">{{ s.phone }}</span>
                            </p>
                            <p v-if="s.address" class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground">
                                <MapPin class="h-3 w-3 flex-shrink-0" />
                                <span class="truncate">{{ s.address }}</span>
                            </p>
                        </div>
                        <div class="flex flex-shrink-0 items-center gap-1.5">
                            <Badge v-if="noMembers(s)" class="gap-0.5 border-red-500/40 bg-red-500/15 px-1.5 text-[10px] text-red-700 dark:text-red-400">
                                <UserX class="h-3 w-3" />
                                {{ t('pageDealer.shops.noMembersBadge') }}
                            </Badge>
                            <Badge v-else-if="isInactive(s)" class="gap-0.5 border-amber-500/40 bg-amber-500/15 px-1.5 text-[10px] text-amber-700 dark:text-amber-400">
                                <UserX class="h-3 w-3" />
                                {{ t('pageDealer.shops.inactiveBadge') }}
                            </Badge>
                            <Badge :variant="s.is_active ? 'default' : 'outline'" class="text-[10px]">
                                {{ s.is_active ? t('pageDealer.shops.active') : t('pageDealer.shops.inactive') }}
                            </Badge>
                            <Button size="icon" variant="ghost" class="h-7 w-7" :title="t('pageDealer.shops.visitAction')" @click.stop="openVisit(s)">
                                <MapPinned class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                    <div class="mt-1.5 flex items-center justify-between gap-2 text-xs">
                        <span class="flex items-center gap-2 text-muted-foreground">
                            <span class="flex items-center gap-1" :class="noMembers(s) ? 'font-semibold text-red-600 dark:text-red-400' : ''">
                                <Users class="h-3 w-3" />
                                {{ s.members_count ?? 0 }}
                            </span>
                            <span class="flex items-center gap-1">
                                <MapPinned class="h-3 w-3" />
                                {{ s.last_visit_at ? formatDate(s.last_visit_at) : t('pageDealer.shops.noVisitYet') }}
                            </span>
                        </span>
                        <div class="flex flex-col items-end gap-0.5">
                            <span :class="s.balance < 0 ? 'font-medium text-amber-600' : 'text-muted-foreground'">
                                {{ formatMoney(s.balance) }} {{ t('pageDealer.common.soum') }}
                            </span>
                            <span v-if="s.is_main_branch && (s.branches_count ?? 0) > 0 && (s.total_balance_with_branches ?? s.balance) !== s.balance"
                                  class="text-[10px]"
                                  :class="(s.total_balance_with_branches ?? s.balance) < 0 ? 'text-amber-600' : 'text-muted-foreground'">
                                {{ t('pageDealer.shops.totalLabel') }} {{ formatMoney(s.total_balance_with_branches ?? s.balance) }}
                            </span>
                            <span v-if="(s.pending_total ?? 0) > 0" class="text-[10px] text-sky-600">
                                +{{ formatMoney(s.pending_total ?? 0) }} {{ t('pageDealer.shops.pendingSuffix') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Jadval ko'rinishi -->
        <Card v-else-if="effectiveViewMode === 'table' && shops.data.length > 0">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1040px] text-left text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="w-12 px-4 py-3 text-center font-medium">#</th>
                                <th class="w-14 px-4 py-3 font-medium"></th>
                                <th class="px-4 py-3 font-medium">{{ t('pageDealer.shops.tableName') }}</th>
                                <th class="px-4 py-3 font-medium">{{ t('pageDealer.shops.tablePhone') }}</th>
                                <th class="px-4 py-3 font-medium">{{ t('pageDealer.shops.tableRegion') }}</th>
                                <th class="px-4 py-3 font-medium">{{ t('pageDealer.shops.tableDistrict') }}</th>
                                <th class="px-4 py-3 font-medium">{{ t('pageDealer.shops.tableAddress') }}</th>
                                <th class="px-4 py-3 text-center font-medium">{{ t('pageDealer.shops.tableMembers') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ t('pageDealer.shops.tableBalance') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ t('pageDealer.shops.tablePending') }}</th>
                                <th class="px-4 py-3 font-medium">{{ t('pageDealer.shops.tableStatus') }}</th>
                                <th class="px-4 py-3 font-medium">{{ t('pageDealer.shops.lastVisit') }}</th>
                                <th class="w-12 px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="(s, i) in shops.data"
                                :key="s.id"
                                class="cursor-pointer transition-colors hover:bg-muted/20"
                                :class="noMembers(s) ? 'bg-red-50/70 dark:bg-red-500/10' : (isInactive(s) ? 'bg-amber-50/60 dark:bg-amber-500/5' : '')"
                                @click="router.get(`/dealer/shops/${s.id}`)"
                            >
                                <td class="px-4 py-3 text-center text-xs text-muted-foreground tabular-nums">
                                    {{ rowNumber(i) }}
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        v-if="s.photo_url"
                                        type="button"
                                        class="h-9 w-9 overflow-hidden rounded-md border bg-muted"
                                        @click.stop="openPreview(s.photo_url!)"
                                    >
                                        <img :src="s.photo_url" :alt="s.name" class="h-full w-full cursor-zoom-in object-cover" />
                                    </button>
                                    <div v-else class="flex h-9 w-9 items-center justify-center rounded-md bg-primary/10 text-primary">
                                        <Store class="h-4 w-4" />
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-medium">
                                    <div class="flex flex-col">
                                        <span class="flex items-center gap-1.5">
                                            {{ s.name }}
                                            <Badge v-if="s.parent_shop_id" variant="outline" class="text-[10px] font-normal">{{ t('pageDealer.shops.branch') }}</Badge>
                                            <Badge v-else-if="(s.branches_count ?? 0) > 0" variant="secondary" class="text-[10px] font-normal">
                                                {{ t('pageDealer.shops.mainBranch') }} ({{ s.branches_count }})
                                            </Badge>
                                        </span>
                                        <span v-if="s.parent" class="text-xs text-muted-foreground">↳ {{ s.parent.name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">{{ s.phone }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ s.region ?? '—' }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ s.district ?? '—' }}</td>
                                <td class="max-w-xs px-4 py-3 text-muted-foreground">
                                    <span class="line-clamp-1">{{ s.address ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-3 text-center" :class="noMembers(s) ? 'font-semibold text-red-600 dark:text-red-400' : ''">{{ s.members_count ?? 0 }}</td>
                                <td
                                    class="px-4 py-3 text-right font-mono"
                                    :class="s.balance < 0 ? 'text-amber-600' : 'text-muted-foreground'"
                                >
                                    <div>{{ formatMoney(s.balance) }}</div>
                                    <div v-if="s.is_main_branch && (s.branches_count ?? 0) > 0 && (s.total_balance_with_branches ?? s.balance) !== s.balance"
                                         class="text-[11px]"
                                         :class="(s.total_balance_with_branches ?? s.balance) < 0 ? 'text-amber-600' : 'text-muted-foreground/70'">
                                        {{ t('pageDealer.shops.totalLabel') }} {{ formatMoney(s.total_balance_with_branches ?? s.balance) }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-xs"
                                    :class="(s.pending_total ?? 0) > 0 ? 'text-sky-600' : 'text-muted-foreground/50'"
                                >
                                    {{ (s.pending_total ?? 0) > 0 ? formatMoney(s.pending_total ?? 0) : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <Badge :variant="s.is_active ? 'default' : 'outline'" class="text-xs">
                                            {{ s.is_active ? t('pageDealer.shops.active') : t('pageDealer.shops.inactive') }}
                                        </Badge>
                                        <Badge v-if="noMembers(s)" class="gap-0.5 border-red-500/40 bg-red-500/15 text-xs text-red-700 dark:text-red-400">
                                            <UserX class="h-3 w-3" />
                                            {{ t('pageDealer.shops.noMembersBadge') }}
                                        </Badge>
                                        <Badge v-else-if="isInactive(s)" class="gap-0.5 border-amber-500/40 bg-amber-500/15 text-xs text-amber-700 dark:text-amber-400">
                                            <UserX class="h-3 w-3" />
                                            {{ t('pageDealer.shops.inactiveBadge') }}
                                        </Badge>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs text-muted-foreground">
                                    {{ s.last_visit_at ? formatDate(s.last_visit_at) : t('pageDealer.shops.noVisitYet') }}
                                </td>
                                <td class="px-4 py-3">
                                    <Button size="icon" variant="ghost" class="h-7 w-7" :title="t('pageDealer.shops.visitAction')" @click.stop="openVisit(s)">
                                        <MapPinned class="h-4 w-4" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
        </InfiniteScroll>

        <div v-if="shops.data.length === 0" class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed py-16">
            <Store class="h-12 w-12 text-muted-foreground/40" />
            <div class="text-center">
                <p class="font-medium">{{ hasFilters ? t('pageDealer.shops.emptyFilteredTitle') : t('pageDealer.shops.emptyTitle') }}</p>
                <p class="text-sm text-muted-foreground">
                    {{ hasFilters ? t('pageDealer.shops.emptyFilteredDesc') : t('pageDealer.shops.emptyDesc') }}
                </p>
            </div>
            <Button v-if="!hasFilters" @click="router.get('/dealer/shops/create')">
                <Plus class="mr-2 h-4 w-4" />
                {{ t('pageDealer.shops.newShop') }}
            </Button>
            <Button v-else variant="outline" @click="clearFilters">
                <X class="mr-2 h-4 w-4" />
                {{ t('pageDealer.common.clear') }}
            </Button>
        </div>

        <ImageLightbox
            v-if="previewUrl"
            :images="[previewUrl]"
            :open="previewUrl !== null"
            @close="previewUrl = null"
        />

        <VisitModal
            v-if="visitShop"
            :open="visitShop !== null"
            :shop-id="visitShop.id"
            :shop-name="visitShop.name"
            @update:open="(v) => { if (!v) visitShop = null; }"
            @submitted="onVisitSubmitted"
        />
    </div>
</template>
