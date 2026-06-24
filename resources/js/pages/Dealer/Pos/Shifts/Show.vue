<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Banknote, Clock, CreditCard, ReceiptText, Scale, Wallet } from 'lucide-vue-next';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/date';
import { formatMoneySum } from '@/lib/format';

type Shift = {
    id: number;
    status: 'open' | 'closed';
    status_label: string;
    opened_at: string;
    closed_at: string | null;
    opening_cash: number;
    closing_cash: number | null;
    expected_cash: number | null;
    cash_diff: number | null;
    total_sales: number;
    total_cash: number;
    total_card: number;
    total_debt: number;
    sales_count: number;
    opening_note: string | null;
    closing_note: string | null;
    cashier?: { id: number; name: string };
};

defineProps<{
    shift: Shift;
    totals: {
        total_sales: number;
        total_cash: number;
        total_card: number;
        total_debt: number;
        sales_count: number;
        expected_cash: number;
    };
    breakdown: Record<string, number>;
}>();

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="`Smena #${shift.id}`" />

    <div>
        <div class="space-y-4 p-4 sm:p-6">
            <div class="flex items-center gap-2">
                <Link href="/dealer/pos/shifts" class="rounded-md p-2 hover:bg-muted">
                    <ArrowLeft class="h-4 w-4" />
                </Link>
                <h1 class="text-2xl font-semibold">Smena #{{ shift.id }}</h1>
                <span
                    class="ml-2 rounded-full px-2 py-0.5 text-xs"
                    :class="shift.status === 'open' ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-slate-500/15 text-slate-600 dark:text-slate-400'"
                >
                    {{ shift.status_label }}
                </span>
            </div>

            <Card>
                <CardContent class="grid gap-6 p-6 md:grid-cols-3">
                    <div>
                        <div class="text-xs text-muted-foreground">Kassir</div>
                        <div class="font-medium">{{ shift.cashier?.name ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted-foreground">Ochildi</div>
                        <div class="font-medium">{{ formatDateTime(shift.opened_at) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted-foreground">Yopildi</div>
                        <div class="font-medium">{{ shift.closed_at ? formatDateTime(shift.closed_at) : '—' }}</div>
                    </div>
                </CardContent>
            </Card>

            <!-- KPI cards -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent class="flex items-center gap-3 p-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <ReceiptText class="h-5 w-5" />
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">Sotuvlar</div>
                            <div class="text-xl font-semibold">{{ totals.sales_count }}</div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-3 p-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <Banknote class="h-5 w-5" />
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">Naqd</div>
                            <div class="text-xl font-semibold">{{ formatMoneySum(totals.total_cash) }}</div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-3 p-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600 dark:text-blue-400">
                            <CreditCard class="h-5 w-5" />
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">Karta</div>
                            <div class="text-xl font-semibold">{{ formatMoneySum(totals.total_card) }}</div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-3 p-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400">
                            <Wallet class="h-5 w-5" />
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">Qarzga sotuv</div>
                            <div class="text-xl font-semibold">{{ formatMoneySum(totals.total_debt) }}</div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardContent class="space-y-3 p-6 text-sm">
                    <h3 class="text-base font-semibold">Kassa hisobi</h3>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-muted-foreground">Boshlang'ich naqd</span>
                        <strong>{{ formatMoneySum(shift.opening_cash) }}</strong>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-muted-foreground">Tushgan naqd</span>
                        <strong>{{ formatMoneySum(totals.total_cash) }}</strong>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-muted-foreground">Kutilgan naqd</span>
                        <strong>{{ formatMoneySum(totals.expected_cash) }}</strong>
                    </div>
                    <template v-if="shift.status === 'closed'">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Yopilish naqdi (sanagan)</span>
                            <strong>{{ formatMoneySum(shift.closing_cash ?? 0) }}</strong>
                        </div>
                        <div class="flex items-center justify-between rounded-md p-2"
                            :class="(shift.cash_diff ?? 0) < 0 ? 'bg-rose-500/10' : (shift.cash_diff ?? 0) > 0 ? 'bg-emerald-500/10' : 'bg-muted/50'"
                        >
                            <span class="flex items-center gap-1">
                                <Scale class="h-4 w-4" /> Farq
                            </span>
                            <strong :class="(shift.cash_diff ?? 0) < 0 ? 'text-rose-600' : (shift.cash_diff ?? 0) > 0 ? 'text-emerald-600' : ''">
                                {{ formatMoneySum(shift.cash_diff ?? 0) }}
                            </strong>
                        </div>
                    </template>
                </CardContent>
            </Card>

            <Card v-if="shift.opening_note || shift.closing_note">
                <CardContent class="space-y-3 p-6">
                    <div v-if="shift.opening_note">
                        <div class="text-xs text-muted-foreground">Ochilish izohi</div>
                        <div class="text-sm">{{ shift.opening_note }}</div>
                    </div>
                    <div v-if="shift.closing_note">
                        <div class="text-xs text-muted-foreground">Yopilish izohi</div>
                        <div class="text-sm">{{ shift.closing_note }}</div>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="Object.keys(breakdown).length > 0">
                <CardContent class="space-y-2 p-6">
                    <h3 class="text-base font-semibold">To'lov holati bo'yicha</h3>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div v-for="(count, key) in breakdown" :key="key" class="rounded-md border p-3 text-center">
                            <div class="text-xs uppercase text-muted-foreground">{{ key }}</div>
                            <div class="text-lg font-semibold">{{ count }}</div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
