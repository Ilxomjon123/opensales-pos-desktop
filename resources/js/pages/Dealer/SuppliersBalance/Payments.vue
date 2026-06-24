<script setup lang="ts">
import { Deferred, Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AddSupplierPaymentModal from '@/components/dealer/finance/AddSupplierPaymentModal.vue';

const { t } = useI18n();
import SupplierBalanceGrid from '@/components/dealer/finance/SupplierBalanceGrid.vue';
import SupplierPaymentHistoryTable from '@/components/dealer/finance/SupplierPaymentHistoryTable.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useTableFilters } from '@/composables/useTableFilters';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Paginated, SupplierPayment } from '@/types';

type SupplierOption = {
    id: number;
    name: string;
    balance: number;
};

type Filters = {
    supplier_id?: string | number;
    type?: string;
    date_from?: string;
    date_to?: string;
};

const props = defineProps<{
    payments: Paginated<SupplierPayment>;
    suppliers?: { data: SupplierOption[] };
    paymentTypes: { value: string; label: string }[];
    filters: Filters;
    sort: { column: string; direction: 'asc' | 'desc' };
}>();

const suppliersList = computed<SupplierOption[]>(
    () => props.suppliers?.data ?? [],
);

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
    exportCsv,
} = useTableFilters<Filters>({
    url: '/dealer/suppliers-balance/payments',
    exportUrl: '/dealer/suppliers-balance/payments/export',
    initialFilters: props.filters,
    initialSort: props.sort,
});

const showAdd = ref(false);

function toggleSupplierFilter(supplierId: number) {
    const id = String(supplierId);
    filters.value.supplier_id =
        filters.value.supplier_id === id ? undefined : id;
    apply();
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.suppliersBalancePayments.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <h1 class="text-xl font-bold sm:text-2xl">
                {{ t('pageDealer.suppliersBalancePayments.title') }}
            </h1>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    class="flex-1 sm:flex-initial"
                    @click="exportCsv"
                    >{{
                        t('pageDealer.suppliersBalancePayments.exportExcel')
                    }}</Button
                >
                <Button
                    variant="outline"
                    class="flex-1 sm:flex-initial"
                    @click="router.get('/dealer/suppliers-balance')"
                >
                    {{ t('pageDealer.suppliersBalancePayments.balanceLink') }}
                </Button>
                <AddSupplierPaymentModal
                    v-model:open="showAdd"
                    :suppliers="suppliersList"
                />
            </div>
        </div>

        <Deferred data="suppliers">
            <template #fallback>
                <div
                    class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
                >
                    <div
                        v-for="i in 8"
                        :key="i"
                        class="h-24 animate-pulse rounded-lg border bg-muted/40"
                    />
                </div>
            </template>
            <SupplierBalanceGrid
                :suppliers="suppliersList"
                :active-supplier-id="
                    filters.supplier_id !== undefined
                        ? String(filters.supplier_id)
                        : undefined
                "
                @toggle-supplier="toggleSupplierFilter"
            />
        </Deferred>

        <!-- Filtrlar -->
        <Card>
            <CardContent
                class="grid grid-cols-1 gap-3 pt-6 sm:grid-cols-2 sm:gap-4 lg:grid-cols-[14rem_10rem_1fr_1fr_auto] lg:items-end"
            >
                <div>
                    <label class="mb-1 block text-sm font-medium">{{
                        t('pageDealer.suppliersBalancePayments.supplier')
                    }}</label>
                    <SearchableSelect
                        v-model="filters.supplier_id as any"
                        :items="suppliersList"
                        value-key="id"
                        label-key="name"
                        :placeholder="
                            suppliers
                                ? t('pageDealer.suppliersBalancePayments.all')
                                : t(
                                      'pageDealer.suppliersBalancePayments.loading',
                                  )
                        "
                        :search-placeholder="
                            t(
                                'pageDealer.suppliersBalancePayments.supplierSearch',
                            )
                        "
                        :empty-text="
                            t(
                                'pageDealer.suppliersBalancePayments.supplierEmpty',
                            )
                        "
                        :disabled="!suppliers"
                        @change="apply()"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{
                        t('pageDealer.suppliersBalancePayments.type')
                    }}</label>
                    <SearchableSelect
                        v-model="filters.type as any"
                        :items="paymentTypes"
                        value-key="value"
                        label-key="label"
                        :placeholder="
                            t('pageDealer.suppliersBalancePayments.all')
                        "
                        :empty-text="
                            t('pageDealer.suppliersBalancePayments.typeEmpty')
                        "
                        @change="apply()"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{
                        t('pageDealer.suppliersBalancePayments.from')
                    }}</label>
                    <Input
                        type="date"
                        v-model="filters.date_from"
                        @change="applyDebounced()"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{
                        t('pageDealer.suppliersBalancePayments.to')
                    }}</label>
                    <Input
                        type="date"
                        v-model="filters.date_to"
                        @change="applyDebounced()"
                    />
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    class="w-full sm:col-span-2 sm:w-auto lg:col-span-1"
                    @click="reset"
                    >{{
                        t('pageDealer.suppliersBalancePayments.clear')
                    }}</Button
                >
            </CardContent>
        </Card>

        <SupplierPaymentHistoryTable
            :payments="payments"
            :sort-column="sortColumn"
            :sort-direction="sortDirection"
            @toggle-sort="toggleSort"
            @page-change="goToPage"
            @page-prefetch="prefetchPage"
        />
    </div>
</template>
