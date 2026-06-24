<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';

const { t } = useI18n();
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useCurrency } from '@/composables/useCurrency';
import { useUnitLabel } from '@/composables/useUnitLabel';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Product, Shop } from '@/types';

type ItemRow = {
    product_id: number;
    product_type_id: number | null;
    product_name: string;
    product_type_name: string | null;
    price: number;
    qty: number;
    pack_qty: number;
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
    pack_size: number;
    bulk_only: boolean;
    unit: string;
};

const props = defineProps<{
    shops: { data: Shop[] };
    products: { data: Product[] };
    preselectedShopId: number | null;
}>();

const form = useForm({
    shop_id: props.preselectedShopId,
    note: '',
    items: [] as Array<{
        product_id: number;
        product_type_id: number | null;
        qty: number;
        pack_qty: number | null;
        price: number;
    }>,
});

const unitLabel = useUnitLabel();
const { formatWithSymbol } = useCurrency();

const rows = ref<ItemRow[]>([]);
const addEntryKey = ref<string | null>(null);
const formError = ref<string | null>(null);

function rowKey(productId: number, productTypeId: number | null): string {
    return `${productId}:${productTypeId ?? 0}`;
}

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

const total = computed(() =>
    rows.value.reduce((sum, r) => {
        const qty = r.bulk_only ? r.pack_qty * Math.max(1, r.pack_size) : r.qty;

        return sum + Math.max(0, r.price) * Math.max(0, qty);
    }, 0),
);

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
        formError.value = t('pageDealer.ordersCreate.errAlreadyAdded');

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
        qty: initialQty,
        pack_qty: initialPackQty,
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
    r.pack_qty = Math.max(0, Number(value) || 0);

    if (r.bulk_only) {
        r.qty = r.pack_qty * Math.max(1, r.pack_size);
    }
}

function rowTotal(r: ItemRow): number {
    const qty = r.bulk_only ? r.pack_qty * Math.max(1, r.pack_size) : r.qty;

    return Math.max(0, r.price) * Math.max(0, qty);
}

function submit() {
    formError.value = null;

    if (form.shop_id === null || form.shop_id === undefined) {
        formError.value = t('pageDealer.ordersCreate.errSelectShop');

        return;
    }

    if (rows.value.length === 0) {
        formError.value = t('pageDealer.ordersCreate.errAddItem');

        return;
    }

    form.items = rows.value.map((r) => ({
        product_id: r.product_id,
        product_type_id: r.product_type_id,
        qty: r.bulk_only
            ? r.pack_qty * Math.max(1, r.pack_size)
            : Math.max(1, Number(r.qty) || 0),
        pack_qty: r.pack_size > 1 ? Math.max(0, Number(r.pack_qty) || 0) : null,
        price: Math.max(0, Number(r.price) || 0),
    }));

    form.post('/dealer/orders', {
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
    if (typeof window !== 'undefined' && window.history.length > 1) {
        window.history.back();
    } else {
        router.get('/dealer/orders');
    }
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.ordersCreate.headTitle')" />

    <div class="flex flex-col gap-3 p-3 sm:gap-4 sm:p-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center gap-2">
            <Button variant="outline" size="sm" @click="goBack">
                <ArrowLeft class="h-4 w-4" />
                {{ t('pageDealer.ordersCreate.back') }}
            </Button>
            <h1 class="text-lg font-bold sm:text-xl">
                {{ t('pageDealer.ordersCreate.title') }}
            </h1>
        </div>

        <Card class="gap-0 py-0">
            <CardContent class="space-y-3 px-3 py-3 sm:px-4 sm:py-4">
                <!-- Mijoz -->
                <section class="space-y-1.5">
                    <Label
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ t('pageDealer.ordersCreate.shopCardTitle') }}
                    </Label>
                    <SearchableSelect
                        v-model="form.shop_id"
                        :items="shops.data"
                        value-key="id"
                        label-key="name"
                        :placeholder="
                            t('pageDealer.ordersCreate.shopPlaceholder')
                        "
                        :search-placeholder="
                            t('pageDealer.ordersCreate.shopSearch')
                        "
                        :empty-text="t('pageDealer.ordersCreate.shopEmpty')"
                    />
                    <InputError :message="form.errors.shop_id" />
                </section>

                <!-- Mahsulotlar -->
                <section class="space-y-2 border-t pt-3">
                    <Label
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ t('pageDealer.ordersCreate.productsCardTitle') }}
                    </Label>
                    <div class="rounded-lg border">
                        <!-- Desktop -->
                        <table class="hidden w-full text-left text-sm sm:table">
                            <thead class="border-b bg-muted/40">
                                <tr>
                                    <th class="px-3 py-2 font-medium">
                                        {{
                                            t(
                                                'pageDealer.ordersCreate.tableProduct',
                                            )
                                        }}
                                    </th>
                                    <th
                                        class="w-32 px-3 py-2 text-right font-medium"
                                    >
                                        {{
                                            t(
                                                'pageDealer.ordersCreate.tablePrice',
                                            )
                                        }}
                                    </th>
                                    <th
                                        class="w-24 px-3 py-2 text-right font-medium"
                                    >
                                        {{
                                            t(
                                                'pageDealer.ordersCreate.tablePack',
                                            )
                                        }}
                                    </th>
                                    <th
                                        class="w-32 px-3 py-2 pr-11 text-right font-medium"
                                    >
                                        {{
                                            t(
                                                'pageDealer.ordersCreate.tableUnit',
                                            )
                                        }}
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        {{
                                            t(
                                                'pageDealer.ordersCreate.tableTotal',
                                            )
                                        }}
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
                                            :model-value="r.pack_qty"
                                            @update:model-value="
                                                (v) =>
                                                    onPackQtyChange(
                                                        r,
                                                        Number(v),
                                                    )
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
                                                :min="r.pack_size > 1 ? 0 : 1"
                                                v-model.number="r.qty"
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
                                            >—</span
                                        >
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono">
                                        <div>
                                            {{ formatWithSymbol(rowTotal(r)) }}
                                        </div>
                                        <div
                                            v-if="
                                                r.pack_size > 1 && r.bulk_only
                                            "
                                            class="text-xs font-normal text-muted-foreground"
                                        >
                                            =
                                            {{
                                                r.pack_qty *
                                                Math.max(1, r.pack_size)
                                            }}
                                            {{ unitLabel(r.unit) }}
                                        </div>
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
                                        colspan="6"
                                        class="px-3 py-4 text-center text-muted-foreground"
                                    >
                                        {{
                                            t('pageDealer.ordersCreate.noItems')
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Mobile -->
                        <div class="flex flex-col divide-y sm:hidden">
                            <div
                                v-for="(r, idx) in rows"
                                :key="`m-${idx}`"
                                class="flex flex-col gap-1.5 p-2.5"
                            >
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium break-words">
                                            {{ r.product_name }}
                                            <span
                                                v-if="r.product_type_name"
                                                class="text-muted-foreground"
                                                >—
                                                {{ r.product_type_name }}</span
                                            >
                                        </p>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-7 w-7 shrink-0 text-destructive"
                                        @click="removeRow(idx)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </Button>
                                </div>

                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <Label
                                        :for="`m-price-${idx}`"
                                        class="text-muted-foreground"
                                        >{{
                                            t(
                                                'pageDealer.ordersCreate.tablePrice',
                                            )
                                        }}</Label
                                    >
                                    <Input
                                        :id="`m-price-${idx}`"
                                        type="number"
                                        min="0"
                                        v-model.number="r.price"
                                        class="h-8 w-32 text-right font-mono"
                                    />
                                </div>

                                <div
                                    v-if="r.pack_size > 1"
                                    class="grid gap-1.5"
                                    :class="
                                        r.bulk_only
                                            ? 'grid-cols-1'
                                            : 'grid-cols-2'
                                    "
                                >
                                    <div>
                                        <Label
                                            class="text-xs text-muted-foreground"
                                            >{{
                                                t(
                                                    'pageDealer.ordersCreate.tablePack',
                                                )
                                            }}</Label
                                        >
                                        <Input
                                            type="number"
                                            min="0"
                                            :model-value="r.pack_qty"
                                            @update:model-value="
                                                (v) =>
                                                    onPackQtyChange(
                                                        r,
                                                        Number(v),
                                                    )
                                            "
                                            class="mt-0.5 h-8 text-right"
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
                                            v-model.number="r.qty"
                                            class="mt-0.5 h-8 text-right"
                                        />
                                    </div>
                                </div>
                                <div v-else class="flex items-center gap-2">
                                    <Label
                                        class="text-xs text-muted-foreground"
                                        >{{
                                            t(
                                                'pageDealer.ordersCreate.amountLabel',
                                            )
                                        }}</Label
                                    >
                                    <Input
                                        type="number"
                                        min="1"
                                        v-model.number="r.qty"
                                        class="h-8 flex-1 text-right"
                                    />
                                    <span
                                        class="shrink-0 text-xs text-muted-foreground"
                                        >{{ unitLabel(r.unit) }}</span
                                    >
                                </div>

                                <div class="flex justify-between font-medium">
                                    <span class="text-muted-foreground">{{
                                        t('pageDealer.ordersCreate.tableTotal')
                                    }}</span>
                                    <span class="font-mono">{{
                                        formatWithSymbol(rowTotal(r))
                                    }}</span>
                                </div>
                            </div>
                            <div
                                v-if="rows.length === 0"
                                class="p-3 text-center text-sm text-muted-foreground"
                            >
                                {{ t('pageDealer.ordersCreate.noItems') }}
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:gap-2"
                    >
                        <div class="flex-1">
                            <SearchableSelect
                                v-model="addEntryKey"
                                :items="availableToAdd"
                                value-key="key"
                                label-key="name"
                                :placeholder="
                                    t('pageDealer.ordersCreate.selectProduct')
                                "
                                :search-placeholder="
                                    t('pageDealer.ordersCreate.productSearch')
                                "
                                :empty-text="
                                    t('pageDealer.ordersCreate.productEmpty')
                                "
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
                            size="sm"
                            class="w-full sm:w-auto"
                            :disabled="!addEntryKey"
                            @click="addProduct"
                        >
                            <Plus class="mr-1 h-4 w-4" />
                            {{ t('pageDealer.ordersCreate.addBtn') }}
                        </Button>
                    </div>

                    <InputError :message="form.errors.items" />
                </section>

                <!-- Izoh -->
                <section class="space-y-1.5 border-t pt-3">
                    <Label
                        for="note"
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >{{ t('pageDealer.ordersCreate.noteLabel') }}</Label
                    >
                    <textarea
                        id="note"
                        v-model="form.note"
                        rows="2"
                        class="w-full rounded-md border bg-background px-3 py-1.5 text-sm shadow-sm focus:ring-2 focus:ring-ring focus:outline-none"
                        :placeholder="
                            t('pageDealer.ordersCreate.notePlaceholder')
                        "
                    />
                    <InputError :message="form.errors.note" />
                </section>

                <!-- Yakun -->
                <section class="space-y-2 border-t pt-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium">{{
                            t('pageDealer.ordersCreate.orderTotal')
                        }}</span>
                        <span class="font-mono text-lg font-bold">{{
                            formatWithSymbol(total)
                        }}</span>
                    </div>

                    <p
                        v-if="formError"
                        class="rounded bg-destructive/10 px-3 py-1.5 text-sm text-destructive"
                    >
                        {{ formError }}
                    </p>

                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <Button
                            variant="outline"
                            size="sm"
                            type="button"
                            @click="goBack"
                            >{{ t('pageDealer.ordersCreate.cancel') }}</Button
                        >
                        <Button
                            size="sm"
                            :disabled="
                                form.processing ||
                                rows.length === 0 ||
                                !form.shop_id
                            "
                            @click="submit"
                        >
                            {{
                                form.processing
                                    ? t('pageDealer.ordersCreate.saving')
                                    : t('pageDealer.ordersCreate.submit')
                            }}
                        </Button>
                    </div>
                </section>
            </CardContent>
        </Card>
    </div>
</template>
