<script setup lang="ts">
import {
    Building2,
    CalendarClock,
    Check,
    Filter as FilterIcon,
    Search,
    Store,
    Tag,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref, type Component } from 'vue';
import { useI18n } from 'vue-i18n';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { useCurrency } from '@/composables/useCurrency';

const { t } = useI18n();
const { symbol: currencySymbol } = useCurrency();

type AudienceType = string;

type AudienceConfig = {
    shop_ids?: number[];
    dealer_ids?: number[];
    balance_op?: string;
    balance_value?: number;
    debtors_only?: boolean;
    min_days_since_last_order?: number;
    region?: string;
    category_ids?: number[];
};

type Shop = {
    id: number;
    name: string;
    phone?: string;
    region?: string | null;
    balance?: number;
};
type Category = { id: number; name: string };
type Dealer = { id: number; name: string };
type Option = { value: string; label: string };

const props = withDefaults(
    defineProps<{
        modelType: AudienceType;
        modelConfig: AudienceConfig;
        options: Option[];
        shops: Shop[];
        categories: Category[];
        regions: string[];
        previewCount: number | null;
        previewLoading: boolean;
        dealers?: Dealer[];
    }>(),
    {
        dealers: () => [],
    },
);

const emit = defineEmits<{
    'update:modelType': [v: AudienceType];
    'update:modelConfig': [v: AudienceConfig];
}>();

const selectedShopIds = computed<number[]>(() =>
    Array.isArray(props.modelConfig.shop_ids) ? props.modelConfig.shop_ids : [],
);
const selectedDealerIds = computed<number[]>(() =>
    Array.isArray(props.modelConfig.dealer_ids)
        ? props.modelConfig.dealer_ids
        : [],
);
const selectedCategoryIds = computed<number[]>(() =>
    Array.isArray(props.modelConfig.category_ids)
        ? props.modelConfig.category_ids
        : [],
);

const showDealerPicker = computed(
    () =>
        props.modelType === 'platform_dealers' ||
        props.modelType === 'platform_shop_members',
);
const showFilter = computed(
    () =>
        props.modelType === 'filter' ||
        props.modelType === 'platform_shop_members',
);

const typeIcons: Record<string, Component> = {
    all_active: Users,
    selected_shops: Store,
    filter: FilterIcon,
    platform_dealers: Building2,
    platform_shop_members: Users,
};

function iconFor(value: string): Component {
    return typeIcons[value] ?? Users;
}

// --- Search ---
const shopSearch = ref('');
const dealerSearch = ref('');

const filteredShops = computed(() => {
    const q = shopSearch.value.trim().toLowerCase();

    if (!q) {
        return props.shops;
    }

    return props.shops.filter(
        (s) =>
            s.name.toLowerCase().includes(q) ||
            (s.phone ?? '').toLowerCase().includes(q),
    );
});

const filteredDealers = computed(() => {
    const q = dealerSearch.value.trim().toLowerCase();

    if (!q) {
        return props.dealers;
    }

    return props.dealers.filter((d) => d.name.toLowerCase().includes(q));
});

const balanceOps = [
    { op: '<', label: '<' },
    { op: '<=', label: '≤' },
    { op: '=', label: '=' },
    { op: '>=', label: '≥' },
    { op: '>', label: '>' },
];

function setType(t: AudienceType) {
    emit('update:modelType', t);
    emit('update:modelConfig', {});
    shopSearch.value = '';
    dealerSearch.value = '';
}

function toggleShop(id: number) {
    const set = new Set(selectedShopIds.value);
    set.has(id) ? set.delete(id) : set.add(id);
    emit('update:modelConfig', {
        ...props.modelConfig,
        shop_ids: Array.from(set),
    });
}

function toggleDealer(id: number) {
    const set = new Set(selectedDealerIds.value);
    set.has(id) ? set.delete(id) : set.add(id);
    emit('update:modelConfig', {
        ...props.modelConfig,
        dealer_ids: Array.from(set),
    });
}

function toggleCategory(id: number) {
    const set = new Set(selectedCategoryIds.value);
    set.has(id) ? set.delete(id) : set.add(id);
    emit('update:modelConfig', {
        ...props.modelConfig,
        category_ids: Array.from(set),
    });
}

function selectAllShops() {
    emit('update:modelConfig', {
        ...props.modelConfig,
        shop_ids: filteredShops.value.map((s) => s.id),
    });
}

function clearShops() {
    emit('update:modelConfig', { ...props.modelConfig, shop_ids: [] });
}

function selectAllDealers() {
    emit('update:modelConfig', {
        ...props.modelConfig,
        dealer_ids: filteredDealers.value.map((d) => d.id),
    });
}

function clearDealers() {
    emit('update:modelConfig', { ...props.modelConfig, dealer_ids: [] });
}

function patchConfig(patch: Partial<AudienceConfig>) {
    emit('update:modelConfig', { ...props.modelConfig, ...patch });
}

function shopInitial(name: string): string {
    return (name?.trim()?.[0] ?? '?').toUpperCase();
}
</script>

<template>
    <Card class="overflow-hidden">
        <CardContent class="space-y-4 p-3 sm:p-5">
            <!-- Header: title + live count -->
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <div
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary"
                    >
                        <Users class="h-4 w-4" />
                    </div>
                    <h3 class="text-sm font-semibold sm:text-base">
                        {{ t('component.broadcast.audience.title') }}
                    </h3>
                </div>
                <span
                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="
                        previewCount === 0 && !previewLoading
                            ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400'
                            : 'bg-primary/10 text-primary'
                    "
                >
                    <span
                        v-if="previewLoading"
                        class="h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent"
                    />
                    <template v-else>
                        <span class="font-mono font-bold tabular-nums">{{
                            previewCount ?? 0
                        }}</span>
                        <span class="hidden sm:inline">{{
                            t('component.broadcast.audience.recipients')
                        }}</span>
                        <span class="sm:hidden">{{
                            t('component.broadcast.audience.recipientsShort')
                        }}</span>
                    </template>
                </span>
            </div>

            <!-- Type selector -->
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                <button
                    v-for="opt in options"
                    :key="opt.value"
                    type="button"
                    class="group flex items-center gap-2.5 rounded-xl border p-2.5 text-left transition-all active:scale-[0.98] sm:flex-col sm:items-start sm:gap-2 sm:p-3"
                    :class="
                        modelType === opt.value
                            ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                            : 'border-border hover:border-primary/40 hover:bg-muted/40'
                    "
                    @click="setType(opt.value)"
                >
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition-colors"
                        :class="
                            modelType === opt.value
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        <component :is="iconFor(opt.value)" class="h-4 w-4" />
                    </span>
                    <span
                        class="min-w-0 flex-1 text-[13px] leading-tight font-medium sm:text-sm"
                        >{{ opt.label }}</span
                    >
                    <Check
                        v-if="modelType === opt.value"
                        class="h-4 w-4 shrink-0 text-primary sm:absolute sm:top-2.5 sm:right-2.5"
                    />
                </button>
            </div>

            <!-- Dealer picker (platform) -->
            <div v-if="showDealerPicker && dealers.length" class="space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <Label class="text-xs text-muted-foreground"
                        >{{ t('component.broadcast.audience.dealers') }}
                        <span class="opacity-60">{{
                            t('component.broadcast.audience.emptyAllHint')
                        }}</span></Label
                    >
                    <div class="flex items-center gap-1 text-xs">
                        <button
                            type="button"
                            class="rounded-md px-2 py-0.5 font-medium text-primary hover:bg-primary/10"
                            @click="selectAllDealers"
                        >
                            {{ t('component.broadcast.audience.all') }}
                        </button>
                        <button
                            v-if="selectedDealerIds.length"
                            type="button"
                            class="rounded-md px-2 py-0.5 text-muted-foreground hover:bg-muted"
                            @click="clearDealers"
                        >
                            {{ t('component.broadcast.audience.clear') }}
                        </button>
                    </div>
                </div>

                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="dealerSearch"
                        type="text"
                        :placeholder="
                            t('component.broadcast.audience.searchDealer')
                        "
                        class="w-full rounded-lg border border-input bg-background py-2 pr-3 pl-8 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    />
                </div>

                <div class="max-h-56 overflow-y-auto rounded-lg border">
                    <button
                        v-for="dealer in filteredDealers"
                        :key="dealer.id"
                        type="button"
                        class="flex w-full items-center gap-2.5 border-b px-3 py-2.5 text-left text-sm transition-colors last:border-b-0 hover:bg-muted/50"
                        :class="
                            selectedDealerIds.includes(dealer.id)
                                ? 'bg-primary/5'
                                : ''
                        "
                        @click="toggleDealer(dealer.id)"
                    >
                        <span
                            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border transition-colors"
                            :class="
                                selectedDealerIds.includes(dealer.id)
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-input'
                            "
                        >
                            <Check
                                v-if="selectedDealerIds.includes(dealer.id)"
                                class="h-3 w-3"
                            />
                        </span>
                        <span class="min-w-0 flex-1 truncate">{{
                            dealer.name
                        }}</span>
                    </button>
                    <p
                        v-if="!filteredDealers.length"
                        class="p-4 text-center text-sm text-muted-foreground"
                    >
                        {{ t('component.broadcast.audience.notFound') }}
                    </p>
                </div>
                <p
                    v-if="selectedDealerIds.length"
                    class="text-xs text-muted-foreground"
                >
                    {{
                        t('component.broadcast.audience.dealersSelected', {
                            count: selectedDealerIds.length,
                        })
                    }}
                </p>
            </div>

            <!-- Shop picker (selected_shops) -->
            <div v-if="modelType === 'selected_shops'" class="space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <Label class="text-xs text-muted-foreground">{{
                        t('component.broadcast.audience.selectShops')
                    }}</Label>
                    <div class="flex items-center gap-1 text-xs">
                        <button
                            type="button"
                            class="rounded-md px-2 py-0.5 font-medium text-primary hover:bg-primary/10"
                            @click="selectAllShops"
                        >
                            {{ t('component.broadcast.audience.all') }}
                        </button>
                        <button
                            v-if="selectedShopIds.length"
                            type="button"
                            class="rounded-md px-2 py-0.5 text-muted-foreground hover:bg-muted"
                            @click="clearShops"
                        >
                            {{ t('component.broadcast.audience.clear') }}
                        </button>
                    </div>
                </div>

                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="shopSearch"
                        type="text"
                        :placeholder="
                            t('component.broadcast.audience.searchShop')
                        "
                        class="w-full rounded-lg border border-input bg-background py-2 pr-3 pl-8 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    />
                </div>

                <div class="max-h-64 overflow-y-auto rounded-lg border">
                    <button
                        v-for="shop in filteredShops"
                        :key="shop.id"
                        type="button"
                        class="flex w-full items-center gap-2.5 border-b px-3 py-2.5 text-left transition-colors last:border-b-0 hover:bg-muted/50"
                        :class="
                            selectedShopIds.includes(shop.id)
                                ? 'bg-primary/5'
                                : ''
                        "
                        @click="toggleShop(shop.id)"
                    >
                        <span
                            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border transition-colors"
                            :class="
                                selectedShopIds.includes(shop.id)
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-input'
                            "
                        >
                            <Check
                                v-if="selectedShopIds.includes(shop.id)"
                                class="h-3 w-3"
                            />
                        </span>
                        <span
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[11px] font-semibold text-primary"
                        >
                            {{ shopInitial(shop.name) }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{
                                shop.name
                            }}</span>
                            <span
                                v-if="shop.phone"
                                class="block truncate text-xs text-muted-foreground"
                                >{{ shop.phone }}</span
                            >
                        </span>
                    </button>
                    <p
                        v-if="!filteredShops.length"
                        class="p-4 text-center text-sm text-muted-foreground"
                    >
                        {{ t('component.broadcast.audience.shopNotFound') }}
                    </p>
                </div>
                <p
                    v-if="selectedShopIds.length"
                    class="text-xs text-muted-foreground"
                >
                    {{
                        t('component.broadcast.audience.shopsSelected', {
                            count: selectedShopIds.length,
                        })
                    }}
                </p>
            </div>

            <!-- Filter -->
            <div
                v-if="showFilter"
                class="space-y-3 rounded-xl border border-dashed p-3 sm:p-4"
            >
                <div
                    class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                >
                    <FilterIcon class="h-3.5 w-3.5" />
                    {{ t('component.broadcast.audience.conditions') }}
                </div>

                <!-- Saldo -->
                <div class="space-y-1.5">
                    <Label class="flex items-center gap-1.5 text-xs">
                        <Wallet class="h-3.5 w-3.5 text-muted-foreground" />
                        {{ t('component.broadcast.audience.byBalance') }}
                    </Label>
                    <div class="flex gap-2">
                        <div
                            class="flex shrink-0 overflow-hidden rounded-lg border"
                        >
                            <button
                                v-for="b in balanceOps"
                                :key="b.op"
                                type="button"
                                class="h-9 w-8 text-sm font-medium transition-colors"
                                :class="
                                    modelConfig.balance_op === b.op
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-background text-muted-foreground hover:bg-muted'
                                "
                                @click="
                                    patchConfig({
                                        balance_op:
                                            modelConfig.balance_op === b.op
                                                ? undefined
                                                : b.op,
                                    })
                                "
                            >
                                {{ b.label }}
                            </button>
                        </div>
                        <input
                            type="number"
                            inputmode="numeric"
                            :value="modelConfig.balance_value ?? ''"
                            @input="
                                patchConfig({
                                    balance_value:
                                        Number(
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        ) || undefined,
                                })
                            "
                            class="h-9 min-w-0 flex-1 rounded-lg border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :placeholder="currencySymbol"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <!-- Faolsizlik -->
                    <div class="space-y-1.5">
                        <Label class="flex items-center gap-1.5 text-xs">
                            <CalendarClock
                                class="h-3.5 w-3.5 text-muted-foreground"
                            />
                            {{
                                t(
                                    'component.broadcast.audience.daysWithoutOrder',
                                )
                            }}
                        </Label>
                        <div class="relative">
                            <input
                                type="number"
                                inputmode="numeric"
                                min="1"
                                :value="
                                    modelConfig.min_days_since_last_order ?? ''
                                "
                                @input="
                                    patchConfig({
                                        min_days_since_last_order:
                                            Number(
                                                (
                                                    $event.target as HTMLInputElement
                                                ).value,
                                            ) || undefined,
                                    })
                                "
                                class="h-9 w-full rounded-lg border border-input bg-background px-3 pr-10 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                placeholder="30"
                            />
                            <span
                                class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs text-muted-foreground"
                                >{{
                                    t('component.broadcast.audience.dayUnit')
                                }}</span
                            >
                        </div>
                    </div>

                    <!-- Hudud -->
                    <div class="space-y-1.5">
                        <Label class="text-xs">{{
                            t('component.broadcast.audience.region')
                        }}</Label>
                        <select
                            :value="modelConfig.region ?? ''"
                            @change="
                                patchConfig({
                                    region:
                                        ($event.target as HTMLSelectElement)
                                            .value || undefined,
                                })
                            "
                            class="h-9 w-full rounded-lg border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="">
                                {{ t('component.broadcast.audience.all') }}
                            </option>
                            <option v-for="r in regions" :key="r" :value="r">
                                {{ r }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Kategoriyalar -->
                <div v-if="categories.length" class="space-y-1.5">
                    <Label class="flex items-center gap-1.5 text-xs">
                        <Tag class="h-3.5 w-3.5 text-muted-foreground" />
                        {{ t('component.broadcast.audience.categoryOrdered') }}
                    </Label>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="cat in categories"
                            :key="cat.id"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors"
                            :class="
                                selectedCategoryIds.includes(cat.id)
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-input text-muted-foreground hover:border-primary/40 hover:text-foreground'
                            "
                            @click="toggleCategory(cat.id)"
                        >
                            <Check
                                v-if="selectedCategoryIds.includes(cat.id)"
                                class="h-3 w-3"
                            />
                            {{ cat.name }}
                        </button>
                    </div>
                </div>

                <!-- Faqat qarzdor — toggle -->
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 rounded-lg border px-3 py-2.5 text-left transition-colors hover:bg-muted/40"
                    :class="
                        modelConfig.debtors_only
                            ? 'border-primary/40 bg-primary/5'
                            : 'border-border'
                    "
                    @click="
                        patchConfig({ debtors_only: !modelConfig.debtors_only })
                    "
                >
                    <span class="text-sm font-medium">{{
                        t('component.broadcast.audience.debtorsOnly')
                    }}</span>
                    <span
                        class="relative h-5 w-9 shrink-0 rounded-full transition-colors"
                        :class="
                            modelConfig.debtors_only
                                ? 'bg-primary'
                                : 'bg-muted-foreground/30'
                        "
                    >
                        <span
                            class="absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform"
                            :class="
                                modelConfig.debtors_only ? 'translate-x-4' : ''
                            "
                        />
                    </span>
                </button>
            </div>
        </CardContent>
    </Card>
</template>
