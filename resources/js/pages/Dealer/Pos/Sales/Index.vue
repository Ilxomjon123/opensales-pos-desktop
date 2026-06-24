<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown, Receipt, Search, ShoppingBag } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PaginationBar } from '@/components/ui/pagination-bar';
import { SearchableSelect } from '@/components/ui/searchable-select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/date';
import { formatMoneySum } from '@/lib/format';
import type { Paginated } from '@/types';

type SaleItem = {
    id: number;
    display_name: string;
    product_name: string;
    product_type_name: string | null;
    qty: number;
    price: number;
    unit: string | null;
    subtotal: number;
};

type Sale = {
    id: number;
    receipt_number: string | null;
    payment_status: string | null;
    payment_status_label: string | null;
    total: number;
    paid_amount: number;
    debt_amount: number;
    paid_cash: number;
    paid_card: number;
    created_at: string | null;
    cashier?: { id: number; name: string } | null;
    shop?: { id: number; name: string } | null;
    items?: SaleItem[];
};

const props = defineProps<{
    sales: Paginated<Sale>;
    products: { id: number; name: string }[];
    paymentStatuses: { value: string; label: string }[];
    filters: {
        search: string | null;
        payment_status: string | null;
        shift_id: string | null;
        product_id: number | null;
        date_from: string | null;
        date_to: string | null;
    };
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.payment_status ?? '');
const productId = ref<number | null>(props.filters.product_id ?? null);
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

const productItems = computed(() => props.products.map((p) => ({ value: p.id, label: p.name })));

// Qaysi sotuvlar yoyilgan (tovarlar ko'rsatilgan) — id'lar to'plami.
const expanded = ref<Set<number>>(new Set());

function toggleExpand(id: number): void {
    const next = new Set(expanded.value);
    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }
    expanded.value = next;
}

let timer: ReturnType<typeof setTimeout> | null = null;

watch([search, status, productId, dateFrom, dateTo], () => {
    if (timer) {
        clearTimeout(timer);
    }
    timer = setTimeout(() => {
        router.get(
            '/dealer/pos/sales',
            {
                search: search.value || undefined,
                payment_status: status.value || undefined,
                product_id: productId.value ?? undefined,
                date_from: dateFrom.value || undefined,
                date_to: dateTo.value || undefined,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }, 300);
});

onBeforeUnmount(() => {
    if (timer) {
        clearTimeout(timer);
    }
});

const statusStyles: Record<string, string> = {
    paid: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    partial: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    debt: 'bg-rose-500/15 text-rose-600 dark:text-rose-400',
    unpaid: 'bg-slate-500/15 text-slate-600 dark:text-slate-400',
};

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="POS sotuvlar" />

    <div>
        <div class="space-y-4 p-4 sm:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">POS sotuvlar</h1>
                    <p class="text-sm text-muted-foreground">Kassada qabul qilingan sotuvlar tarixi</p>
                </div>
                <Link
                    href="/dealer/pos"
                    class="inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    <ShoppingBag class="h-4 w-4" /> Terminal
                </Link>
            </div>

            <Card>
                <CardContent class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <div class="relative flex-1">
                        <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" placeholder="Chek raqami yoki mijoz nomi…" class="pl-9" />
                    </div>
                    <div class="w-full sm:w-64">
                        <SearchableSelect
                            v-model="productId"
                            :items="productItems"
                            placeholder="Tovar bo'yicha filtr"
                            search-placeholder="Tovar nomi…"
                            empty-text="Tovar topilmadi"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <Input v-model="dateFrom" type="date" class="w-full sm:w-40" aria-label="Sanadan" />
                        <span class="text-muted-foreground">—</span>
                        <Input v-model="dateTo" type="date" class="w-full sm:w-40" aria-label="Sanagacha" />
                    </div>
                    <select
                        v-model="status"
                        class="rounded-md border bg-card px-3 py-2 text-sm"
                    >
                        <option value="">Barcha holatlar</option>
                        <option v-for="opt in paymentStatuses" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/30 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="w-8 px-2 py-3"></th>
                                    <th class="px-4 py-3">Chek</th>
                                    <th class="px-4 py-3">Mijoz</th>
                                    <th class="px-4 py-3">Kassir</th>
                                    <th class="px-4 py-3">Vaqt</th>
                                    <th class="px-4 py-3 text-right">Summa</th>
                                    <th class="px-4 py-3 text-right">To'langan</th>
                                    <th class="px-4 py-3 text-right">Qarz</th>
                                    <th class="px-4 py-3">Holat</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <template v-for="sale in sales.data" :key="sale.id">
                                    <tr class="cursor-pointer hover:bg-muted/40" @click="toggleExpand(sale.id)">
                                        <td class="px-2 py-3 text-center text-muted-foreground">
                                            <ChevronDown
                                                class="inline h-4 w-4 transition-transform"
                                                :class="expanded.has(sale.id) ? 'rotate-180' : ''"
                                            />
                                        </td>
                                        <td class="px-4 py-3">
                                            <Link :href="`/dealer/pos/sales/${sale.id}`" class="inline-flex items-center gap-1 font-medium text-primary hover:underline" @click.stop>
                                                <Receipt class="h-3 w-3" />{{ sale.receipt_number ?? `#${sale.id}` }}
                                            </Link>
                                        </td>
                                        <td class="px-4 py-3">{{ sale.shop?.name ?? '—' }}</td>
                                        <td class="px-4 py-3">{{ sale.cashier?.name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-xs">{{ sale.created_at ? formatDateTime(sale.created_at) : '—' }}</td>
                                        <td class="px-4 py-3 text-right font-semibold">{{ formatMoneySum(sale.total) }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatMoneySum(sale.paid_amount) }}</td>
                                        <td class="px-4 py-3 text-right" :class="sale.debt_amount > 0 ? 'text-rose-500' : ''">
                                            {{ sale.debt_amount > 0 ? formatMoneySum(sale.debt_amount) : '—' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                v-if="sale.payment_status"
                                                class="inline-flex rounded-full px-2 py-0.5 text-xs"
                                                :class="statusStyles[sale.payment_status] ?? ''"
                                            >
                                                {{ sale.payment_status_label }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="expanded.has(sale.id)" class="bg-muted/20">
                                        <td></td>
                                        <td colspan="8" class="px-4 py-3">
                                            <div v-if="(sale.items?.length ?? 0) === 0" class="text-xs text-muted-foreground">
                                                Tovarlar topilmadi
                                            </div>
                                            <table v-else class="w-full text-xs">
                                                <thead class="text-left text-muted-foreground">
                                                    <tr>
                                                        <th class="py-1 font-medium">Tovar</th>
                                                        <th class="py-1 text-right font-medium">Miqdor</th>
                                                        <th class="py-1 text-right font-medium">Narx</th>
                                                        <th class="py-1 text-right font-medium">Summa</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="item in sale.items" :key="item.id" class="border-t border-border/50">
                                                        <td class="py-1.5">{{ item.display_name ?? item.product_name }}</td>
                                                        <td class="py-1.5 text-right tabular-nums">{{ item.qty }} {{ item.unit }}</td>
                                                        <td class="py-1.5 text-right tabular-nums">{{ formatMoneySum(item.price) }}</td>
                                                        <td class="py-1.5 text-right font-medium tabular-nums">{{ formatMoneySum(item.subtotal) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="sales.data.length === 0">
                                    <td colspan="9" class="px-4 py-12 text-center text-muted-foreground">Sotuv topilmadi</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <PaginationBar :links="sales.links" :meta="sales.meta" />
        </div>
    </div>
</template>
