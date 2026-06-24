<script setup lang="ts">
import { Deferred, Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AddPaymentModal from '@/components/dealer/finance/AddPaymentModal.vue';

const { t } = useI18n();
import PaymentHistoryTable from '@/components/dealer/finance/PaymentHistoryTable.vue';
import ShopBalanceGrid from '@/components/dealer/finance/ShopBalanceGrid.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useTableFilters } from '@/composables/useTableFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Payment, Shop, StatusOption, Paginated, Filters } from '@/types';

const props = defineProps<{
    payments: Paginated<Payment>;
    shops?: { data: Shop[] };
    paymentTypes: StatusOption[];
    filters: Filters;
    sort: { column: string; direction: 'asc' | 'desc' };
}>();

const shopsList = computed<Shop[]>(() => props.shops?.data ?? []);

const { filters, sortColumn, sortDirection, apply, applyDebounced, reset, toggleSort, goToPage, prefetchPage, exportCsv } = useTableFilters<Filters>({
    url: '/dealer/finance',
    exportUrl: '/dealer/finance/export',
    initialFilters: props.filters,
    initialSort: props.sort,
});

const showAdd = ref(false);

function toggleShopFilter(shopId: number) {
    const id = String(shopId);
    filters.value.shop_id = filters.value.shop_id === id ? undefined : id;
    apply();
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.financeIndex.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-bold sm:text-2xl">{{ t('pageDealer.financeIndex.title') }}</h1>
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" class="flex-1 sm:flex-initial" @click="exportCsv">{{ t('pageDealer.financeIndex.exportExcel') }}</Button>
                <Button variant="outline" class="flex-1 sm:flex-initial" @click="router.get('/dealer/finance/aging')">{{ t('pageDealer.financeIndex.aging') }}</Button>
                <AddPaymentModal v-model:open="showAdd" :shops="shopsList" />
            </div>
        </div>

        <Deferred data="shops">
            <template #fallback>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    <div v-for="i in 8" :key="i" class="h-24 animate-pulse rounded-lg border bg-muted/40" />
                </div>
            </template>
            <ShopBalanceGrid
                :shops="shopsList"
                :active-shop-id="filters.shop_id"
                @toggle-shop="toggleShopFilter"
            />
        </Deferred>

        <!-- Filtrlar -->
        <Card>
            <CardContent class="grid grid-cols-1 gap-3 pt-6 sm:grid-cols-2 sm:gap-4 lg:grid-cols-[14rem_10rem_1fr_1fr_auto] lg:items-end">
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ t('pageDealer.financeIndex.shop') }}</label>
                    <SearchableSelect
                        v-model="filters.shop_id"
                        :items="shopsList"
                        value-key="id"
                        label-key="name"
                        :placeholder="shops ? t('pageDealer.financeIndex.all') : t('pageDealer.financeIndex.loading')"
                        :search-placeholder="t('pageDealer.financeIndex.shopPlaceholder')"
                        :empty-text="t('pageDealer.financeIndex.shopEmpty')"
                        :disabled="!shops"
                        @change="apply()"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ t('pageDealer.financeIndex.type') }}</label>
                    <SearchableSelect
                        v-model="filters.type"
                        :items="paymentTypes"
                        value-key="value"
                        label-key="label"
                        :placeholder="t('pageDealer.financeIndex.all')"
                        :empty-text="t('pageDealer.financeIndex.typeEmpty')"
                        @change="apply()"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ t('pageDealer.financeIndex.from') }}</label>
                    <Input type="date" v-model="filters.date_from" @change="applyDebounced()" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ t('pageDealer.financeIndex.to') }}</label>
                    <Input type="date" v-model="filters.date_to" @change="applyDebounced()" />
                </div>
                <Button variant="outline" size="sm" class="w-full sm:col-span-2 sm:w-auto lg:col-span-1" @click="reset">{{ t('pageDealer.financeIndex.clear') }}</Button>
            </CardContent>
        </Card>

        <PaymentHistoryTable
            :payments="payments"
            :sort-column="sortColumn"
            :sort-direction="sortDirection"
            @toggle-sort="toggleSort"
            @page-change="goToPage"
            @page-prefetch="prefetchPage"
        />
    </div>
</template>
