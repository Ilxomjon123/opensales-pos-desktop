<script setup lang="ts">
import { Deferred, Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart3,
    CalendarDays,
    Hourglass,
    Moon,
    Package,
    Store,
    TrendingUp,
    Truck,
    Wallet,
} from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CollapsibleCard from '@/components/dealer/CollapsibleCard.vue';
import OnboardingChecklist from '@/components/dealer/OnboardingChecklist.vue';
import TrialBanner from '@/components/dealer/TrialBanner.vue';
import WelcomeTour from '@/components/dealer/WelcomeTour.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate } from '@/lib/date';

const { t, tm } = useI18n();

type Kpis = {
    total_shops: number;
    active_shops: number;
    total_orders: number;
    pending_orders: number;
    assembling_orders: number;
    delivering_orders: number;
    delivered_orders: number;
    today_orders: number;
    total_products: number;
    low_stock_products: number;
    out_of_stock_products: number;
    negative_stock_products: number;
    dead_stock_count: number;
    inactive_shops_count: number;
    total_turnover: number;
    total_discount: number;
    month_turnover: number;
    month_discount: number;
    month_orders: number;
    shop_debt_total: number;
    shop_credit_total: number;
    shop_balance_net: number;
};

type ChartPoint = { date: string; count: number; total: number };
type MonthPoint = { month: string; revenue: number; discount?: number; orders: number };

type TopShop = { id: number; name: string; orders: number; revenue: number; discount: number };
type TopProduct = { product_id: number; name: string; qty: number; revenue: number };

type DeadStockItem = {
    id: number; name: string; code: string | null;
    stock: number; last_sold_at: string | null; days_since: number | null;
};

type TopDeliveryman = {
    id: number; name: string; orders: number; delivered: number; revenue: number;
};

type InactiveShop = {
    id: number; name: string; last_order_at: string | null; days_since: number | null; balance: number;
};

type CommissionType = 'turnover_percentage' | 'fixed_per_shop' | 'fixed_per_order' | 'fixed_per_deliveryman' | 'fixed_monthly';
type Finance = {
    turnover: number;
    fee_rate: number;
    fee_owed: number;
    total_paid: number;
    balance: number;
    commission_type: CommissionType;
    fixed_commission_amount: number | null;
};

type Onboarding = {
    dealer_id: number;
    steps: {
        bot_connected: boolean;
        notifications_connected: boolean;
        has_category: boolean;
        has_product: boolean;
        has_shop: boolean;
        has_deliveryman: boolean;
    };
    bot_username: string | null;
    connect_url: string | null;
};

type Trial = {
    ends_at: string;
    days_left: number;
    expired: boolean;
};

const props = defineProps<{
    onboarding?: Onboarding | null;
    trial?: Trial | null;
    kpis: Kpis;
    finance: Finance;
    chart?: ChartPoint[];
    topShops?: TopShop[];
    topProducts?: TopProduct[];
    statusBreakdown?: Record<string, number>;
    monthlyRevenue?: MonthPoint[];
    deadStock?: DeadStockItem[];
    topDeliverymen?: TopDeliveryman[];
    inactiveShops?: InactiveShop[];
}>();

function formatMoney(n: number): string {
    return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
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

function formatMonth(ym: string): string {
    if (!ym) {
        return '';
    }

    const [y, m] = ym.split('-');
    const months = tm('pageDealer.stats.monthShort') as unknown as string[];

    return `${months[parseInt(m, 10) - 1]} ${y.slice(2)}`;
}

const maxChartCount = computed(() => Math.max(...(props.chart ?? []).map((p) => p.count), 1));
const maxMonthRevenue = computed(() => Math.max(...(props.monthlyRevenue ?? []).map((p) => p.revenue), 1));

const operationalCount = computed(
    () => props.kpis.pending_orders + props.kpis.assembling_orders + props.kpis.delivering_orders,
);

const commissionDue = computed(() => Math.max(0, -props.finance.balance));
const stockAlertCount = computed(
    () => props.kpis.low_stock_products + props.kpis.out_of_stock_products + props.kpis.negative_stock_products,
);

const statusLabels = computed<Record<string, { label: string; color: string }>>(() => ({
    pending: { label: t('pageDealer.stats.statusPending'), color: 'bg-amber-500' },
    assembling: { label: t('pageDealer.stats.statusAssembling'), color: 'bg-sky-500' },
    delivering: { label: t('pageDealer.stats.statusDelivering'), color: 'bg-indigo-500' },
    delivered: { label: t('pageDealer.stats.statusDelivered'), color: 'bg-emerald-500' },
    received: { label: t('pageDealer.stats.statusReceived'), color: 'bg-teal-500' },
    cancelled: { label: t('pageDealer.stats.statusCancelled'), color: 'bg-rose-500' },
}));

const commissionRateLabel = computed(() => {
    if (props.finance.commission_type === 'fixed_per_shop') {
        return t('pageDealer.stats.commissionRatePerShop');
    }

    if (props.finance.commission_type === 'fixed_per_order') {
        return t('pageDealer.stats.commissionRatePerOrder');
    }

    if (props.finance.commission_type === 'fixed_per_deliveryman') {
        return t('pageDealer.stats.commissionRatePerDeliveryman');
    }

    if (props.finance.commission_type === 'fixed_monthly') {
        return t('pageDealer.stats.commissionRateMonthly');
    }

    return t('pageDealer.stats.commissionRateLabel');
});

const commissionRateValue = computed(() => {
    if (
        props.finance.commission_type === 'fixed_per_shop'
        || props.finance.commission_type === 'fixed_per_order'
        || props.finance.commission_type === 'fixed_per_deliveryman'
        || props.finance.commission_type === 'fixed_monthly'
    ) {
        return `${formatCompact(props.finance.fixed_commission_amount ?? 0)} ${t('pageDealer.stats.soum')}`;
    }

    return `${Number(props.finance.fee_rate).toFixed(2)}%`;
});

// Diagrammalar bo'limi — yuqoridagi stats ikoni bosilsa shu yerga scroll bo'ladi.
const chartsRef = ref<HTMLElement | null>(null);

// Faolsiz mijozlar ro'yxati kartasiga scroll qilish uchun (task 5).
const inactiveShopsRef = ref<HTMLElement | null>(null);

async function scrollTo(target: HTMLElement | null): Promise<void> {
    await nextTick();
    target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.stats.headTitle')" />

    <!-- Yangi diller uchun: bir martalik tanishtiruv + onboarding checklist -->
    <WelcomeTour v-if="props.onboarding" :dealer-id="props.onboarding.dealer_id" />

    <div class="flex flex-col gap-3 p-3 sm:gap-4 sm:p-5">
        <TrialBanner v-if="props.trial" :trial="props.trial" />
        <OnboardingChecklist v-if="props.onboarding" :onboarding="props.onboarding" />

        <!-- Header — stats ikoni bosilsa diagrammalarga scroll bo'ladi -->
        <div class="flex items-center gap-3">
            <button
                type="button"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary transition-colors hover:bg-primary/20"
                :aria-label="t('pageDealer.stats.scrollToCharts')"
                :title="t('pageDealer.stats.scrollToCharts')"
                @click="scrollTo(chartsRef)"
            >
                <BarChart3 class="h-4 w-4" />
            </button>
            <button type="button" class="min-w-0 text-left" @click="scrollTo(chartsRef)">
                <h1 class="text-lg font-bold tracking-tight sm:text-xl">{{ t('pageDealer.stats.title') }}</h1>
                <p class="text-xs text-muted-foreground">{{ t('pageDealer.stats.subtitle') }}</p>
            </button>
        </div>

        <!-- Eng muhim KPI tile'lar (1 qator, 6 ta) -->
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-6">
            <div class="rounded-lg border bg-card p-3">
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                    <span>{{ t('pageDealer.stats.kpiMonthTurnover') }}</span>
                    <TrendingUp class="h-3.5 w-3.5" />
                </div>
                <p class="mt-1 text-base font-bold leading-tight sm:text-lg">{{ formatCompact(kpis.month_turnover) }}</p>
                <p class="text-[11px] text-muted-foreground">{{ kpis.month_orders }} {{ t('pageDealer.stats.kpiOrdersWord') }}</p>
            </div>

            <div class="rounded-lg border bg-card p-3">
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                    <span>{{ t('pageDealer.stats.kpiToday') }}</span>
                    <CalendarDays class="h-3.5 w-3.5" />
                </div>
                <p class="mt-1 text-base font-bold leading-tight sm:text-lg">{{ kpis.today_orders }}</p>
                <p class="text-[11px] text-muted-foreground">{{ t('pageDealer.stats.kpiOrdersWord') }}</p>
            </div>

            <button
                type="button"
                class="rounded-lg border bg-card p-3 text-left transition-colors hover:bg-muted/30"
                :class="operationalCount > 0 ? 'border-amber-300/60' : ''"
                @click="router.get('/dealer/orders', { status: ['pending', 'assembling', 'delivering'] })"
            >
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                    <span>{{ t('pageDealer.stats.kpiOperational') }}</span>
                    <Hourglass class="h-3.5 w-3.5" :class="operationalCount > 0 ? 'text-amber-600' : ''" />
                </div>
                <p class="mt-1 text-base font-bold leading-tight sm:text-lg" :class="operationalCount > 0 ? 'text-amber-600' : ''">
                    {{ operationalCount }}
                </p>
                <p class="text-[11px] text-muted-foreground">
                    {{ kpis.pending_orders }} / {{ kpis.assembling_orders }} / {{ kpis.delivering_orders }}
                </p>
            </button>

            <button
                type="button"
                class="rounded-lg border bg-card p-3 text-left transition-colors hover:bg-muted/30"
                @click="router.get('/dealer/shops-balance')"
            >
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                    <span>{{ t('pageDealer.stats.kpiShopsDebt') }}</span>
                    <Store class="h-3.5 w-3.5" />
                </div>
                <p class="mt-1 text-base font-bold leading-tight text-rose-600 sm:text-lg">
                    {{ formatCompact(kpis.shop_debt_total) }}
                </p>
                <p class="text-[11px] text-muted-foreground">
                    +{{ formatCompact(kpis.shop_credit_total) }} {{ t('pageDealer.stats.kpiPrepaid') }}
                </p>
            </button>

            <div class="rounded-lg border bg-card p-3" :class="commissionDue > 0 ? 'border-rose-300/60' : ''">
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                    <span>{{ t('pageDealer.stats.kpiCommission') }}</span>
                    <Wallet class="h-3.5 w-3.5" :class="commissionDue > 0 ? 'text-rose-600' : ''" />
                </div>
                <p
                    class="mt-1 text-base font-bold leading-tight sm:text-lg"
                    :class="commissionDue > 0 ? 'text-rose-600' : 'text-emerald-600'"
                >
                    {{ commissionDue > 0 ? '−' : '' }}{{ formatCompact(Math.abs(finance.balance)) }}
                </p>
                <p class="text-[11px] text-muted-foreground">{{ commissionRateLabel }}: {{ commissionRateValue }}</p>
            </div>

            <button
                type="button"
                class="rounded-lg border bg-card p-3 text-left transition-colors hover:bg-muted/30"
                @click="router.get('/dealer/shops')"
            >
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                    <span>{{ t('pageDealer.stats.kpiShops') }}</span>
                    <Store class="h-3.5 w-3.5" />
                </div>
                <p class="mt-1 text-base font-bold leading-tight sm:text-lg">{{ kpis.active_shops }}</p>
                <p class="text-[11px] text-muted-foreground">{{ kpis.total_shops }} {{ t('pageDealer.stats.kpiTotalSuffix') }}</p>
            </button>
        </div>

        <!-- Diqqat alertlari (chip qator) — faqat muammo bor bo'lsa ko'rinadi -->
        <div
            v-if="stockAlertCount > 0 || kpis.dead_stock_count > 0 || kpis.inactive_shops_count > 0 || commissionDue > 0"
            class="flex flex-wrap items-center gap-2 rounded-lg border border-amber-200/60 bg-amber-50/50 p-2.5 text-sm dark:border-amber-900/40 dark:bg-amber-950/20"
        >
            <AlertTriangle class="h-4 w-4 shrink-0 text-amber-600" />
            <span class="text-xs font-medium text-amber-700 dark:text-amber-400">{{ t('pageDealer.stats.alertAttention') }}</span>

            <button
                v-if="kpis.out_of_stock_products > 0"
                type="button"
                class="rounded-full border bg-card px-2.5 py-0.5 text-xs hover:border-rose-400"
                @click="router.get('/dealer/products?stock=out')"
            >
                <span class="font-semibold text-rose-600">{{ kpis.out_of_stock_products }}</span>
                <span class="ml-1 text-muted-foreground">{{ t('pageDealer.stats.alertOutOfStock') }}</span>
            </button>

            <button
                v-if="kpis.negative_stock_products > 0"
                type="button"
                class="rounded-full border bg-card px-2.5 py-0.5 text-xs hover:border-rose-400"
                @click="router.get('/dealer/products?stock=negative')"
            >
                <span class="font-semibold text-rose-600">{{ kpis.negative_stock_products }}</span>
                <span class="ml-1 text-muted-foreground">{{ t('pageDealer.stats.alertNegative') }}</span>
            </button>

            <button
                v-if="kpis.low_stock_products > 0"
                type="button"
                class="rounded-full border bg-card px-2.5 py-0.5 text-xs hover:border-amber-400"
                @click="router.get('/dealer/products?stock=low')"
            >
                <span class="font-semibold text-amber-600">{{ kpis.low_stock_products }}</span>
                <span class="ml-1 text-muted-foreground">{{ t('pageDealer.stats.alertLowStock') }}</span>
            </button>

            <span
                v-if="kpis.dead_stock_count > 0"
                class="rounded-full border bg-card px-2.5 py-0.5 text-xs"
            >
                <span class="font-semibold text-muted-foreground">{{ kpis.dead_stock_count }}</span>
                <span class="ml-1 text-muted-foreground">{{ t('pageDealer.stats.alertDeadStock') }}</span>
            </span>

            <button
                v-if="kpis.inactive_shops_count > 0"
                type="button"
                class="rounded-full border bg-card px-2.5 py-0.5 text-xs hover:border-amber-400"
                @click="scrollTo(inactiveShopsRef)"
            >
                <span class="font-semibold text-muted-foreground">{{ kpis.inactive_shops_count }}</span>
                <span class="ml-1 text-muted-foreground">{{ t('pageDealer.stats.alertInactiveShops') }}</span>
            </button>

            <span
                v-if="commissionDue > 0"
                class="rounded-full border bg-card px-2.5 py-0.5 text-xs"
            >
                <span class="font-semibold text-rose-600">{{ formatCompact(commissionDue) }}</span>
                <span class="ml-1 text-muted-foreground">{{ t('pageDealer.stats.alertCommissionDebt') }}</span>
            </span>
        </div>

        <!-- O'lik tovar + faolsiz mijoz — diagrammalar tepasida turadi (task 7) -->
        <div class="grid gap-3 lg:grid-cols-2">
            <Deferred data="deadStock">
                <template #fallback>
                    <Card>
                        <CardHeader class="pb-2"><CardTitle class="flex items-center gap-2 text-sm"><Moon class="h-3.5 w-3.5 text-primary" />{{ t('pageDealer.stats.deadStockTitle') }}</CardTitle></CardHeader>
                        <CardContent class="pt-0"><div class="h-28 animate-pulse rounded bg-muted/50" /></CardContent>
                    </Card>
                </template>
                <CollapsibleCard :title="t('pageDealer.stats.deadStockTitle')">
                    <template #icon><Moon class="h-3.5 w-3.5 text-primary" /></template>
                    <div v-if="(deadStock?.length ?? 0) === 0" class="p-4 text-center text-xs text-muted-foreground">
                        {{ t('pageDealer.stats.deadStockEmpty') }}
                    </div>
                    <table v-else class="w-full text-left text-sm">
                        <tbody class="divide-y">
                            <tr
                                v-for="d in deadStock"
                                :key="d.id"
                                class="cursor-pointer hover:bg-muted/20"
                                @click="router.get(`/dealer/products/${d.id}/edit`)"
                            >
                                <td class="px-3 py-1.5">
                                    <p class="truncate text-sm font-medium">{{ d.name }}</p>
                                    <p class="text-[10px] text-muted-foreground">
                                        <template v-if="d.days_since === null">{{ t('pageDealer.stats.deadStockNever') }}</template>
                                        <template v-else>{{ d.days_since }}{{ t('pageDealer.stats.deadStockDaysSuffix') }}</template>
                                    </p>
                                </td>
                                <td class="px-3 py-1.5 text-right font-mono text-xs text-muted-foreground">{{ d.stock }}</td>
                            </tr>
                        </tbody>
                    </table>
                </CollapsibleCard>
            </Deferred>

            <Deferred data="inactiveShops">
                <template #fallback>
                    <Card>
                        <CardHeader class="pb-2"><CardTitle class="flex items-center gap-2 text-sm"><AlertTriangle class="h-3.5 w-3.5 text-primary" />{{ t('pageDealer.stats.inactiveShopsTitle') }}</CardTitle></CardHeader>
                        <CardContent class="pt-0"><div class="h-28 animate-pulse rounded bg-muted/50" /></CardContent>
                    </Card>
                </template>
                <div ref="inactiveShopsRef" class="scroll-mt-4">
                    <CollapsibleCard :title="t('pageDealer.stats.inactiveShopsTitle')">
                        <template #icon><AlertTriangle class="h-3.5 w-3.5 text-primary" /></template>
                        <div v-if="(inactiveShops?.length ?? 0) === 0" class="p-4 text-center text-xs text-muted-foreground">
                            {{ t('pageDealer.stats.inactiveShopsAllActive') }}
                        </div>
                        <table v-else class="w-full text-left text-sm">
                            <tbody class="divide-y">
                                <tr
                                    v-for="s in inactiveShops"
                                    :key="s.id"
                                    class="cursor-pointer hover:bg-muted/20"
                                    @click="router.get(`/dealer/shops/${s.id}`)"
                                >
                                    <td class="px-3 py-1.5">
                                        <p class="truncate text-sm font-medium">{{ s.name }}</p>
                                        <p class="text-[10px] text-muted-foreground">
                                            <template v-if="s.days_since === null">{{ t('pageDealer.stats.inactiveShopsNever') }}</template>
                                            <template v-else>{{ s.days_since }}{{ t('pageDealer.stats.inactiveShopsDaysSuffix') }}</template>
                                        </p>
                                    </td>
                                    <td
                                        class="px-3 py-1.5 text-right font-mono text-xs"
                                        :class="s.balance < 0 ? 'text-amber-600' : 'text-muted-foreground'"
                                    >
                                        {{ formatCompact(s.balance) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </CollapsibleCard>
                </div>
            </Deferred>
        </div>

        <!-- Diagrammalar — tepadagi stats ikoni bosilsa shu yerga scroll bo'ladi -->
        <div ref="chartsRef" class="flex scroll-mt-4 flex-col gap-3 sm:gap-4">
            <!-- 30 kunlik mini chart + status pills -->
            <div class="grid gap-3 lg:grid-cols-3">
                <Deferred data="chart">
                    <template #fallback>
                        <Card class="lg:col-span-2">
                            <CardHeader class="pb-2"><CardTitle class="text-sm">{{ t('pageDealer.stats.chart30Title') }}</CardTitle></CardHeader>
                            <CardContent class="pt-0"><div class="h-24 animate-pulse rounded bg-muted/50" /></CardContent>
                        </Card>
                    </template>
                    <CollapsibleCard
                        :title="t('pageDealer.stats.chart30TitleFull')"
                        content-class="pt-0"
                        class="lg:col-span-2"
                    >
                        <template #actions>
                            <span class="text-xs font-normal text-muted-foreground">
                                {{ t('pageDealer.stats.chartTotalPrefix') }} {{ (chart ?? []).reduce((sum, p) => sum + p.count, 0) }} {{ t('pageDealer.stats.chartTotalSuffix') }}
                            </span>
                        </template>
                        <div class="flex h-24 gap-[2px]">
                            <div
                                v-for="point in chart"
                                :key="point.date"
                                class="group relative flex flex-1 flex-col justify-end"
                            >
                                <div
                                    class="w-full rounded-t bg-emerald-500 transition-all hover:bg-emerald-500/80"
                                    :style="{ height: `${(point.count / maxChartCount) * 100}%`, minHeight: point.count > 0 ? '3px' : '0' }"
                                />
                                <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1.5 hidden -translate-x-1/2 whitespace-nowrap rounded bg-popover px-2 py-1 text-[11px] shadow-md group-hover:block">
                                    <div class="font-medium">{{ formatDate(point.date) }}</div>
                                    <div>{{ point.count }} {{ t('pageDealer.stats.chartTooltipCount') }} — {{ formatMoney(point.total) }} {{ t('pageDealer.stats.soum') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-1.5 flex justify-between text-[10px] text-muted-foreground">
                            <span>{{ formatDate(chart?.[0]?.date ?? '') }}</span>
                            <span>{{ formatDate(chart?.[(chart?.length ?? 1) - 1]?.date ?? '') }}</span>
                        </div>
                    </CollapsibleCard>
                </Deferred>

                <Deferred data="statusBreakdown">
                    <template #fallback>
                        <Card>
                            <CardHeader class="pb-2"><CardTitle class="text-sm">{{ t('pageDealer.stats.statusTitle') }}</CardTitle></CardHeader>
                            <CardContent class="pt-0"><div class="h-24 animate-pulse rounded bg-muted/50" /></CardContent>
                        </Card>
                    </template>
                    <CollapsibleCard :title="t('pageDealer.stats.statusFull')" content-class="pt-0">
                        <div class="flex flex-col gap-1.5">
                            <div
                                v-for="(count, status) in statusBreakdown"
                                :key="status"
                                class="flex items-center justify-between text-sm"
                            >
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="h-2 w-2 rounded-full" :class="statusLabels[status]?.color ?? 'bg-muted'" />
                                    <span>{{ statusLabels[status]?.label ?? status }}</span>
                                </div>
                                <span class="font-mono text-xs font-semibold">{{ count }}</span>
                            </div>
                        </div>
                    </CollapsibleCard>
                </Deferred>
            </div>

            <!-- Top mijozlar + mahsulotlar -->
            <div class="grid gap-3 lg:grid-cols-2">
                <Deferred data="topShops">
                    <template #fallback>
                        <Card>
                            <CardHeader class="pb-2"><CardTitle class="flex items-center gap-2 text-sm"><Store class="h-3.5 w-3.5 text-primary" />{{ t('pageDealer.stats.topShopsTitle') }}</CardTitle></CardHeader>
                            <CardContent class="pt-0"><div class="h-32 animate-pulse rounded bg-muted/50" /></CardContent>
                        </Card>
                    </template>
                    <CollapsibleCard :title="t('pageDealer.stats.topShopsTitle')">
                        <template #icon><Store class="h-3.5 w-3.5 text-primary" /></template>
                        <table class="w-full text-left text-sm">
                            <tbody class="divide-y">
                                <tr
                                    v-for="(s, i) in topShops"
                                    :key="s.id"
                                    class="cursor-pointer hover:bg-muted/20"
                                    @click="router.get(`/dealer/shops/${s.id}`)"
                                >
                                    <td class="w-8 px-3 py-2 text-xs text-muted-foreground">{{ i + 1 }}</td>
                                    <td class="px-3 py-2">
                                        <div class="truncate text-sm font-medium">{{ s.name }}</div>
                                        <div class="text-[11px] text-muted-foreground">{{ s.orders }} {{ t('pageDealer.stats.ordersWord') }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <div class="font-mono text-sm">{{ formatCompact(s.revenue) }}</div>
                                        <div v-if="s.discount > 0" class="font-mono text-[10px] text-rose-600">
                                            −{{ formatCompact(s.discount) }}
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="(topShops?.length ?? 0) === 0">
                                    <td colspan="3" class="px-3 py-6 text-center text-xs text-muted-foreground">
                                        {{ t('pageDealer.stats.noData') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </CollapsibleCard>
                </Deferred>

                <Deferred data="topProducts">
                    <template #fallback>
                        <Card>
                            <CardHeader class="pb-2"><CardTitle class="flex items-center gap-2 text-sm"><Package class="h-3.5 w-3.5 text-primary" />{{ t('pageDealer.stats.topProductsTitle') }}</CardTitle></CardHeader>
                            <CardContent class="pt-0"><div class="h-32 animate-pulse rounded bg-muted/50" /></CardContent>
                        </Card>
                    </template>
                    <CollapsibleCard :title="t('pageDealer.stats.topProductsTitle')">
                        <template #icon><Package class="h-3.5 w-3.5 text-primary" /></template>
                        <table class="w-full text-left text-sm">
                            <tbody class="divide-y">
                                <tr v-for="(p, i) in topProducts" :key="p.product_id" class="hover:bg-muted/20">
                                    <td class="w-8 px-3 py-2 text-xs text-muted-foreground">{{ i + 1 }}</td>
                                    <td class="px-3 py-2">
                                        <div class="truncate text-sm font-medium">{{ p.name }}</div>
                                        <div class="text-[11px] text-muted-foreground">{{ p.qty }} {{ t('pageDealer.stats.qtyDona') }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono text-sm">{{ formatCompact(p.revenue) }}</td>
                                </tr>
                                <tr v-if="(topProducts?.length ?? 0) === 0">
                                    <td colspan="3" class="px-3 py-6 text-center text-xs text-muted-foreground">
                                        {{ t('pageDealer.stats.noData') }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </CollapsibleCard>
                </Deferred>
            </div>

            <!-- 12 oylik trend -->
            <Deferred data="monthlyRevenue">
                <template #fallback>
                    <Card>
                        <CardHeader class="pb-2"><CardTitle class="text-sm">{{ t('pageDealer.stats.monthlyTitle') }}</CardTitle></CardHeader>
                        <CardContent class="pt-0"><div class="h-28 animate-pulse rounded bg-muted/50" /></CardContent>
                    </Card>
                </template>
                <CollapsibleCard :title="t('pageDealer.stats.monthlyTitle')" content-class="pt-0">
                    <template #actions>
                        <span class="text-xs font-normal text-muted-foreground">
                            {{ t('pageDealer.stats.chartTotalPrefix') }} {{ formatCompact((monthlyRevenue ?? []).reduce((sum, p) => sum + p.revenue, 0)) }} {{ t('pageDealer.stats.soum') }}
                        </span>
                    </template>
                    <div class="flex h-28 gap-1.5">
                        <div
                            v-for="point in monthlyRevenue"
                            :key="point.month"
                            class="group relative flex flex-1 flex-col justify-end"
                        >
                            <div
                                class="w-full rounded-t bg-primary transition-all hover:bg-primary/80"
                                :style="{ height: `${(point.revenue / maxMonthRevenue) * 100}%`, minHeight: point.revenue > 0 ? '3px' : '0' }"
                            />
                            <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1.5 hidden -translate-x-1/2 whitespace-nowrap rounded bg-popover px-2 py-1 text-[11px] shadow-md group-hover:block">
                                <div class="font-medium">{{ formatMonth(point.month) }}</div>
                                <div>{{ formatMoney(point.revenue) }} {{ t('pageDealer.stats.soum') }}</div>
                                <div>{{ point.orders }} {{ t('pageDealer.stats.ordersWord') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-1.5 flex gap-1.5 text-[10px] text-muted-foreground">
                        <span v-for="point in monthlyRevenue" :key="point.month" class="flex-1 text-center">
                            {{ formatMonth(point.month) }}
                        </span>
                    </div>
                </CollapsibleCard>
            </Deferred>

            <!-- Yetkazib beruvchilar samaradorligi (faqat mavjud bo'lsa) -->
            <Deferred data="topDeliverymen">
                <template #fallback>
                    <div class="hidden" />
                </template>
                <CollapsibleCard
                    v-if="(topDeliverymen?.length ?? 0) > 0"
                    :title="t('pageDealer.stats.deliverymenTitle')"
                    content-class="overflow-x-auto p-0"
                >
                    <template #icon><Truck class="h-3.5 w-3.5 text-primary" /></template>
                    <table class="w-full min-w-[420px] text-left text-sm">
                        <thead class="border-b bg-muted/40 text-xs">
                            <tr>
                                <th class="px-3 py-1.5 font-medium">{{ t('pageDealer.stats.deliverymenName') }}</th>
                                <th class="px-3 py-1.5 text-center font-medium">{{ t('pageDealer.stats.deliverymenOrders') }}</th>
                                <th class="px-3 py-1.5 text-center font-medium">{{ t('pageDealer.stats.deliverymenDelivered') }}</th>
                                <th class="px-3 py-1.5 text-right font-medium">{{ t('pageDealer.stats.deliverymenRevenue') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="d in topDeliverymen" :key="d.id" class="hover:bg-muted/20">
                                <td class="px-3 py-1.5 text-sm">{{ d.name }}</td>
                                <td class="px-3 py-1.5 text-center text-sm">{{ d.orders }}</td>
                                <td class="px-3 py-1.5 text-center">
                                    <Badge variant="secondary" class="font-mono text-[10px]">{{ d.delivered }}</Badge>
                                </td>
                                <td class="px-3 py-1.5 text-right font-mono text-sm">{{ formatCompact(d.revenue) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </CollapsibleCard>
            </Deferred>
        </div>
    </div>
</template>
