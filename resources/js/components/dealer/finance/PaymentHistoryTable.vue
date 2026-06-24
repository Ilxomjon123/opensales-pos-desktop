<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PaginationBar } from '@/components/ui/pagination-bar';
import SortHeader from '@/components/ui/sort-header.vue';
import { formatDateTime } from '@/lib/date';
import { formatMoney } from '@/lib/format';
import type { Paginated, Payment } from '@/types';

const { t } = useI18n();

defineProps<{
    payments: Paginated<Payment>;
    sortColumn: string;
    sortDirection: 'asc' | 'desc';
}>();

const emit = defineEmits<{
    toggleSort: [column: string];
    pageChange: [page: number];
    pagePrefetch: [page: number];
}>();

</script>

<template>
    <Card>
        <CardHeader><CardTitle>{{ t('pageDealer.finance.paymentsHistory') }}</CardTitle></CardHeader>
        <CardContent class="p-0">
            <!-- Desktop table -->
            <div class="hidden md:block">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="px-4 py-3 font-medium">
                                <SortHeader column="id" :active-column="sortColumn" :direction="sortDirection" @toggle="(c) => emit('toggleSort', c)">#</SortHeader>
                            </th>
                            <th class="px-4 py-3 font-medium">{{ t('pageDealer.finance.customer') }}</th>
                            <th class="px-4 py-3 font-medium">
                                <SortHeader column="type" :active-column="sortColumn" :direction="sortDirection" @toggle="(c) => emit('toggleSort', c)">{{ t('pageDealer.finance.type') }}</SortHeader>
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                <SortHeader column="amount" :active-column="sortColumn" :direction="sortDirection" @toggle="(c) => emit('toggleSort', c)">{{ t('pageDealer.finance.amount') }}</SortHeader>
                            </th>
                            <th class="px-4 py-3 font-medium">{{ t('pageDealer.finance.method') }}</th>
                            <th class="px-4 py-3 font-medium">{{ t('pageDealer.finance.note') }}</th>
                            <th class="px-4 py-3 font-medium">
                                <SortHeader column="created_at" :active-column="sortColumn" :direction="sortDirection" @toggle="(c) => emit('toggleSort', c)">{{ t('pageDealer.finance.date') }}</SortHeader>
                            </th>
                            <th class="w-24 px-4 py-3 text-center font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="p in payments.data" :key="p.id" class="hover:bg-muted/20">
                            <td class="px-4 py-3 font-mono">{{ p.id }}</td>
                            <td class="px-4 py-3">{{ p.shop?.name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <Badge :variant="p.type === 'credit' ? 'default' : 'destructive'">
                                    {{ p.type_label }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">{{ formatMoney(p.amount) }}</td>
                            <td class="px-4 py-3">
                                <Badge v-if="p.type === 'credit'" :variant="p.method === 'card' ? 'default' : 'secondary'">
                                    {{ p.method_label }}
                                </Badge>
                                <span v-else class="text-muted-foreground">—</span>
                                <span v-if="p.cardholder_name" class="ml-1 text-xs text-muted-foreground">
                                    ({{ p.cardholder_name }})
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ p.note ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ formatDateTime(p.created_at) }}</td>
                            <td class="px-4 py-3 text-center">
                                <Button
                                    v-if="p.order_id"
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 text-xs"
                                    @click="router.get(`/dealer/orders/${p.order_id}`)"
                                >
                                    {{ t('pageDealer.finance.order') }}
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="payments.data.length === 0">
                            <td colspan="8" class="px-4 py-8 text-center text-muted-foreground">
                                {{ t('pageDealer.finance.noPayments') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile card list -->
            <div class="flex flex-col divide-y md:hidden">
                <div v-for="p in payments.data" :key="`m-${p.id}`" class="flex flex-col gap-2 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex flex-col">
                            <span class="font-mono text-xs text-muted-foreground">#{{ p.id }}</span>
                            <span class="font-semibold">{{ p.shop?.name ?? '—' }}</span>
                        </div>
                        <Badge :variant="p.type === 'credit' ? 'default' : 'destructive'" class="shrink-0">
                            {{ p.type_label }}
                        </Badge>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">{{ t('pageDealer.finance.amount') }}</span>
                        <span class="font-mono font-semibold">{{ formatMoney(p.amount) }}</span>
                    </div>
                    <div v-if="p.type === 'credit'" class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">{{ t('pageDealer.finance.method') }}</span>
                        <Badge :variant="p.method === 'card' ? 'default' : 'secondary'" class="shrink-0">
                            {{ p.method_label }}
                        </Badge>
                    </div>
                    <div v-if="p.cardholder_name" class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">{{ t('pageDealer.finance.cardholder') }}</span>
                        <span class="text-right">{{ p.cardholder_name }}</span>
                    </div>
                    <div v-if="p.note" class="text-sm text-muted-foreground">
                        {{ p.note }}
                    </div>
                    <div class="flex items-center justify-between text-xs text-muted-foreground">
                        <span>{{ formatDateTime(p.created_at) }}</span>
                        <button
                            v-if="p.order_id"
                            type="button"
                            class="font-medium text-primary"
                            @click="router.get(`/dealer/orders/${p.order_id}`)"
                        >
                            {{ t('pageDealer.finance.order') }}
                        </button>
                    </div>
                </div>
                <div v-if="payments.data.length === 0" class="p-8 text-center text-sm text-muted-foreground">
                    {{ t('pageDealer.finance.noPayments') }}
                </div>
            </div>

            <div class="border-t p-4">
                <PaginationBar
                    :meta="payments.meta"
                    @change="(p) => emit('pageChange', p)"
                    @prefetch="(p) => emit('pagePrefetch', p)"
                />
            </div>
        </CardContent>
    </Card>
</template>
