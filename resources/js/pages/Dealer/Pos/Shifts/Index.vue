<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Clock, LogIn, LogOut } from 'lucide-vue-next';
import { Card, CardContent } from '@/components/ui/card';
import { PaginationBar } from '@/components/ui/pagination-bar';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/date';
import { formatMoneySum } from '@/lib/format';
import type { Paginated } from '@/types';

type ShiftRow = {
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
    cashier?: { id: number; name: string };
};

defineProps<{
    shifts: Paginated<ShiftRow>;
    activeShift: ShiftRow | null;
}>();

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="POS smenalar" />

    <div>
        <div class="space-y-4 p-4 sm:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Smenalar</h1>
                    <p class="text-sm text-muted-foreground">Kassir smena tarixi va hisobotlari</p>
                </div>
                <Link
                    href="/dealer/pos"
                    class="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    <LogIn class="h-4 w-4" /> Terminal
                </Link>
            </div>

            <Card v-if="activeShift" class="border-emerald-500/30 bg-emerald-500/5">
                <CardContent class="p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">
                                <Clock class="h-5 w-5" />
                            </div>
                            <div>
                                <div class="font-medium">Aktiv smena #{{ activeShift.id }}</div>
                                <div class="text-xs text-muted-foreground">{{ formatDateTime(activeShift.opened_at) }}</div>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-4 text-sm">
                            <div><span class="text-muted-foreground">Sotuv:</span> <strong>{{ activeShift.sales_count }}</strong></div>
                            <div><span class="text-muted-foreground">Naqd:</span> <strong>{{ formatMoneySum(activeShift.total_cash) }}</strong></div>
                            <div><span class="text-muted-foreground">Karta:</span> <strong>{{ formatMoneySum(activeShift.total_card) }}</strong></div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/30 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3">Kassir</th>
                                    <th class="px-4 py-3">Holat</th>
                                    <th class="px-4 py-3">Ochilish</th>
                                    <th class="px-4 py-3">Yopilish</th>
                                    <th class="px-4 py-3 text-right">Sotuv</th>
                                    <th class="px-4 py-3 text-right">Naqd</th>
                                    <th class="px-4 py-3 text-right">Karta</th>
                                    <th class="px-4 py-3 text-right">Farq</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-for="row in shifts.data"
                                    :key="row.id"
                                    class="hover:bg-muted/40"
                                >
                                    <td class="px-4 py-3">
                                        <Link :href="`/dealer/pos/shifts/${row.id}`" class="font-medium text-primary hover:underline">#{{ row.id }}</Link>
                                    </td>
                                    <td class="px-4 py-3">{{ row.cashier?.name ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs"
                                            :class="row.status === 'open' ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-slate-500/15 text-slate-600 dark:text-slate-400'"
                                        >
                                            <Clock v-if="row.status === 'open'" class="h-3 w-3" />
                                            <LogOut v-else class="h-3 w-3" />
                                            {{ row.status_label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-xs">{{ formatDateTime(row.opened_at) }}</td>
                                    <td class="px-4 py-3 text-xs">{{ row.closed_at ? formatDateTime(row.closed_at) : '—' }}</td>
                                    <td class="px-4 py-3 text-right">{{ row.sales_count }} · {{ formatMoneySum(row.total_sales) }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatMoneySum(row.total_cash) }}</td>
                                    <td class="px-4 py-3 text-right">{{ formatMoneySum(row.total_card) }}</td>
                                    <td class="px-4 py-3 text-right" :class="(row.cash_diff ?? 0) < 0 ? 'text-rose-500' : (row.cash_diff ?? 0) > 0 ? 'text-emerald-500' : ''">
                                        {{ row.cash_diff !== null ? formatMoneySum(row.cash_diff) : '—' }}
                                    </td>
                                </tr>
                                <tr v-if="shifts.data.length === 0">
                                    <td colspan="9" class="px-4 py-12 text-center text-muted-foreground">Smena yo'q</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <PaginationBar :links="shifts.links" :meta="shifts.meta" />
        </div>
    </div>
</template>
