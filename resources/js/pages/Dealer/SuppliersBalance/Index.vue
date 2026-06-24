<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Receipt } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';

const { t } = useI18n();
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PaginationBar } from '@/components/ui/pagination-bar';
import SortHeader from '@/components/ui/sort-header.vue';
import { useCurrency } from '@/composables/useCurrency';
import { useTableFilters } from '@/composables/useTableFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/format';
import type { Paginated } from '@/types';

const { formatWithSymbol } = useCurrency();

type SupplierBalanceRow = {
    id: number;
    name: string;
    phone: string | null;
    contact_person: string | null;
    is_active: boolean;
    balance: number;
};

type Totals = { net: number; credits: number; debits: number };
type SuppliersBalanceFilters = { date?: string; search?: string };

const props = defineProps<{
    suppliers: Paginated<SupplierBalanceRow>;
    totals: Totals;
    filters: SuppliersBalanceFilters;
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
} = useTableFilters<SuppliersBalanceFilters>({
    url: '/dealer/suppliers-balance',
    initialFilters: props.filters,
    initialSort: props.sort,
    defaultSortColumn: 'name',
});

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.suppliersBalance.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <h1 class="text-xl font-bold sm:text-2xl">
                {{ t('pageDealer.suppliersBalance.title') }}
            </h1>
            <div class="flex items-center gap-3">
                <div class="text-sm text-muted-foreground">
                    {{ t('pageDealer.suppliersBalance.datePrefix') }}
                    <span class="font-medium text-foreground">{{
                        filters.date
                    }}</span>
                    {{ t('pageDealer.suppliersBalance.dateSuffix') }}
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    @click="router.get('/dealer/suppliers-balance/payments')"
                >
                    <Receipt class="mr-1.5 h-4 w-4" />
                    {{ t('pageDealer.suppliersBalance.paymentsHistory') }}
                </Button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        {{ t('pageDealer.suppliersBalance.totalBalance') }}
                    </p>
                    <p
                        class="text-xl font-bold sm:text-2xl"
                        :class="totals.net < 0 ? 'text-destructive' : ''"
                    >
                        {{ formatWithSymbol(totals.net) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ t('pageDealer.suppliersBalance.negativeMeans') }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        {{ t('pageDealer.suppliersBalance.totalSentPayments') }}
                    </p>
                    <p class="text-xl font-bold sm:text-2xl">
                        {{ formatWithSymbol(totals.credits) }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        {{ t('pageDealer.suppliersBalance.totalDebts') }}
                    </p>
                    <p class="text-xl font-bold sm:text-2xl">
                        {{ formatWithSymbol(totals.debits) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardContent
                class="grid grid-cols-1 gap-3 pt-6 sm:grid-cols-[12rem_1fr_auto] sm:items-end"
            >
                <div>
                    <label class="mb-1 block text-sm font-medium">{{
                        t('pageDealer.suppliersBalance.date')
                    }}</label>
                    <Input
                        type="date"
                        v-model="filters.date"
                        @change="apply()"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{
                        t('pageDealer.suppliersBalance.search')
                    }}</label>
                    <Input
                        type="text"
                        v-model="filters.search"
                        :placeholder="
                            t('pageDealer.suppliersBalance.searchPlaceholder')
                        "
                        @input="applyDebounced()"
                    />
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    class="w-full sm:w-auto"
                    @click="reset"
                    >{{ t('pageDealer.suppliersBalance.clear') }}</Button
                >
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>{{
                    t('pageDealer.suppliersBalance.listTitle')
                }}</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
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
                                                'pageDealer.suppliersBalance.tableSupplier',
                                            )
                                        }}
                                    </SortHeader>
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    {{
                                        t(
                                            'pageDealer.suppliersBalance.tablePhone',
                                        )
                                    }}
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    {{
                                        t(
                                            'pageDealer.suppliersBalance.tableContact',
                                        )
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
                                                'pageDealer.suppliersBalance.tableBalance',
                                            )
                                        }}
                                    </SortHeader>
                                </th>
                                <th
                                    class="w-24 px-4 py-3 text-center font-medium"
                                >
                                    {{
                                        t(
                                            'pageDealer.suppliersBalance.tableStatus',
                                        )
                                    }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="s in suppliers.data"
                                :key="s.id"
                                class="cursor-pointer hover:bg-muted/20"
                                @click="router.get(`/dealer/suppliers/${s.id}`)"
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{ s.name }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ s.phone ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ s.contact_person ?? '—' }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-mono font-semibold"
                                    :class="
                                        s.balance < 0
                                            ? 'text-destructive'
                                            : s.balance > 0
                                              ? 'text-emerald-600'
                                              : ''
                                    "
                                >
                                    {{ formatMoney(s.balance) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <Badge
                                        :variant="
                                            s.balance < 0
                                                ? 'destructive'
                                                : 'secondary'
                                        "
                                    >
                                        {{
                                            s.balance < 0
                                                ? t(
                                                      'pageDealer.suppliersBalance.debt',
                                                  )
                                                : s.balance > 0
                                                  ? t(
                                                        'pageDealer.suppliersBalance.overpaid',
                                                    )
                                                  : t(
                                                        'pageDealer.suppliersBalance.zero',
                                                    )
                                        }}
                                    </Badge>
                                </td>
                            </tr>
                            <tr v-if="suppliers.data.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    {{
                                        t(
                                            'pageDealer.suppliersBalance.noSuppliers',
                                        )
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col divide-y md:hidden">
                    <div
                        v-for="s in suppliers.data"
                        :key="`m-${s.id}`"
                        class="flex cursor-pointer flex-col gap-2 p-4 hover:bg-muted/20"
                        @click="router.get(`/dealer/suppliers/${s.id}`)"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold">
                                    {{ s.name }}
                                </p>
                                <p
                                    v-if="s.contact_person"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ s.contact_person }}
                                </p>
                            </div>
                            <Badge
                                :variant="
                                    s.balance < 0 ? 'destructive' : 'secondary'
                                "
                                class="shrink-0"
                            >
                                {{
                                    s.balance < 0
                                        ? t('pageDealer.suppliersBalance.debt')
                                        : s.balance > 0
                                          ? t(
                                                'pageDealer.suppliersBalance.overpaid',
                                            )
                                          : t(
                                                'pageDealer.suppliersBalance.zero',
                                            )
                                }}
                            </Badge>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-muted-foreground">{{
                                t('pageDealer.suppliersBalance.tableBalance')
                            }}</span>
                            <span
                                class="font-mono font-semibold"
                                :class="s.balance < 0 ? 'text-destructive' : ''"
                            >
                                {{ formatWithSymbol(s.balance) }}
                            </span>
                        </div>
                        <div
                            v-if="s.phone"
                            class="flex items-center justify-between text-sm"
                        >
                            <span class="text-muted-foreground">{{
                                t('pageDealer.suppliersBalance.tablePhone')
                            }}</span>
                            <span>{{ s.phone }}</span>
                        </div>
                    </div>
                    <div
                        v-if="suppliers.data.length === 0"
                        class="p-8 text-center text-sm text-muted-foreground"
                    >
                        {{ t('pageDealer.suppliersBalance.noSuppliers') }}
                    </div>
                </div>

                <div class="border-t p-4">
                    <PaginationBar
                        :meta="suppliers.meta"
                        @change="(p) => goToPage(p)"
                        @prefetch="(p) => prefetchPage(p)"
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
