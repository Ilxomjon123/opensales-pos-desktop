<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PaginationBar } from '@/components/ui/pagination-bar';
import SortHeader from '@/components/ui/sort-header.vue';

const { t } = useI18n();
import { useCurrency } from '@/composables/useCurrency';
import { useTableFilters } from '@/composables/useTableFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/format';
import type { Paginated } from '@/types';

const { formatWithSymbol } = useCurrency();

type ShopBalanceRow = {
    id: number;
    name: string;
    phone: string | null;
    region: string | null;
    is_active: boolean;
    balance: number;
    parent_shop_id: number | null;
    parent_name: string | null;
    is_main_branch: boolean;
    branches_balance_sum: number;
    total_balance_with_branches: number;
};

type Totals = {
    net: number;
    credits: number;
    debits: number;
};

type ShopsBalanceFilters = {
    date?: string;
    search?: string;
};

const props = defineProps<{
    shops: Paginated<ShopBalanceRow>;
    totals: Totals;
    filters: ShopsBalanceFilters;
    sort: { column: string; direction: 'asc' | 'desc' };
}>();

const {
    filters,
    sortColumn,
    sortDirection,
    apply,
    applyDebounced,
    reset,
    toggleSort,
    goToPage,
    prefetchPage,
} = useTableFilters<ShopsBalanceFilters>({
    url: '/dealer/shops-balance',
    initialFilters: props.filters,
    initialSort: props.sort,
    defaultSortColumn: 'name',
});

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.shopsBalance.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <h1 class="text-xl font-bold sm:text-2xl">
                {{ t('pageDealer.shopsBalance.title') }}
            </h1>
            <div class="text-sm text-muted-foreground">
                {{ t('pageDealer.shopsBalance.datePrefix') }}
                <span class="font-medium text-foreground">{{
                    filters.date
                }}</span>
                {{ t('pageDealer.shopsBalance.dateSuffix') }}
            </div>
        </div>

        <!-- Qarzdorlik kanali tablari -->
        <div class="flex gap-1 border-b">
            <span
                class="border-b-2 border-primary px-3 py-2 text-sm font-medium text-primary"
                >{{ t('pageDealer.shopsBalance.tabBotClients') }}</span
            >
            <Link
                href="/dealer/marketplace/finance"
                class="border-b-2 border-transparent px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground"
            >
                Marketplace
            </Link>
        </div>

        <!-- Totals -->
        <div class="grid gap-3 sm:grid-cols-3">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        {{ t('pageDealer.shopsBalance.totalBalance') }}
                    </p>
                    <p
                        class="text-xl font-bold sm:text-2xl"
                        :class="totals.net < 0 ? 'text-destructive' : ''"
                    >
                        {{ formatWithSymbol(totals.net) }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        {{ t('pageDealer.shopsBalance.totalPayments') }}
                    </p>
                    <p class="text-xl font-bold sm:text-2xl">
                        {{ formatWithSymbol(totals.credits) }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        {{ t('pageDealer.shopsBalance.totalDebts') }}
                    </p>
                    <p class="text-xl font-bold sm:text-2xl">
                        {{ formatWithSymbol(totals.debits) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Filters -->
        <Card>
            <CardContent
                class="grid grid-cols-1 gap-3 pt-6 sm:grid-cols-[12rem_1fr_auto] sm:items-end"
            >
                <div>
                    <label class="mb-1 block text-sm font-medium">{{
                        t('pageDealer.shopsBalance.date')
                    }}</label>
                    <Input
                        type="date"
                        v-model="filters.date"
                        @change="apply()"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{
                        t('pageDealer.shopsBalance.search')
                    }}</label>
                    <Input
                        type="text"
                        v-model="filters.search"
                        :placeholder="
                            t('pageDealer.shopsBalance.searchPlaceholder')
                        "
                        @input="applyDebounced()"
                    />
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    class="w-full sm:w-auto"
                    @click="reset"
                    >{{ t('pageDealer.shopsBalance.clear') }}</Button
                >
            </CardContent>
        </Card>

        <!-- Table -->
        <Card>
            <CardHeader>
                <CardTitle>{{
                    t('pageDealer.shopsBalance.shopsListTitle')
                }}</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <!-- Desktop -->
                <div class="hidden md:block">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-4 py-3 font-medium">
                                    <SortHeader
                                        column="name"
                                        :active-column="sortColumn"
                                        :direction="sortDirection"
                                        @toggle="(c) => toggleSort(c)"
                                    >
                                        {{
                                            t(
                                                'pageDealer.shopsBalance.tableShop',
                                            )
                                        }}
                                    </SortHeader>
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    {{
                                        t('pageDealer.shopsBalance.tablePhone')
                                    }}
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    {{
                                        t('pageDealer.shopsBalance.tableRegion')
                                    }}
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    <SortHeader
                                        column="balance"
                                        :active-column="sortColumn"
                                        :direction="sortDirection"
                                        @toggle="(c) => toggleSort(c)"
                                    >
                                        {{
                                            t(
                                                'pageDealer.shopsBalance.tableBalance',
                                            )
                                        }}
                                    </SortHeader>
                                </th>
                                <th
                                    class="w-24 px-4 py-3 text-center font-medium"
                                >
                                    {{
                                        t('pageDealer.shopsBalance.tableStatus')
                                    }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="shop in shops.data"
                                :key="shop.id"
                                class="hover:bg-muted/20"
                            >
                                <td class="px-4 py-3 font-medium">
                                    <div class="flex flex-col">
                                        <span class="flex items-center gap-2">
                                            {{ shop.name }}
                                            <Badge
                                                v-if="shop.parent_shop_id"
                                                variant="outline"
                                                class="text-[10px] font-normal"
                                                >{{
                                                    t(
                                                        'pageDealer.shopsBalance.branch',
                                                    )
                                                }}</Badge
                                            >
                                            <Badge
                                                v-else-if="
                                                    shop.branches_balance_sum !==
                                                        0 ||
                                                    shop.total_balance_with_branches !==
                                                        shop.balance
                                                "
                                                variant="secondary"
                                                class="text-[10px] font-normal"
                                                >{{
                                                    t(
                                                        'pageDealer.shopsBalance.mainBranch',
                                                    )
                                                }}</Badge
                                            >
                                        </span>
                                        <span
                                            v-if="shop.parent_name"
                                            class="text-xs text-muted-foreground"
                                        >
                                            ↳ {{ shop.parent_name }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ shop.phone ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ shop.region ?? '—' }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-mono font-semibold"
                                    :class="
                                        shop.balance < 0
                                            ? 'text-destructive'
                                            : ''
                                    "
                                >
                                    <div>{{ formatMoney(shop.balance) }}</div>
                                    <div
                                        v-if="
                                            shop.is_main_branch &&
                                            shop.total_balance_with_branches !==
                                                shop.balance
                                        "
                                        class="mt-0.5 text-[11px] font-normal"
                                        :class="
                                            shop.total_balance_with_branches < 0
                                                ? 'text-destructive'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{
                                            t(
                                                'pageDealer.shopsBalance.withBranches',
                                            )
                                        }}
                                        {{
                                            formatMoney(
                                                shop.total_balance_with_branches,
                                            )
                                        }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <Badge
                                        :variant="
                                            shop.balance >= 0
                                                ? 'secondary'
                                                : 'destructive'
                                        "
                                    >
                                        {{
                                            shop.balance >= 0
                                                ? t(
                                                      'pageDealer.shopsBalance.positive',
                                                  )
                                                : t(
                                                      'pageDealer.shopsBalance.debt',
                                                  )
                                        }}
                                    </Badge>
                                </td>
                            </tr>
                            <tr v-if="shops.data.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    {{ t('pageDealer.shopsBalance.noShops') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile -->
                <div class="flex flex-col divide-y md:hidden">
                    <div
                        v-for="shop in shops.data"
                        :key="`m-${shop.id}`"
                        class="flex flex-col gap-2 p-4"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <p
                                    class="flex items-center gap-1.5 truncate font-semibold"
                                >
                                    {{ shop.name }}
                                    <Badge
                                        v-if="shop.parent_shop_id"
                                        variant="outline"
                                        class="shrink-0 text-[10px] font-normal"
                                        >{{
                                            t('pageDealer.shopsBalance.branch')
                                        }}</Badge
                                    >
                                    <Badge
                                        v-else-if="
                                            shop.total_balance_with_branches !==
                                            shop.balance
                                        "
                                        variant="secondary"
                                        class="shrink-0 text-[10px] font-normal"
                                        >{{
                                            t(
                                                'pageDealer.shopsBalance.mainBranch',
                                            )
                                        }}</Badge
                                    >
                                </p>
                                <p
                                    v-if="shop.parent_name"
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    ↳ {{ shop.parent_name }}
                                </p>
                                <p
                                    v-if="shop.region"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ shop.region }}
                                </p>
                            </div>
                            <Badge
                                :variant="
                                    shop.balance >= 0
                                        ? 'secondary'
                                        : 'destructive'
                                "
                                class="shrink-0"
                            >
                                {{
                                    shop.balance >= 0
                                        ? t('pageDealer.shopsBalance.positive')
                                        : t('pageDealer.shopsBalance.debt')
                                }}
                            </Badge>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">{{
                                t('pageDealer.shopsBalance.tableBalance')
                            }}</span>
                            <span
                                class="font-mono font-semibold"
                                :class="
                                    shop.balance < 0 ? 'text-destructive' : ''
                                "
                            >
                                {{ formatWithSymbol(shop.balance) }}
                            </span>
                        </div>
                        <div
                            v-if="
                                shop.is_main_branch &&
                                shop.total_balance_with_branches !==
                                    shop.balance
                            "
                            class="flex items-center justify-between text-sm"
                        >
                            <span class="text-muted-foreground">{{
                                t('pageDealer.shopsBalance.withBranchesShort')
                            }}</span>
                            <span
                                class="font-mono font-semibold"
                                :class="
                                    shop.total_balance_with_branches < 0
                                        ? 'text-destructive'
                                        : ''
                                "
                            >
                                {{
                                    formatWithSymbol(
                                        shop.total_balance_with_branches,
                                    )
                                }}
                            </span>
                        </div>
                        <div
                            v-if="shop.phone"
                            class="flex items-center justify-between text-sm"
                        >
                            <span class="text-muted-foreground">{{
                                t('pageDealer.shopsBalance.tablePhone')
                            }}</span>
                            <span>{{ shop.phone }}</span>
                        </div>
                    </div>
                    <div
                        v-if="shops.data.length === 0"
                        class="p-8 text-center text-sm text-muted-foreground"
                    >
                        {{ t('pageDealer.shopsBalance.noShops') }}
                    </div>
                </div>

                <div class="border-t p-4">
                    <PaginationBar
                        :meta="shops.meta"
                        @change="(p) => goToPage(p)"
                        @prefetch="(p) => prefetchPage(p)"
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
