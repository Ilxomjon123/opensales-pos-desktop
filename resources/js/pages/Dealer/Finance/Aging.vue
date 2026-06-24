<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';

const { t } = useI18n();
const { symbol } = useCurrency();
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useCurrency } from '@/composables/useCurrency';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/date';

type Bucket = 'current' | 'warning' | 'late' | 'critical';

type Row = {
    shop_id: number;
    name: string;
    balance: number;
    last_payment_at: string | null;
    days_since: number;
    bucket: Bucket;
};

type Report = {
    totals: { count: number; debt: number };
    buckets: Record<Bucket, { count: number; debt: number }>;
    rows: Row[];
};

defineProps<{ report: Report }>();

const bucketMeta: Record<
    Bucket,
    { label: string; color: string; badge: string }
> = {
    current: {
        label: t('pageDealer.financeAging.bucketCurrent'),
        color: 'bg-emerald-500',
        badge: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    },
    warning: {
        label: t('pageDealer.financeAging.bucketWarning'),
        color: 'bg-amber-500',
        badge: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    },
    late: {
        label: t('pageDealer.financeAging.bucketLate'),
        color: 'bg-orange-500',
        badge: 'bg-orange-500/15 text-orange-700 dark:text-orange-300',
    },
    critical: {
        label: t('pageDealer.financeAging.bucketCritical'),
        color: 'bg-rose-500',
        badge: 'bg-rose-500/15 text-rose-700 dark:text-rose-300',
    },
};

function formatMoney(n: number): string {
    return String(Math.abs(Math.round(n))).replace(
        /\B(?=(\d{3})+(?!\d))/g,
        ' ',
    );
}

const orderedBuckets = computed(
    () => ['current', 'warning', 'late', 'critical'] as Bucket[],
);

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.financeAging.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex items-center gap-3">
            <Button
                variant="ghost"
                size="icon"
                class="shrink-0"
                @click="router.get('/dealer/finance')"
            >
                <ArrowLeft class="h-5 w-5" />
            </Button>
            <div class="min-w-0 flex-1">
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">
                    {{ t('pageDealer.financeAging.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('pageDealer.financeAging.subtitle') }}
                </p>
            </div>
        </div>

        <!-- Umumiy KPI -->
        <div class="grid gap-3 sm:grid-cols-2 sm:gap-4 md:grid-cols-4">
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">
                            {{ t('pageDealer.financeAging.totalDebtors') }}
                        </p>
                        <AlertCircle class="h-4 w-4 text-muted-foreground" />
                    </div>
                    <p class="mt-1 text-3xl font-bold">
                        {{ report.totals.count }}
                    </p>
                </CardContent>
            </Card>
            <Card class="md:col-span-3">
                <CardContent class="pt-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">
                            {{ t('pageDealer.financeAging.totalDebt') }}
                        </p>
                        <Wallet class="h-4 w-4 text-muted-foreground" />
                    </div>
                    <p
                        class="mt-1 text-2xl font-bold text-amber-600 sm:text-3xl"
                    >
                        {{ formatMoney(report.totals.debt) }}
                        <span class="text-base font-normal">{{ symbol }}</span>
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Baketlar -->
        <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-4">
            <Card v-for="b in orderedBuckets" :key="b">
                <CardContent class="pt-6">
                    <div class="flex items-center gap-2">
                        <span
                            class="h-2.5 w-2.5 rounded-full"
                            :class="bucketMeta[b].color"
                        />
                        <p class="text-sm font-medium">
                            {{ bucketMeta[b].label }}
                        </p>
                    </div>
                    <p class="mt-2 text-2xl font-bold">
                        {{ report.buckets[b].count }} ta
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ formatMoney(report.buckets[b].debt) }} {{ symbol }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Ro'yxat -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">{{
                    t('pageDealer.financeAging.listTitle')
                }}</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="report.rows.length === 0" class="p-10 text-center">
                    <p class="text-3xl">🎉</p>
                    <p class="mt-3 text-sm text-muted-foreground">
                        {{ t('pageDealer.financeAging.noDebtors') }}
                    </p>
                </div>
                <div v-else class="hidden overflow-x-auto md:block">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-4 py-3 font-medium">
                                    {{ t('pageDealer.financeAging.tableShop') }}
                                </th>
                                <th class="px-4 py-3 font-medium">
                                    {{
                                        t(
                                            'pageDealer.financeAging.tableLastPayment',
                                        )
                                    }}
                                </th>
                                <th class="px-4 py-3 text-center font-medium">
                                    {{ t('pageDealer.financeAging.tableDays') }}
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    {{ t('pageDealer.financeAging.tableDebt') }}
                                </th>
                                <th class="px-4 py-3 text-center font-medium">
                                    {{
                                        t('pageDealer.financeAging.tableBucket')
                                    }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="r in report.rows"
                                :key="r.shop_id"
                                class="hover:bg-muted/20"
                            >
                                <td class="px-4 py-3">
                                    <button
                                        type="button"
                                        class="font-medium hover:underline"
                                        @click="
                                            router.get(
                                                `/dealer/shops/${r.shop_id}/edit`,
                                            )
                                        "
                                    >
                                        {{ r.name }}
                                    </button>
                                </td>
                                <td
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    <template v-if="r.last_payment_at">{{
                                        formatDateTime(r.last_payment_at)
                                    }}</template>
                                    <template v-else
                                        ><span class="italic">{{
                                            t(
                                                'pageDealer.financeAging.noPayment',
                                            )
                                        }}</span></template
                                    >
                                </td>
                                <td class="px-4 py-3 text-center font-mono">
                                    {{ r.days_since }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-mono text-amber-700 dark:text-amber-400"
                                >
                                    −{{ formatMoney(r.balance) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <Badge
                                        class="font-medium"
                                        :class="bucketMeta[r.bucket].badge"
                                    >
                                        {{ bucketMeta[r.bucket].label }}
                                    </Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="report.rows.length > 0"
                    class="flex flex-col divide-y md:hidden"
                >
                    <button
                        v-for="r in report.rows"
                        :key="`m-${r.shop_id}`"
                        type="button"
                        class="flex flex-col gap-1.5 p-3 text-left hover:bg-muted/20"
                        @click="router.get(`/dealer/shops/${r.shop_id}/edit`)"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="min-w-0 flex-1 truncate font-medium">
                                {{ r.name }}
                            </p>
                            <span
                                class="shrink-0 font-mono text-sm font-semibold text-amber-700 dark:text-amber-400"
                            >
                                −{{ formatMoney(r.balance) }}
                            </span>
                        </div>
                        <div
                            class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                        >
                            <Badge
                                class="font-medium"
                                :class="bucketMeta[r.bucket].badge"
                            >
                                {{ bucketMeta[r.bucket].label }}
                            </Badge>
                            <span class="font-mono"
                                >{{ r.days_since }}
                                {{
                                    t('pageDealer.financeAging.daysSuffix')
                                }}</span
                            >
                            <span v-if="r.last_payment_at">{{
                                formatDateTime(r.last_payment_at)
                            }}</span>
                            <span v-else class="italic">{{
                                t('pageDealer.financeAging.noPayment')
                            }}</span>
                        </div>
                    </button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
