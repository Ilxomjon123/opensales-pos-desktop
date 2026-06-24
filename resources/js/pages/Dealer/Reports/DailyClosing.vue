<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Banknote, BookCheck, Download, Package, RefreshCw, RotateCcw, ShoppingCart, Wallet } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/format';

type OrdersBlock = {
    total: number;
    pending: number;
    assembling: number;
    delivering: number;
    delivered: number;
    received: number;
    cancelled: number;
    gross: number;
    discount: number;
    net: number;
};

type PaymentsBlock = {
    credit_cash: number;
    credit_card: number;
    debit: number;
    total_credit: number;
    net_inflow: number;
};

type CourierRow = {
    deliveryman_id: number;
    name: string;
    received_today: number;
    settled_today: number;
    pending_balance: number;
};

type ReturnsBlock = {
    shop_returns_count: number;
    supplier_returns_count: number;
};

type StockBlock = {
    stock_in_count: number;
    stock_out_count: number;
    adjust_count: number;
};

type Meta = {
    date_from: string;
    date_to: string;
    is_single_day: boolean;
};

type Report = {
    meta: Meta;
    orders: OrdersBlock;
    payments: PaymentsBlock;
    courier_cash: CourierRow[];
    returns: ReturnsBlock;
    stock: StockBlock;
};

const props = defineProps<{
    report: Report;
    filters: Meta;
}>();

const { t } = useI18n();

const localFilters = ref({ date_from: props.filters.date_from, date_to: props.filters.date_to });

watch(
    () => props.filters,
    (next) => {
        localFilters.value = { date_from: next.date_from, date_to: next.date_to };
    },
);

function apply() {
    router.get('/dealer/reports/daily-closing', { ...localFilters.value }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function shiftDay(dir: -1 | 1) {
    const base = new Date(localFilters.value.date_from);
    base.setDate(base.getDate() + dir);
    const day = base.toISOString().slice(0, 10);
    localFilters.value.date_from = day;
    localFilters.value.date_to = day;
    apply();
}

function today() {
    const day = new Date().toISOString().slice(0, 10);
    localFilters.value.date_from = day;
    localFilters.value.date_to = day;
    apply();
}

function thisWeek() {
    const now = new Date();
    const day = now.getDay() || 7; // monday=1, sunday=0→7
    const monday = new Date(now);
    monday.setDate(now.getDate() - (day - 1));
    localFilters.value.date_from = monday.toISOString().slice(0, 10);
    localFilters.value.date_to = now.toISOString().slice(0, 10);
    apply();
}

function thisMonth() {
    const now = new Date();
    const first = new Date(now.getFullYear(), now.getMonth(), 1);
    localFilters.value.date_from = first.toISOString().slice(0, 10);
    localFilters.value.date_to = now.toISOString().slice(0, 10);
    apply();
}

function exportCsv() {
    const params = new URLSearchParams();
    params.set('date_from', localFilters.value.date_from);
    params.set('date_to', localFilters.value.date_to);
    window.location.href = `/dealer/reports/daily-closing/export?${params.toString()}`;
}

function printPage() {
    window.print();
}

function formatCompact(n: number): string {
    const abs = Math.abs(n);

    if (abs >= 1_000_000_000) {
        return (n / 1_000_000_000).toFixed(1).replace(/\.0$/, '') + 'mlrd';
    }

    if (abs >= 1_000_000) {
        return (n / 1_000_000).toFixed(1).replace(/\.0$/, '') + 'mln';
    }

    if (abs >= 1_000) {
        return (n / 1_000).toFixed(0) + 'k';
    }

    return String(n);
}

const totalsPending = computed(
    () => props.report.orders.pending + props.report.orders.assembling + props.report.orders.delivering,
);

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.dailyClosing.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-5 sm:p-6 print:p-0">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between print:hidden">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <BookCheck class="h-4 w-4 text-primary" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-lg font-bold tracking-tight sm:text-xl">{{ t('pageDealer.dailyClosing.title') }}</h1>
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.dailyClosing.subtitle') }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" size="sm" @click="printPage">{{ t('pageDealer.dailyClosing.print') }}</Button>
                <Button variant="default" size="sm" @click="exportCsv">
                    <Download class="mr-1.5 h-3.5 w-3.5" />
                    {{ t('pageDealer.dailyClosing.exportCsv') }}
                </Button>
            </div>
        </div>

        <!-- Filtrlar -->
        <Card class="print:hidden">
            <CardContent class="pt-6">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.dailyClosing.dateFrom') }}</label>
                        <Input v-model="localFilters.date_from" type="date" @change="apply()" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-muted-foreground">{{ t('pageDealer.dailyClosing.dateTo') }}</label>
                        <Input v-model="localFilters.date_to" type="date" @change="apply()" />
                    </div>
                    <div class="flex items-center gap-1">
                        <Button variant="outline" size="icon" class="h-9 w-9" @click="shiftDay(-1)">
                            <ArrowLeft class="h-3.5 w-3.5" />
                        </Button>
                        <Button variant="outline" size="sm" class="h-9 px-3" @click="today">
                            <RefreshCw class="mr-1.5 h-3.5 w-3.5" />
                            {{ t('pageDealer.dailyClosing.today') }}
                        </Button>
                        <Button variant="outline" size="icon" class="h-9 w-9" @click="shiftDay(1)">
                            <ArrowRight class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <Button variant="outline" size="sm" class="h-7 px-2 text-xs" @click="thisWeek">{{ t('pageDealer.dailyClosing.thisWeek') }}</Button>
                        <Button variant="outline" size="sm" class="h-7 px-2 text-xs" @click="thisMonth">{{ t('pageDealer.dailyClosing.thisMonth') }}</Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Period header (print uchun) -->
        <div class="hidden print:block">
            <h1 class="text-2xl font-bold">{{ t('pageDealer.dailyClosing.title') }}</h1>
            <p class="text-sm">{{ report.meta.date_from }} — {{ report.meta.date_to }}</p>
        </div>

        <!-- BUYURTMALAR -->
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="flex items-center gap-2 text-sm">
                    <ShoppingCart class="h-3.5 w-3.5 text-primary" />
                    {{ t('pageDealer.dailyClosing.orders') }}
                </CardTitle>
            </CardHeader>
            <CardContent class="grid grid-cols-2 gap-2 pt-0 sm:grid-cols-3 lg:grid-cols-6">
                <div class="rounded-lg border bg-card p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.dailyClosing.totalOrders') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ report.orders.total }}</p>
                </div>
                <div class="rounded-lg border bg-card p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.dailyClosing.delivered') }}</p>
                    <p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-400">{{ report.orders.delivered }}</p>
                </div>
                <div class="rounded-lg border bg-card p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.dailyClosing.openOrders') }}</p>
                    <p class="mt-1 text-xl font-bold text-amber-600">{{ totalsPending }}</p>
                </div>
                <div class="rounded-lg border bg-card p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.dailyClosing.cancelled') }}</p>
                    <p class="mt-1 text-xl font-bold text-rose-600">{{ report.orders.cancelled }}</p>
                </div>
                <div class="rounded-lg border bg-card p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.dailyClosing.gross') }}</p>
                    <p class="mt-1 text-xl font-bold">{{ formatCompact(report.orders.gross) }}</p>
                    <p v-if="report.orders.discount > 0" class="text-[10px] text-rose-600">−{{ formatCompact(report.orders.discount) }}</p>
                </div>
                <div class="rounded-lg border border-emerald-200/60 bg-emerald-50/30 p-3 dark:bg-emerald-950/20">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.dailyClosing.net') }}</p>
                    <p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-400">
                        {{ formatMoney(report.orders.net) }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- TO'LOVLAR -->
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="flex items-center gap-2 text-sm">
                    <Wallet class="h-3.5 w-3.5 text-primary" />
                    {{ t('pageDealer.dailyClosing.payments') }}
                </CardTitle>
            </CardHeader>
            <CardContent class="grid grid-cols-2 gap-2 pt-0 sm:grid-cols-3 lg:grid-cols-5">
                <div class="rounded-lg border bg-card p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.dailyClosing.cash') }}</p>
                    <p class="mt-1 text-lg font-bold">{{ formatMoney(report.payments.credit_cash) }}</p>
                </div>
                <div class="rounded-lg border bg-card p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.dailyClosing.card') }}</p>
                    <p class="mt-1 text-lg font-bold">{{ formatMoney(report.payments.credit_card) }}</p>
                </div>
                <div class="rounded-lg border bg-card p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.dailyClosing.totalCredit') }}</p>
                    <p class="mt-1 text-lg font-bold text-emerald-700 dark:text-emerald-400">
                        {{ formatMoney(report.payments.total_credit) }}
                    </p>
                </div>
                <div class="rounded-lg border bg-card p-3">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.dailyClosing.debit') }}</p>
                    <p class="mt-1 text-lg font-bold text-rose-600">−{{ formatMoney(report.payments.debit) }}</p>
                </div>
                <div class="rounded-lg border border-emerald-200/60 bg-emerald-50/30 p-3 dark:bg-emerald-950/20">
                    <p class="text-xs text-muted-foreground">{{ t('pageDealer.dailyClosing.netInflow') }}</p>
                    <p class="mt-1 text-lg font-bold text-emerald-700 dark:text-emerald-400">
                        {{ formatMoney(report.payments.net_inflow) }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- KURYER NAQDI -->
        <Card v-if="report.courier_cash.length > 0">
            <CardHeader class="pb-2">
                <CardTitle class="flex items-center gap-2 text-sm">
                    <Banknote class="h-3.5 w-3.5 text-primary" />
                    {{ t('pageDealer.dailyClosing.courierCash') }}
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-left text-sm">
                        <thead class="border-b bg-muted/40 text-xs">
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ t('pageDealer.dailyClosing.courierName') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageDealer.dailyClosing.receivedToday') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageDealer.dailyClosing.settledToday') }}</th>
                                <th class="px-4 py-2 text-right font-medium">{{ t('pageDealer.dailyClosing.pendingBalance') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="cc in report.courier_cash" :key="cc.deliveryman_id" class="hover:bg-muted/20">
                                <td class="px-4 py-2 text-sm font-medium">{{ cc.name }}</td>
                                <td class="px-4 py-2 text-right font-mono text-sm">{{ formatMoney(cc.received_today) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-sm">{{ formatMoney(cc.settled_today) }}</td>
                                <td
                                    class="px-4 py-2 text-right font-mono text-sm font-semibold"
                                    :class="cc.pending_balance > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-muted-foreground'"
                                >
                                    {{ formatMoney(cc.pending_balance) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- QAYTARISHLAR + STOK HARAKATI -->
        <div class="grid gap-3 lg:grid-cols-2">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-sm">
                        <RotateCcw class="h-3.5 w-3.5 text-primary" />
                        {{ t('pageDealer.dailyClosing.returns') }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-2 gap-2 pt-0">
                    <div class="rounded-lg border bg-card p-3">
                        <p class="text-xs text-muted-foreground">{{ t('pageDealer.dailyClosing.shopReturns') }}</p>
                        <p class="mt-1 text-xl font-bold">{{ report.returns.shop_returns_count }}</p>
                        <p class="text-[10px] text-muted-foreground">{{ t('pageDealer.dailyClosing.opsSuffix') }}</p>
                    </div>
                    <div class="rounded-lg border bg-card p-3">
                        <p class="text-xs text-muted-foreground">{{ t('pageDealer.dailyClosing.supplierReturns') }}</p>
                        <p class="mt-1 text-xl font-bold">{{ report.returns.supplier_returns_count }}</p>
                        <p class="text-[10px] text-muted-foreground">{{ t('pageDealer.dailyClosing.opsSuffix') }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="flex items-center gap-2 text-sm">
                        <Package class="h-3.5 w-3.5 text-primary" />
                        {{ t('pageDealer.dailyClosing.stockMoves') }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-3 gap-2 pt-0">
                    <div class="rounded-lg border bg-card p-3">
                        <p class="text-xs text-muted-foreground">{{ t('pageDealer.dailyClosing.stockIn') }}</p>
                        <p class="mt-1 text-xl font-bold text-emerald-700 dark:text-emerald-400">{{ report.stock.stock_in_count }}</p>
                    </div>
                    <div class="rounded-lg border bg-card p-3">
                        <p class="text-xs text-muted-foreground">{{ t('pageDealer.dailyClosing.stockOut') }}</p>
                        <p class="mt-1 text-xl font-bold">{{ report.stock.stock_out_count }}</p>
                    </div>
                    <div class="rounded-lg border bg-card p-3">
                        <p class="text-xs text-muted-foreground">{{ t('pageDealer.dailyClosing.stockAdjust') }}</p>
                        <p class="mt-1 text-xl font-bold">{{ report.stock.adjust_count }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Order status detallari -->
        <Card>
            <CardHeader class="pb-2">
                <CardTitle class="text-sm">{{ t('pageDealer.dailyClosing.statusBreakdown') }}</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-wrap items-center gap-2 pt-0 text-xs">
                <Badge variant="outline" class="bg-amber-50 dark:bg-amber-950/20">
                    {{ t('pageDealer.dailyClosing.statusPending') }}: {{ report.orders.pending }}
                </Badge>
                <Badge variant="outline" class="bg-sky-50 dark:bg-sky-950/20">
                    {{ t('pageDealer.dailyClosing.statusAssembling') }}: {{ report.orders.assembling }}
                </Badge>
                <Badge variant="outline" class="bg-indigo-50 dark:bg-indigo-950/20">
                    {{ t('pageDealer.dailyClosing.statusDelivering') }}: {{ report.orders.delivering }}
                </Badge>
                <Badge variant="outline" class="bg-emerald-50 dark:bg-emerald-950/20">
                    {{ t('pageDealer.dailyClosing.statusDelivered') }}: {{ report.orders.delivered }}
                </Badge>
                <Badge variant="outline" class="bg-teal-50 dark:bg-teal-950/20">
                    {{ t('pageDealer.dailyClosing.statusReceived') }}: {{ report.orders.received }}
                </Badge>
                <Badge variant="outline" class="bg-rose-50 dark:bg-rose-950/20">
                    {{ t('pageDealer.dailyClosing.statusCancelled') }}: {{ report.orders.cancelled }}
                </Badge>
            </CardContent>
        </Card>
    </div>
</template>

<style scoped>
@media print {
    :global(aside),
    :global(header),
    :global(nav) {
        display: none !important;
    }
}
</style>
