<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, MapPin, Phone, Search, Store, User, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PaginationBar } from '@/components/ui/pagination-bar';
import { SearchableSelect } from '@/components/ui/searchable-select';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useI18n();

type DealerRow = {
    shop_id: number;
    dealer_id: number;
    dealer_name: string;
    dealer_active: boolean;
    shop_name: string;
    phone: string | null;
    contact_person: string | null;
    address: string | null;
    balance: number;
    is_active: boolean;
};

type ShopGroup = {
    key: string;
    inn: string | null;
    name: string;
    phone: string;
    contact_person: string | null;
    region: string | null;
    district: string | null;
    address: string | null;
    dealer_count: number;
    total_balance: number;
    dealers: DealerRow[];
};

type Paginated<T> = {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
};

type Totals = {
    shop_rows: number;
    unique_shops: number;
    shared_inn_groups: number;
    total_balance: number;
    total_debt: number;
    total_credit: number;
};

type DealerBalance = {
    id: number;
    name: string;
    is_active: boolean;
    shops_count: number;
    total_balance: number;
    debt: number;
    credit: number;
};

type DealerOption = { id: number; name: string };

const props = defineProps<{
    groups: Paginated<ShopGroup>;
    totals: Totals;
    dealerBalances: DealerBalance[];
    dealers: DealerOption[];
    filters: { search: string | null; dealer_id: number | null };
}>();

const search = ref(props.filters.search ?? '');
const dealerFilter = ref<number | null>(props.filters.dealer_id);
const expanded = ref<Set<string>>(new Set());

const dealerItems = computed<DealerOption[]>(() => [
    { id: 0, name: t('pageAdmin.shops.filterAllDealers') },
    ...props.dealers,
]);

function formatMoney(n: number): string {
    return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

function applyFilters() {
    router.get('/admin/shops', {
        search: search.value || undefined,
        dealer_id: dealerFilter.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function goToPage(page: number) {
    router.get('/admin/shops', {
        search: search.value || undefined,
        dealer_id: dealerFilter.value || undefined,
        page,
    }, { preserveState: true, preserveScroll: false });
}

function toggle(key: string) {
    if (expanded.value.has(key)) {
        expanded.value.delete(key);
    } else {
        expanded.value.add(key);
    }

    expanded.value = new Set(expanded.value);
}

function balanceClass(balance: number): string {
    if (balance < 0) {
return 'text-rose-600 dark:text-rose-400';
}

    if (balance > 0) {
return 'text-emerald-600 dark:text-emerald-400';
}

    return 'text-muted-foreground';
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageAdmin.shops.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageAdmin.shops.title') }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('pageAdmin.shops.subtitleCount', { count: totals.unique_shops }) }}
                    <span v-if="totals.shared_inn_groups > 0" class="text-amber-600">
                        {{ t('pageAdmin.shops.subtitleShared', { count: totals.shared_inn_groups }) }}
                    </span>
                </p>
            </div>
        </div>

        <!-- KPI -->
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.shops.kpiUniqueShops') }}</p>
                    <p class="text-2xl font-bold">{{ totals.unique_shops }}</p>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.shops.kpiShopRows', { count: totals.shop_rows }) }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.shops.kpiTotalBalance') }}</p>
                    <p class="text-2xl font-bold" :class="balanceClass(totals.total_balance)">
                        {{ formatMoney(totals.total_balance) }}
                    </p>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.shops.currency') }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.shops.kpiDebt') }}</p>
                    <p class="text-2xl font-bold text-rose-600 dark:text-rose-400">
                        {{ formatMoney(totals.total_debt) }}
                    </p>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.shops.kpiDebtSubtitle') }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.shops.kpiCredit') }}</p>
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                        {{ formatMoney(totals.total_credit) }}
                    </p>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.shops.kpiCreditSubtitle') }}</p>
                </CardContent>
            </Card>
        </div>

        <!-- Dillerlar bo'yicha saldo -->
        <Card>
            <CardHeader>
                <CardTitle>{{ t('pageAdmin.shops.dealerBalancesTitle') }}</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead class="bg-muted/50 text-xs uppercase text-muted-foreground">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium">{{ t('pageAdmin.shops.tableDealer') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageAdmin.shops.tableShops') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageAdmin.shops.tableDebt') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageAdmin.shops.tableCredit') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageAdmin.shops.tableTotalBalance') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="d in dealerBalances"
                                :key="d.id"
                                class="border-t cursor-pointer hover:bg-muted/30"
                                @click="dealerFilter = d.id; applyFilters()"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{{ d.name }}</span>
                                        <Badge v-if="!d.is_active" variant="outline" class="text-xs">{{ t('pageAdmin.shops.inactive') }}</Badge>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ d.shops_count }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-rose-600 dark:text-rose-400">
                                    {{ formatMoney(d.debt) }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums text-emerald-600 dark:text-emerald-400">
                                    {{ formatMoney(d.credit) }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums" :class="balanceClass(d.total_balance)">
                                    {{ formatMoney(d.total_balance) }}
                                </td>
                            </tr>
                            <tr v-if="dealerBalances.length === 0">
                                <td colspan="5" class="px-4 py-6 text-center text-muted-foreground">
                                    {{ t('pageAdmin.shops.noDealers') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Filtrlar -->
        <Card>
            <CardContent class="grid grid-cols-1 gap-3 pt-6 sm:grid-cols-2 lg:grid-cols-[1fr_14rem_auto] lg:items-end">
                <div class="relative">
                    <label class="mb-1 block text-sm font-medium">{{ t('pageAdmin.shops.filterSearchLabel') }}</label>
                    <Search class="pointer-events-none absolute left-3 top-[calc(50%+10px)] h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="search"
                        class="pl-10"
                        :placeholder="t('pageAdmin.shops.filterSearchPlaceholder')"
                        @keyup.enter="applyFilters"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ t('pageAdmin.shops.filterDealerLabel') }}</label>
                    <SearchableSelect
                        v-model="dealerFilter"
                        :items="dealerItems"
                        value-key="id"
                        label-key="name"
                        :placeholder="t('pageAdmin.shops.filterDealerPlaceholder')"
                        :search-placeholder="t('pageAdmin.shops.filterDealerSearchPlaceholder')"
                        :empty-text="t('pageAdmin.shops.filterDealerEmpty')"
                        @change="applyFilters"
                    />
                </div>
                <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-1">
                    <Button class="flex-1 sm:flex-initial" @click="applyFilters">{{ t('pageAdmin.shops.search') }}</Button>
                    <Button
                        v-if="search || dealerFilter"
                        variant="outline"
                        class="flex-1 sm:flex-initial"
                        @click="search = ''; dealerFilter = null; applyFilters()"
                    >
                        {{ t('pageAdmin.shops.reset') }}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Mijozlar ro'yxati -->
        <Card>
            <CardContent class="p-0">
                <div v-if="groups.data.length === 0" class="flex flex-col items-center justify-center gap-3 py-16">
                    <Store class="h-12 w-12 text-muted-foreground/40" />
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.shops.empty') }}</p>
                </div>

                <div v-else class="divide-y">
                    <div
                        v-for="g in groups.data"
                        :key="g.key"
                        class="transition-colors"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-muted/30"
                            @click="toggle(g.key)"
                        >
                            <component
                                :is="expanded.has(g.key) ? ChevronDown : ChevronRight"
                                class="h-4 w-4 shrink-0 text-muted-foreground"
                            />
                            <div class="rounded-lg bg-primary/10 p-2 text-primary">
                                <Store class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold">{{ g.name }}</p>
                                    <Badge v-if="g.inn" variant="secondary" class="font-mono text-xs">
                                        {{ t('pageAdmin.shops.innLabel') }} {{ g.inn }}
                                    </Badge>
                                    <Badge v-else variant="outline" class="text-xs">{{ t('pageAdmin.shops.innMissing') }}</Badge>
                                    <Badge v-if="g.dealer_count > 1" class="bg-amber-500/15 text-amber-700 border-amber-500/30 dark:text-amber-300">
                                        <Users class="mr-1 h-3 w-3" />
                                        {{ t('pageAdmin.shops.dealerCountLabel', { n: g.dealer_count }) }}
                                    </Badge>
                                </div>
                                <div class="mt-0.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                    <span class="flex items-center gap-1">
                                        <Phone class="h-3 w-3" />
                                        {{ g.phone }}
                                    </span>
                                    <span v-if="g.contact_person" class="flex items-center gap-1">
                                        <User class="h-3 w-3" />
                                        {{ g.contact_person }}
                                    </span>
                                    <span v-if="g.region || g.district" class="flex items-center gap-1">
                                        <MapPin class="h-3 w-3" />
                                        {{ [g.region, g.district].filter(Boolean).join(', ') }}
                                    </span>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-xs text-muted-foreground">{{ t('pageAdmin.shops.totalBalance') }}</p>
                                <p class="text-sm font-semibold tabular-nums" :class="balanceClass(g.total_balance)">
                                    {{ formatMoney(g.total_balance) }}
                                </p>
                            </div>
                        </button>

                        <div v-if="expanded.has(g.key)" class="border-t bg-muted/20 px-4 py-3">
                            <p v-if="g.address" class="mb-2 text-xs text-muted-foreground">
                                <MapPin class="mr-1 inline h-3 w-3" />
                                {{ g.address }}
                            </p>
                            <div class="overflow-x-auto rounded-md border bg-background">
                                <table class="w-full text-sm">
                                    <thead class="bg-muted/50 text-xs uppercase text-muted-foreground">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-medium">{{ t('pageAdmin.shops.innerTableDealer') }}</th>
                                            <th class="px-3 py-2 text-left font-medium">{{ t('pageAdmin.shops.innerTableShopName') }}</th>
                                            <th class="px-3 py-2 text-center font-medium">{{ t('pageAdmin.shops.innerTableStatus') }}</th>
                                            <th class="px-3 py-2 text-right font-medium">{{ t('pageAdmin.shops.innerTableBalance') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in g.dealers" :key="row.shop_id" class="border-t">
                                            <td class="px-3 py-2">
                                                <div class="flex items-center gap-2">
                                                    <span>{{ row.dealer_name }}</span>
                                                    <Badge v-if="!row.dealer_active" variant="outline" class="text-xs">{{ t('pageAdmin.shops.inactiveDealer') }}</Badge>
                                                </div>
                                            </td>
                                            <td class="px-3 py-2 text-muted-foreground">{{ row.shop_name }}</td>
                                            <td class="px-3 py-2 text-center">
                                                <Badge :variant="row.is_active ? 'default' : 'outline'" class="text-xs">
                                                    {{ row.is_active ? t('pageAdmin.shops.active') : t('pageAdmin.shops.inactive') }}
                                                </Badge>
                                            </td>
                                            <td class="px-3 py-2 text-right font-medium tabular-nums" :class="balanceClass(row.balance)">
                                                {{ formatMoney(row.balance) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <PaginationBar v-if="groups.meta.last_page > 1" :meta="groups.meta" @change="goToPage" />
    </div>
</template>
