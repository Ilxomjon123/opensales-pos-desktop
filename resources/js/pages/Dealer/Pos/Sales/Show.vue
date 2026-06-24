<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Banknote, CreditCard, Printer, Receipt, ShoppingBag, User } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/date';
import { formatMoney, formatMoneySum } from '@/lib/format';

type Item = {
    id: number;
    product_id: number;
    product_name: string;
    product_type_name: string | null;
    qty: number;
    price: number;
    pack_qty: number | null;
    pack_price: number | null;
    unit: string;
    subtotal: number;
};

type Sale = {
    id: number;
    receipt_number: string | null;
    payment_status: string | null;
    payment_status_label: string | null;
    total: number;
    discount: number;
    paid_amount: number;
    paid_cash: number;
    paid_card: number;
    debt_amount: number;
    note: string | null;
    created_at: string | null;
    shop?: { id: number; name: string; phone: string | null; type: string | null } | null;
    cashier?: { id: number; name: string } | null;
    shift?: { id: number; opened_at: string | null } | null;
    items?: Item[];
};

defineProps<{ sale: Sale }>();

function printReceipt() {
    window.print();
}

const statusStyles: Record<string, string> = {
    paid: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    partial: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    debt: 'bg-rose-500/15 text-rose-600 dark:text-rose-400',
    unpaid: 'bg-slate-500/15 text-slate-600 dark:text-slate-400',
};

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="`Chek ${sale.receipt_number ?? sale.id}`" />

    <div>
        <div class="space-y-4 p-4 sm:p-6 print:p-0">
            <div class="flex flex-wrap items-center gap-2 print:hidden">
                <Link href="/dealer/pos/sales" class="rounded-md p-2 hover:bg-muted">
                    <ArrowLeft class="h-4 w-4" />
                </Link>
                <h1 class="flex-1 text-2xl font-semibold">Chek {{ sale.receipt_number }}</h1>
                <Link
                    href="/dealer/pos"
                    class="inline-flex items-center gap-1.5 rounded-md border bg-card px-3 py-2 text-sm font-medium hover:bg-muted"
                >
                    <ShoppingBag class="h-4 w-4" /> Yangi sotuv
                </Link>
                <Button @click="printReceipt">
                    <Printer class="mr-1 h-4 w-4" /> Chiqarish
                </Button>
            </div>

            <div class="mx-auto max-w-2xl space-y-4">
                <Card>
                    <CardContent class="p-6">
                        <div class="text-center">
                            <div class="mx-auto mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <Receipt class="h-6 w-6" />
                            </div>
                            <div class="text-xl font-semibold">Chek #{{ sale.receipt_number }}</div>
                            <div class="text-xs text-muted-foreground">{{ sale.created_at ? formatDateTime(sale.created_at) : '' }}</div>
                            <span
                                v-if="sale.payment_status"
                                class="mt-2 inline-flex rounded-full px-2 py-0.5 text-xs"
                                :class="statusStyles[sale.payment_status] ?? ''"
                            >
                                {{ sale.payment_status_label }}
                            </span>
                        </div>

                        <div class="mt-4 grid gap-3 border-y py-4 text-sm sm:grid-cols-2">
                            <div class="flex items-center gap-2">
                                <User class="h-4 w-4 text-muted-foreground" />
                                <div>
                                    <div class="text-xs text-muted-foreground">Mijoz</div>
                                    <div>{{ sale.shop?.name ?? '—' }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <User class="h-4 w-4 text-muted-foreground" />
                                <div>
                                    <div class="text-xs text-muted-foreground">Kassir</div>
                                    <div>{{ sale.cashier?.name ?? '—' }}</div>
                                </div>
                            </div>
                        </div>

                        <table class="mt-3 w-full text-sm">
                            <thead class="border-b text-left text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th class="py-2">Mahsulot</th>
                                    <th class="py-2 text-right">Miqdor</th>
                                    <th class="py-2 text-right">Narx</th>
                                    <th class="py-2 text-right">Jami</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="item in sale.items" :key="item.id">
                                    <td class="py-2">
                                        <div>{{ item.product_name }}</div>
                                        <div v-if="item.product_type_name" class="text-xs text-muted-foreground">{{ item.product_type_name }}</div>
                                    </td>
                                    <td class="py-2 text-right">{{ Number(item.qty).toFixed(item.qty % 1 === 0 ? 0 : 3) }} {{ item.unit }}</td>
                                    <td class="py-2 text-right">{{ formatMoney(item.price) }}</td>
                                    <td class="py-2 text-right font-medium">{{ formatMoneySum(item.subtotal ?? Math.round(item.qty * item.price)) }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="mt-4 space-y-2 border-t pt-3 text-sm">
                            <div v-if="sale.discount > 0" class="flex justify-between text-muted-foreground">
                                <span>Chegirma</span>
                                <span>− {{ formatMoneySum(sale.discount) }}</span>
                            </div>
                            <div class="flex justify-between text-base font-semibold">
                                <span>Jami</span>
                                <span>{{ formatMoneySum(sale.total) }}</span>
                            </div>
                            <div v-if="sale.paid_cash > 0" class="flex items-center justify-between">
                                <span class="flex items-center gap-1 text-muted-foreground"><Banknote class="h-3 w-3" /> Naqd</span>
                                <span>{{ formatMoneySum(sale.paid_cash) }}</span>
                            </div>
                            <div v-if="sale.paid_card > 0" class="flex items-center justify-between">
                                <span class="flex items-center gap-1 text-muted-foreground"><CreditCard class="h-3 w-3" /> Karta</span>
                                <span>{{ formatMoneySum(sale.paid_card) }}</span>
                            </div>
                            <div v-if="sale.debt_amount > 0" class="flex items-center justify-between font-semibold text-rose-600 dark:text-rose-400">
                                <span>Qarz</span>
                                <span>{{ formatMoneySum(sale.debt_amount) }}</span>
                            </div>
                        </div>

                        <div v-if="sale.note" class="mt-3 rounded-md bg-muted/50 p-2 text-xs text-muted-foreground">
                            {{ sale.note }}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>

<style>
@media print {
    body {
        background: white;
    }
    [data-sidebar],
    nav,
    header {
        display: none !important;
    }
}
</style>
