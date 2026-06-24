<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useCurrency } from '@/composables/useCurrency';
import { useUnitLabel } from '@/composables/useUnitLabel';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Order, OrderItem, Product } from '@/types';

type ItemRow = {
    product_id: number;
    product_type_id: number | null;
    product_name: string;
    product_type_name: string | null;
    price: number;
    pack_price: number | null;
    delivered_qty: number;
    delivered_pack_qty: number;
    pack_size: number;
    bulk_only: boolean;
    unit: string;
};

type CatalogEntry = {
    key: string;
    product_id: number;
    product_type_id: number | null;
    name: string;
    price: number;
    pack_price: number | null;
    pack_size: number;
    bulk_only: boolean;
    unit: string;
};

const props = defineProps<{
    order: { data: Order };
    products: { data: Product[] };
}>();

const { t } = useI18n();
const { formatWithSymbol } = useCurrency();
const unitLabel = useUnitLabel();

function rowKey(productId: number, productTypeId: number | null): string {
    return `${productId}:${productTypeId ?? 0}`;
}

const initialRows: ItemRow[] = (props.order.data.items ?? []).map(
    (it: OrderItem) => {
        const packSize = Math.max(1, Number(it.pack_size ?? 1));

        return {
            product_id: it.product_id,
            product_type_id: it.product_type_id ?? null,
            product_name: it.product_name,
            product_type_name: it.product_type_name ?? null,
            price: Number(it.price),
            pack_price:
                it.pack_price !== null && it.pack_price !== undefined
                    ? Number(it.pack_price)
                    : null,
            delivered_qty: Number(it.delivered_qty ?? it.qty ?? 0),
            delivered_pack_qty: Number(
                it.delivered_pack_qty ?? it.pack_qty ?? 0,
            ),
            pack_size: packSize,
            bulk_only: false,
            unit: String(it.unit ?? 'dona'),
        };
    },
);

const rows = ref<ItemRow[]>(initialRows);
const addEntryKey = ref<string | null>(null);
const formError = ref<string | null>(null);

const form = useForm({
    items: [] as Array<{
        product_id: number;
        product_type_id: number | null;
        price: number;
        pack_price: number | null;
        delivered_qty: number;
        delivered_pack_qty: number | null;
    }>,
    paid_amount: Number(props.order.data.paid_amount ?? 0),
    paid_card: 0,
    cardholder_name: '',
    discount: Number(props.order.data.discount ?? 0),
});

const flattenedCatalog = computed<CatalogEntry[]>(() => {
    const out: CatalogEntry[] = [];

    props.products.data.forEach((p) => {
        if (p.has_types && (p.types?.length ?? 0) > 0) {
            (p.types ?? []).forEach((t) => {
                if (!t.is_active) {
                    return;
                }

                out.push({
                    key: rowKey(p.id, t.id),
                    product_id: p.id,
                    product_type_id: t.id,
                    name: `${p.name} — ${t.name}`,
                    price: t.price,
                    pack_price: t.pack_price ?? null,
                    pack_size: Math.max(1, t.pack_size ?? 1),
                    bulk_only: Boolean(t.bulk_only),
                    unit: p.unit,
                });
            });

            return;
        }

        if (p.has_types) {
            return;
        }

        out.push({
            key: rowKey(p.id, null),
            product_id: p.id,
            product_type_id: null,
            name: p.name,
            price: p.price,
            pack_price: p.pack_price ?? null,
            pack_size: Math.max(1, p.pack_size ?? 1),
            bulk_only: Boolean(p.bulk_only),
            unit: p.unit,
        });
    });

    return out;
});

const availableToAdd = computed(() =>
    flattenedCatalog.value.filter(
        (e) =>
            !rows.value.some(
                (r) =>
                    r.product_id === e.product_id &&
                    r.product_type_id === e.product_type_id,
            ),
    ),
);

function rowTotal(r: ItemRow): number {
    const packs = Math.max(0, Number(r.delivered_pack_qty) || 0);
    const packSize = Math.max(1, r.pack_size);
    const price = Math.max(0, Number(r.price) || 0);
    const qty = Math.max(0, Number(r.delivered_qty) || 0);

    if (packs > 0 && r.pack_price !== null && packSize > 1) {
        const loose = Math.max(0, qty - packs * packSize);
        const packPrice = Math.max(0, Number(r.pack_price) || 0);

        return Math.round(packs * packPrice + loose * price);
    }

    return Math.round(qty * price);
}

const deliveredTotal = computed(() =>
    rows.value.reduce((sum, r) => sum + rowTotal(r), 0),
);

const remainingBalance = computed(() => {
    const total =
        deliveredTotal.value - Math.max(0, Number(form.discount) || 0);

    return total - Math.max(0, Number(form.paid_amount) || 0);
});

function addProduct() {
    const key = addEntryKey.value;

    if (!key) {
        return;
    }

    const entry = flattenedCatalog.value.find((e) => e.key === key);

    if (!entry) {
        return;
    }

    if (
        rows.value.some((r) => rowKey(r.product_id, r.product_type_id) === key)
    ) {
        formError.value = t('pageDealer.orders.alreadyInList');

        return;
    }

    const product = props.products.data.find((p) => p.id === entry.product_id);
    const typeRow =
        entry.product_type_id !== null
            ? (product?.types?.find((t) => t.id === entry.product_type_id) ??
              null)
            : null;

    const packSize = entry.pack_size;
    const bulkOnly = entry.bulk_only && packSize > 1;
    const initialPackQty = bulkOnly ? 1 : 0;
    const initialQty = bulkOnly ? packSize : 1;

    rows.value.push({
        product_id: entry.product_id,
        product_type_id: entry.product_type_id,
        product_name: product?.name ?? entry.name,
        product_type_name: typeRow?.name ?? null,
        price: entry.price,
        pack_price: entry.pack_price,
        delivered_qty: initialQty,
        delivered_pack_qty: initialPackQty,
        pack_size: packSize,
        bulk_only: bulkOnly,
        unit: entry.unit,
    });

    addEntryKey.value = null;
    formError.value = null;
}

function removeRow(idx: number) {
    rows.value.splice(idx, 1);
}

function onPackQtyChange(r: ItemRow, value: number) {
    r.delivered_pack_qty = Math.max(0, Number(value) || 0);

    if (r.bulk_only) {
        r.delivered_qty = r.delivered_pack_qty * Math.max(1, r.pack_size);
    }
}

function submit() {
    formError.value = null;

    if (rows.value.length === 0) {
        formError.value = t('pageDealer.orders.atLeastOneProduct');

        return;
    }

    form.items = rows.value.map((r) => ({
        product_id: r.product_id,
        product_type_id: r.product_type_id,
        price: Math.max(0, Number(r.price) || 0),
        pack_price:
            r.pack_price !== null
                ? Math.max(0, Number(r.pack_price) || 0)
                : null,
        delivered_qty: r.bulk_only
            ? r.delivered_pack_qty * Math.max(1, r.pack_size)
            : Math.max(0, Number(r.delivered_qty) || 0),
        delivered_pack_qty:
            r.pack_size > 1
                ? Math.max(0, Number(r.delivered_pack_qty) || 0)
                : null,
    }));

    form.put(`/dealer/orders/${props.order.data.id}/edit`, {
        preserveScroll: true,
        onError: (errors) => {
            const first = Object.values(errors)[0];

            if (typeof first === 'string') {
                formError.value = first;
            }
        },
    });
}

function goBack() {
    router.get(`/dealer/orders/${props.order.data.id}`);
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head
        :title="t('pageDealer.orders.editHeadTitle', { id: order.data.id })"
    />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            <Button variant="outline" size="sm" @click="goBack">
                <ArrowLeft class="h-4 w-4" />
                {{ t('pageDealer.orders.back') }}
            </Button>
            <h1 class="text-xl font-bold sm:text-2xl">
                {{
                    t('pageDealer.orders.editTitle', {
                        number: order.data.number,
                    })
                }}
            </h1>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>{{ t('pageDealer.orders.products') }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="rounded-lg border">
                    <table class="hidden w-full text-left text-sm sm:table">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-3 py-2 font-medium">
                                    {{ t('pageDealer.orders.product') }}
                                </th>
                                <th
                                    class="w-32 px-3 py-2 text-right font-medium"
                                >
                                    {{ t('pageDealer.orders.unitPrice') }}
                                </th>
                                <th
                                    class="w-32 px-3 py-2 text-right font-medium"
                                >
                                    {{ t('pageDealer.orders.packPrice') }}
                                </th>
                                <th
                                    class="w-24 px-3 py-2 text-right font-medium"
                                >
                                    {{ t('pageDealer.orders.pack') }}
                                </th>
                                <th
                                    class="w-32 px-3 py-2 text-right font-medium"
                                >
                                    {{ t('pageDealer.orders.quantity') }}
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    {{ t('pageDealer.orders.total') }}
                                </th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(r, idx) in rows" :key="idx">
                                <td class="px-3 py-2">
                                    {{ r.product_name }}
                                    <span
                                        v-if="r.product_type_name"
                                        class="text-muted-foreground"
                                        >— {{ r.product_type_name }}</span
                                    >
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <Input
                                        type="number"
                                        min="0"
                                        v-model.number="r.price"
                                        class="h-8 w-28 text-right font-mono"
                                    />
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <Input
                                        v-if="r.pack_size > 1"
                                        type="number"
                                        min="0"
                                        v-model.number="r.pack_price"
                                        class="h-8 w-28 text-right font-mono"
                                    />
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                        >—</span
                                    >
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <Input
                                        v-if="r.pack_size > 1"
                                        type="number"
                                        min="0"
                                        :model-value="r.delivered_pack_qty"
                                        @update:model-value="
                                            (v) => onPackQtyChange(r, Number(v))
                                        "
                                        class="ml-auto h-8 w-20 text-right"
                                    />
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                        >—</span
                                    >
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <div
                                        v-if="!r.bulk_only"
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <Input
                                            type="number"
                                            min="0"
                                            v-model.number="r.delivered_qty"
                                            class="h-8 w-20 text-right"
                                        />
                                        <span
                                            class="w-8 shrink-0 text-left text-xs text-muted-foreground"
                                            >{{ unitLabel(r.unit) }}</span
                                        >
                                    </div>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                        >{{
                                            r.delivered_pack_qty * r.pack_size
                                        }}
                                        {{ unitLabel(r.unit) }}</span
                                    >
                                </td>
                                <td class="px-3 py-2 text-right font-mono">
                                    {{ formatWithSymbol(rowTotal(r)) }}
                                </td>
                                <td class="px-2 py-2">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-7 w-7 text-destructive"
                                        @click="removeRow(idx)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="rows.length === 0">
                                <td
                                    colspan="7"
                                    class="px-3 py-4 text-center text-muted-foreground"
                                >
                                    {{ t('pageDealer.orders.noProducts') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex flex-col divide-y sm:hidden">
                        <div
                            v-for="(r, idx) in rows"
                            :key="`m-${idx}`"
                            class="flex flex-col gap-2 p-3"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p
                                    class="min-w-0 flex-1 font-medium break-words"
                                >
                                    {{ r.product_name }}
                                    <span
                                        v-if="r.product_type_name"
                                        class="text-muted-foreground"
                                        >— {{ r.product_type_name }}</span
                                    >
                                </p>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-7 w-7 shrink-0 text-destructive"
                                    @click="removeRow(idx)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </Button>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <Label
                                        class="text-xs text-muted-foreground"
                                        >{{
                                            t('pageDealer.orders.unitPrice')
                                        }}</Label
                                    >
                                    <Input
                                        type="number"
                                        min="0"
                                        v-model.number="r.price"
                                        class="mt-1 h-9 text-right font-mono"
                                    />
                                </div>
                                <div v-if="r.pack_size > 1">
                                    <Label
                                        class="text-xs text-muted-foreground"
                                        >{{
                                            t('pageDealer.orders.packPrice')
                                        }}</Label
                                    >
                                    <Input
                                        type="number"
                                        min="0"
                                        v-model.number="r.pack_price"
                                        class="mt-1 h-9 text-right font-mono"
                                    />
                                </div>
                            </div>

                            <div
                                class="grid gap-2"
                                :class="
                                    r.pack_size > 1
                                        ? 'grid-cols-2'
                                        : 'grid-cols-1'
                                "
                            >
                                <div v-if="r.pack_size > 1">
                                    <Label
                                        class="text-xs text-muted-foreground"
                                        >{{
                                            t('pageDealer.orders.pack')
                                        }}</Label
                                    >
                                    <Input
                                        type="number"
                                        min="0"
                                        :model-value="r.delivered_pack_qty"
                                        @update:model-value="
                                            (v) => onPackQtyChange(r, Number(v))
                                        "
                                        class="mt-1 h-9 text-right"
                                    />
                                </div>
                                <div v-if="!r.bulk_only">
                                    <Label
                                        class="text-xs text-muted-foreground"
                                        >{{ unitLabel(r.unit) }}</Label
                                    >
                                    <Input
                                        type="number"
                                        min="0"
                                        v-model.number="r.delivered_qty"
                                        class="mt-1 h-9 text-right"
                                    />
                                </div>
                            </div>

                            <div class="flex justify-between font-medium">
                                <span class="text-muted-foreground">{{
                                    t('pageDealer.orders.total')
                                }}</span>
                                <span class="font-mono">{{
                                    formatWithSymbol(rowTotal(r))
                                }}</span>
                            </div>
                        </div>
                        <div
                            v-if="rows.length === 0"
                            class="p-4 text-center text-sm text-muted-foreground"
                        >
                            {{ t('pageDealer.orders.noProducts') }}
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <Label class="mb-1.5">{{
                            t('pageDealer.orders.addProduct')
                        }}</Label>
                        <SearchableSelect
                            v-model="addEntryKey"
                            :items="availableToAdd"
                            value-key="key"
                            label-key="name"
                            :placeholder="
                                t('pageDealer.orders.selectPlaceholder')
                            "
                            :search-placeholder="
                                t('pageDealer.orders.searchPlaceholder')
                            "
                            :empty-text="t('pageDealer.orders.notFound')"
                        >
                            <template #item-suffix="{ item }">
                                <span
                                    class="shrink-0 font-mono text-[11px] text-muted-foreground"
                                >
                                    {{ formatWithSymbol(item.price) }} /
                                    {{ unitLabel(item.unit) }}
                                </span>
                            </template>
                        </SearchableSelect>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        class="w-full sm:w-auto"
                        :disabled="!addEntryKey"
                        @click="addProduct"
                    >
                        <Plus class="mr-1 h-4 w-4" />
                        {{ t('pageDealer.orders.add') }}
                    </Button>
                </div>

                <InputError :message="form.errors.items" />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>{{ t('pageDealer.orders.payment') }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label for="paid_amount">{{
                            t('pageDealer.orders.paidTotal')
                        }}</Label>
                        <Input
                            id="paid_amount"
                            type="number"
                            min="0"
                            v-model.number="form.paid_amount"
                            class="mt-1 font-mono"
                        />
                        <InputError :message="form.errors.paid_amount" />
                    </div>
                    <div>
                        <Label for="discount">{{
                            t('pageDealer.orders.discount')
                        }}</Label>
                        <Input
                            id="discount"
                            type="number"
                            min="0"
                            v-model.number="form.discount"
                            class="mt-1 font-mono"
                        />
                        <InputError :message="form.errors.discount" />
                    </div>
                    <div>
                        <Label for="paid_card">{{
                            t('pageDealer.orders.paidByCard')
                        }}</Label>
                        <Input
                            id="paid_card"
                            type="number"
                            min="0"
                            v-model.number="form.paid_card"
                            class="mt-1 font-mono"
                        />
                        <InputError :message="form.errors.paid_card" />
                    </div>
                    <div v-if="form.paid_card > 0">
                        <Label for="cardholder_name">{{
                            t('pageDealer.orders.cardholder')
                        }}</Label>
                        <Input
                            id="cardholder_name"
                            v-model="form.cardholder_name"
                            class="mt-1"
                        />
                        <InputError :message="form.errors.cardholder_name" />
                    </div>
                </div>

                <div class="space-y-1 border-t pt-4 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">{{
                            t('pageDealer.orders.deliveredAmount')
                        }}</span>
                        <span class="font-mono">{{
                            formatWithSymbol(deliveredTotal)
                        }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">{{
                            t('pageDealer.orders.discount')
                        }}</span>
                        <span class="font-mono"
                            >−{{
                                formatWithSymbol(
                                    Math.max(0, Number(form.discount) || 0),
                                )
                            }}</span
                        >
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">{{
                            t('pageDealer.orders.paid')
                        }}</span>
                        <span class="font-mono"
                            >−{{
                                formatWithSymbol(
                                    Math.max(0, Number(form.paid_amount) || 0),
                                )
                            }}</span
                        >
                    </div>
                    <div
                        class="flex justify-between border-t pt-2 text-base font-bold"
                    >
                        <span>{{ t('pageDealer.orders.balanceImpact') }}</span>
                        <span
                            class="font-mono"
                            :class="
                                remainingBalance > 0
                                    ? 'text-destructive'
                                    : 'text-emerald-600'
                            "
                        >
                            {{ formatWithSymbol(remainingBalance) }}
                        </span>
                    </div>
                </div>

                <p
                    v-if="formError"
                    class="rounded bg-destructive/10 px-3 py-2 text-sm text-destructive"
                >
                    {{ formError }}
                </p>

                <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <Button variant="outline" type="button" @click="goBack">{{
                        t('pageDealer.orders.cancel')
                    }}</Button>
                    <Button
                        :disabled="form.processing || rows.length === 0"
                        @click="submit"
                    >
                        {{
                            form.processing
                                ? t('pageDealer.orders.saving')
                                : t('pageDealer.orders.save')
                        }}
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
