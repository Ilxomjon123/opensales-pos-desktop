<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Receipt, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import SortHeader from '@/components/ui/sort-header.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/date';
import { formatMoney } from '@/lib/format';

const { t } = useI18n();

type Payment = {
    id: number;
    dealer_id: number;
    dealer_name: string;
    amount: number;
    discount: number;
    note: string | null;
    created_at: string | null;
};

type Paginated<T> = {
    data: T[];
    meta: { total: number; last_page: number; current_page: number; per_page: number };
    links: { prev: string | null; next: string | null };
};

type DealerOption = { id: number; name: string };

type Filters = {
    dealer_id: number | null;
    date_from: string | null;
    date_to: string | null;
    sort: 'created_at' | 'amount' | 'dealer_name';
    direction: 'asc' | 'desc';
};

const props = defineProps<{
    payments: Paginated<Payment>;
    dealers: DealerOption[];
    totals: { count: number; sum: number; discount: number };
    filters: Filters;
}>();

const dealerItems = computed(() =>
    props.dealers.map(d => ({ value: d.id, label: d.name })),
);

const local = ref({
    dealer_id: props.filters.dealer_id,
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
});

watch(() => props.filters, (next) => {
    local.value.dealer_id = next.dealer_id;
    local.value.date_from = next.date_from ?? '';
    local.value.date_to = next.date_to ?? '';
});

function buildQuery(overrides: Partial<Filters> = {}): Record<string, string | number> {
    const merged = {
        dealer_id: local.value.dealer_id,
        date_from: local.value.date_from || null,
        date_to: local.value.date_to || null,
        sort: props.filters.sort,
        direction: props.filters.direction,
        ...overrides,
    };
    const out: Record<string, string | number> = {};

    if (merged.dealer_id) {
out.dealer_id = merged.dealer_id;
}

    if (merged.date_from) {
out.date_from = merged.date_from;
}

    if (merged.date_to) {
out.date_to = merged.date_to;
}

    if (merged.sort && merged.sort !== 'created_at') {
out.sort = merged.sort;
}

    if (merged.direction && merged.direction !== 'desc') {
out.direction = merged.direction;
}

    return out;
}

function apply() {
    router.get('/admin/platform-payments', buildQuery(), {
        preserveState: true,
        preserveScroll: true,
    });
}

function reset() {
    local.value = { dealer_id: null, date_from: '', date_to: '' };
    router.get('/admin/platform-payments', {}, { preserveState: true, preserveScroll: true });
}

function toggleSort(column: string) {
    const isActive = props.filters.sort === column;
    const nextDirection = isActive && props.filters.direction === 'desc' ? 'asc' : 'desc';
    router.get('/admin/platform-payments', buildQuery({
        sort: column as Filters['sort'],
        direction: nextDirection,
    }), {
        preserveState: true,
        preserveScroll: true,
    });
}

function destroy(payment: Payment) {
    if (!confirm(t('pageAdmin.payments.deleteConfirm', { id: payment.id, amount: formatMoney(payment.amount) }))) {
        return;
    }

    router.delete(`/admin/platform-payments/${payment.id}`, {
        preserveScroll: true,
    });
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageAdmin.payments.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                <Receipt class="h-5 w-5 text-primary" />
            </div>
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageAdmin.payments.title') }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('pageAdmin.payments.subtitle') }}</p>
            </div>
        </div>

        <!-- Totals -->
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.payments.totalsCount') }}</p>
                    <p class="mt-1 text-2xl font-bold">{{ totals.count }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.payments.totalsSum') }}</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-600">{{ formatMoney(totals.sum) }}</p>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.payments.currency') }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">{{ t('pageAdmin.payments.totalsDiscount') }}</p>
                    <p class="mt-1 text-2xl font-bold text-amber-600">{{ formatMoney(totals.discount) }}</p>
                    <p class="text-xs text-muted-foreground">{{ t('pageAdmin.payments.currency') }}</p>
                </CardContent>
            </Card>
        </div>

        <!-- Filtr -->
        <Card>
            <CardContent class="grid grid-cols-1 gap-3 pt-6 sm:grid-cols-2 lg:grid-cols-[16rem_1fr_1fr_auto] lg:items-end">
                <div>
                    <Label class="mb-1 block text-xs">{{ t('pageAdmin.payments.filterDealer') }}</Label>
                    <SearchableSelect
                        v-model="local.dealer_id"
                        :items="dealerItems"
                        :placeholder="t('pageAdmin.payments.filterDealerPlaceholder')"
                        :search-placeholder="t('pageAdmin.payments.filterDealerSearchPlaceholder')"
                        :empty-text="t('pageAdmin.payments.filterDealerEmpty')"
                    />
                </div>
                <div>
                    <Label class="mb-1 block text-xs">{{ t('pageAdmin.payments.filterDateFrom') }}</Label>
                    <Input v-model="local.date_from" type="date" />
                </div>
                <div>
                    <Label class="mb-1 block text-xs">{{ t('pageAdmin.payments.filterDateTo') }}</Label>
                    <Input v-model="local.date_to" type="date" />
                </div>
                <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-1">
                    <Button class="flex-1 sm:flex-initial" @click="apply">{{ t('pageAdmin.payments.apply') }}</Button>
                    <Button variant="outline" class="flex-1 sm:flex-initial" @click="reset">{{ t('pageAdmin.payments.reset') }}</Button>
                </div>
            </CardContent>
        </Card>

        <!-- Jadval -->
        <Card>
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="px-4 py-3 font-medium">
                                <SortHeader
                                    column="created_at"
                                    :active-column="filters.sort"
                                    :direction="filters.direction"
                                    @toggle="toggleSort"
                                >
                                    {{ t('pageAdmin.payments.tableDate') }}
                                </SortHeader>
                            </th>
                            <th class="px-4 py-3 font-medium">
                                <SortHeader
                                    column="dealer_name"
                                    :active-column="filters.sort"
                                    :direction="filters.direction"
                                    @toggle="toggleSort"
                                >
                                    {{ t('pageAdmin.payments.tableDealer') }}
                                </SortHeader>
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                <SortHeader
                                    column="amount"
                                    :active-column="filters.sort"
                                    :direction="filters.direction"
                                    align="right"
                                    @toggle="toggleSort"
                                >
                                    {{ t('pageAdmin.payments.tableAmount') }}
                                </SortHeader>
                            </th>
                            <th class="px-4 py-3 text-right font-medium">{{ t('pageAdmin.payments.tableDiscount') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('pageAdmin.payments.tableNote') }}</th>
                            <th class="w-12 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="p in payments.data" :key="p.id" class="hover:bg-muted/20">
                            <td class="px-4 py-3 font-mono text-xs text-muted-foreground">
                                {{ formatDateTime(p.created_at) }}
                            </td>
                            <td class="px-4 py-3">
                                <button
                                    class="text-left font-medium hover:underline"
                                    @click="router.get(`/admin/billing/${p.dealer_id}`)"
                                >
                                    {{ p.dealer_name }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-semibold text-emerald-600">
                                {{ formatMoney(p.amount) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-amber-600">
                                <span v-if="p.discount > 0">{{ formatMoney(p.discount) }}</span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-muted-foreground">{{ p.note ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    class="text-muted-foreground hover:text-rose-600"
                                    :title="t('pageAdmin.payments.deleteTitle')"
                                    @click="destroy(p)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="payments.data.length === 0" class="p-8 text-center text-sm text-muted-foreground">
                    {{ t('pageAdmin.payments.empty') }}
                </div>

                <div v-if="payments.meta.last_page > 1" class="flex items-center justify-between border-t px-4 py-3 text-xs">
                    <span class="text-muted-foreground">
                        {{ t('pageAdmin.payments.paginationSummary', { current: payments.meta.current_page, last: payments.meta.last_page, total: payments.meta.total }) }}
                    </span>
                    <div class="flex gap-2">
                        <Button size="sm" variant="outline" :disabled="!payments.links.prev" @click="payments.links.prev && router.get(payments.links.prev)">
                            {{ t('pageAdmin.payments.previous') }}
                        </Button>
                        <Button size="sm" variant="outline" :disabled="!payments.links.next" @click="payments.links.next && router.get(payments.links.next)">
                            {{ t('pageAdmin.payments.next') }}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
