<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useCurrency } from '@/composables/useCurrency';
import { useUnitLabel } from '@/composables/useUnitLabel';

type ProductTypeOpt = {
    id: number;
    name: string;
    stock: number;
    price: number;
    pack_price: number | null;
    pack_size?: number;
    bulk_only?: boolean;
};

type ProductOpt = {
    id: number;
    name: string;
    unit: string;
    stock: number;
    has_types: boolean;
    price: number;
    pack_price: number | null;
    pack_size: number;
    bulk_only?: boolean;
    types: ProductTypeOpt[];
};

type CatalogEntry = {
    key: string;
    product_id: number;
    product_type_id: number | null;
    name: string;
    unit: string;
    stock: number;
    price: number;
    pack_price: number | null;
    pack_size: number;
    bulk_only: boolean;
};

type Row = {
    key: string;
    product_id: number;
    product_type_id: number | null;
    product_name: string;
    unit: string;
    stock: number;
    pack_size: number;
    bulk_only: boolean;
    return_pack_qty: number;
    return_unit_qty: number;
    unit_cost: number;
    pack_unit_cost: number | null;
};

type PaymentMode = 'all-cash' | 'all-card' | 'none' | 'split';

function rowKey(productId: number, productTypeId: number | null): string {
    return `${productId}:${productTypeId ?? 0}`;
}

const props = defineProps<{
    open: boolean;
    supplierId: number;
    supplierName?: string;
    supplierBalance?: number;
    products: ProductOpt[];
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const { t } = useI18n();
const unitLabel = useUnitLabel();
const { formatWithSymbol } = useCurrency();

const REASONS = computed(() => [
    {
        value: 'defective',
        label: t('pageDealer.suppliers.returnReasonDefective'),
    },
    { value: 'expired', label: t('pageDealer.suppliers.returnReasonExpired') },
    { value: 'wrong_item', label: t('pageDealer.suppliers.returnReasonWrong') },
    { value: 'damaged', label: t('pageDealer.suppliers.returnReasonDamaged') },
    { value: 'other', label: t('pageDealer.suppliers.returnReasonOther') },
]);

const rows = reactive<Row[]>([]);
const addEntryKey = ref<string | null>(null);
const reason = ref('defective');
const note = ref('');
const paymentMode = ref<PaymentMode>('none');
const customCash = ref(0);
const customCard = ref(0);
const cardholderName = ref('');
const processing = ref(false);
const error = ref<string | null>(null);

const flattenedCatalog = computed<CatalogEntry[]>(() => {
    const out: CatalogEntry[] = [];

    props.products.forEach((p) => {
        if (p.has_types && p.types.length > 0) {
            p.types.forEach((t) => {
                const typePackSize = Math.max(
                    1,
                    t.pack_size ?? p.pack_size ?? 1,
                );

                out.push({
                    key: rowKey(p.id, t.id),
                    product_id: p.id,
                    product_type_id: t.id,
                    name: `${p.name} — ${t.name}`,
                    unit: p.unit,
                    stock: t.stock,
                    price: t.price,
                    pack_price: t.pack_price,
                    pack_size: typePackSize,
                    bulk_only: Boolean(t.bulk_only) && typePackSize > 1,
                });
            });

            return;
        }

        const packSize = Math.max(1, p.pack_size ?? 1);

        out.push({
            key: rowKey(p.id, null),
            product_id: p.id,
            product_type_id: null,
            name: p.name,
            unit: p.unit,
            stock: p.stock,
            price: p.price,
            pack_price: p.pack_price,
            pack_size: packSize,
            bulk_only: Boolean(p.bulk_only) && packSize > 1,
        });
    });

    return out;
});

const availableToAdd = computed<CatalogEntry[]>(() =>
    flattenedCatalog.value.filter((e) => !rows.some((r) => r.key === e.key)),
);

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        rows.splice(0, rows.length);
        addEntryKey.value = null;
        reason.value = 'defective';
        note.value = '';
        paymentMode.value = 'none';
        customCash.value = 0;
        customCard.value = 0;
        cardholderName.value = '';
        error.value = null;
    },
);

function addProduct() {
    const key = addEntryKey.value;

    if (key === null) {
        return;
    }

    const entry = flattenedCatalog.value.find((e) => e.key === key);

    if (entry === undefined) {
        return;
    }

    if (rows.some((r) => r.key === key)) {
        error.value = t('pageDealer.suppliers.returnAlreadyInList');

        return;
    }

    rows.push({
        key: entry.key,
        product_id: entry.product_id,
        product_type_id: entry.product_type_id,
        product_name: entry.name,
        unit: entry.unit,
        stock: entry.stock,
        pack_size: entry.pack_size,
        bulk_only: entry.bulk_only,
        return_pack_qty: entry.pack_size > 1 ? 1 : 0,
        return_unit_qty: entry.bulk_only || entry.pack_size > 1 ? 0 : 1,
        unit_cost: entry.price,
        pack_unit_cost: entry.pack_price,
    });

    addEntryKey.value = null;
    error.value = null;
}

function removeRow(idx: number) {
    rows.splice(idx, 1);
}

function totalQty(r: Row): number {
    const packs = Math.max(0, Number(r.return_pack_qty) || 0);
    const units = r.bulk_only ? 0 : Math.max(0, Number(r.return_unit_qty) || 0);

    return packs * Math.max(1, r.pack_size) + units;
}

function clamp(r: Row) {
    if (r.pack_size > 1) {
        const maxPacks = Math.floor(r.stock / r.pack_size);
        r.return_pack_qty = Math.min(Math.max(0, r.return_pack_qty), maxPacks);
        const remaining = Math.max(
            0,
            r.stock - r.return_pack_qty * r.pack_size,
        );
        r.return_unit_qty = r.bulk_only
            ? 0
            : Math.min(Math.max(0, r.return_unit_qty), remaining);
    } else {
        r.return_unit_qty = Math.min(Math.max(0, r.return_unit_qty), r.stock);
        r.return_pack_qty = 0;
    }
}

function lineTotal(r: Row): number {
    const packs = Math.max(0, r.return_pack_qty);
    const packSize = Math.max(1, r.pack_size);

    if (
        packs > 0 &&
        r.pack_unit_cost !== null &&
        r.pack_unit_cost > 0 &&
        packSize > 1
    ) {
        const loose = Math.max(0, totalQty(r) - packs * packSize);

        return Math.round(
            packs * r.pack_unit_cost + loose * Math.max(0, r.unit_cost),
        );
    }

    return Math.round(totalQty(r) * Math.max(0, r.unit_cost));
}

const total = computed(() => rows.reduce((sum, r) => sum + lineTotal(r), 0));

const paidCash = computed<number>({
    get: () => {
        const mode = paymentMode.value;

        if (mode === 'all-cash') {
            return Math.max(0, total.value);
        }

        if (mode === 'all-card' || mode === 'none') {
            return 0;
        }

        return Math.max(0, Number(customCash.value) || 0);
    },
    set: (v: number) => {
        const next = Math.max(0, Number(v) || 0);

        if (paymentMode.value !== 'split') {
            const currentCard = paidCard.value;
            paymentMode.value = 'split';
            customCard.value = currentCard;
        }

        customCash.value = next;
    },
});

const paidCard = computed<number>({
    get: () => {
        const mode = paymentMode.value;

        if (mode === 'all-card') {
            return Math.max(0, total.value);
        }

        if (mode === 'all-cash' || mode === 'none') {
            return 0;
        }

        return Math.max(0, Number(customCard.value) || 0);
    },
    set: (v: number) => {
        const next = Math.max(0, Number(v) || 0);

        if (paymentMode.value !== 'split') {
            const currentCash = paidCash.value;
            paymentMode.value = 'split';
            customCash.value = currentCash;
        }

        customCard.value = next;
    },
});

const previousBalance = computed(() => props.supplierBalance ?? 0);
const balanceCredit = computed(
    () => total.value - paidCash.value - paidCard.value,
);
const finalBalance = computed(
    () => previousBalance.value + balanceCredit.value,
);

function balanceDeltaLabel(delta: number): string {
    if (delta === 0) {
        return '';
    }

    return delta > 0
        ? `+${formatWithSymbol(delta)}`
        : `−${formatWithSymbol(Math.abs(delta))}`;
}

function submit() {
    const cardAmount = Math.max(0, Number(paidCard.value) || 0);
    const trimmedCardholder = cardholderName.value.trim();

    if (cardAmount > 0 && trimmedCardholder === '') {
        error.value = t('pageDealer.suppliers.returnCardholderRequired');

        return;
    }

    const items = rows
        .filter((r) => totalQty(r) > 0 && r.unit_cost > 0)
        .map((r) => ({
            product_id: r.product_id,
            product_type_id: r.product_type_id,
            qty: totalQty(r),
            unit_cost: r.unit_cost,
            pack_unit_cost: r.pack_unit_cost,
        }));

    if (items.length === 0) {
        error.value = t('pageDealer.suppliers.returnAtLeastOneRow');

        return;
    }

    processing.value = true;
    error.value = null;

    router.post(
        `/dealer/suppliers/${props.supplierId}/return`,
        {
            reason: reason.value,
            note: note.value || null,
            paid_cash: Math.max(0, Math.round(Number(paidCash.value) || 0)),
            paid_card: Math.max(0, Math.round(cardAmount)),
            cardholder_name: cardAmount > 0 ? trimmedCardholder : null,
            items,
        },
        {
            preserveScroll: true,
            onSuccess: () => emit('update:open', false),
            onError: (e) => {
                error.value =
                    Object.values(e).join(', ') ||
                    t('pageDealer.suppliers.returnGenericError');
            },
            onFinish: () => {
                processing.value = false;
            },
        },
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent
            class="flex h-[100dvh] max-h-[100dvh] w-screen max-w-none flex-col gap-0 rounded-none border-0 p-0 sm:h-auto sm:max-h-[calc(100dvh-2rem)] sm:w-auto sm:max-w-3xl sm:rounded-lg sm:border"
            @open-auto-focus="(e: Event) => e.preventDefault()"
            @pointer-down-outside="
                (e: Event) => {
                    const target = e.target as HTMLElement | null;
                    if (target?.closest('[data-searchable-select-popup]')) {
                        e.preventDefault();
                    }
                }
            "
            @interact-outside="
                (e: Event) => {
                    const target = e.target as HTMLElement | null;
                    if (target?.closest('[data-searchable-select-popup]')) {
                        e.preventDefault();
                    }
                }
            "
        >
            <DialogHeader class="border-b px-3 py-2 sm:px-4 sm:py-3">
                <DialogTitle class="pr-8 text-sm font-semibold sm:text-base">
                    {{ t('pageDealer.suppliers.returnTitle') }}
                    <span
                        v-if="supplierName"
                        class="ml-1 text-xs font-normal text-muted-foreground"
                        >· {{ supplierName }}</span
                    >
                </DialogTitle>
            </DialogHeader>

            <div class="flex-1 space-y-2.5 overflow-y-auto p-3 sm:p-4">
                <div class="grid grid-cols-2 gap-2">
                    <select
                        v-model="reason"
                        class="block h-9 w-full rounded-md border border-input bg-background px-2 text-sm shadow-sm"
                    >
                        <option
                            v-for="r in REASONS"
                            :key="r.value"
                            :value="r.value"
                        >
                            {{ r.label }}
                        </option>
                    </select>
                    <Input
                        v-model="note"
                        class="h-9"
                        :placeholder="
                            t('pageDealer.suppliers.returnNotePlaceholder')
                        "
                    />
                </div>

                <div class="flex gap-2">
                    <div class="flex-1">
                        <SearchableSelect
                            v-model="addEntryKey"
                            :items="availableToAdd"
                            value-key="key"
                            label-key="name"
                            :placeholder="
                                t('pageDealer.suppliers.returnAddProduct')
                            "
                            :search-placeholder="
                                t(
                                    'pageDealer.suppliers.returnProductNamePlaceholder',
                                )
                            "
                            :empty-text="
                                t('pageDealer.suppliers.returnNotFound')
                            "
                        />
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="h-10 w-10 shrink-0"
                        :disabled="addEntryKey === null"
                        @click="addProduct"
                    >
                        <Plus class="h-4 w-4" />
                    </Button>
                </div>

                <div
                    v-if="rows.length === 0"
                    class="rounded-lg border border-dashed bg-muted/20 px-3 py-3 text-center text-xs text-muted-foreground"
                >
                    {{ t('pageDealer.suppliers.returnSelectProduct') }}
                </div>

                <div v-else class="space-y-1.5">
                    <div
                        v-for="(r, idx) in rows"
                        :key="r.key"
                        class="rounded-md border bg-primary/[0.04] p-2"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p
                                class="min-w-0 flex-1 truncate text-sm font-medium"
                            >
                                {{ r.product_name }}
                            </p>
                            <span
                                class="shrink-0 font-mono text-sm font-semibold tabular-nums"
                            >
                                {{ formatWithSymbol(lineTotal(r)) }}
                            </span>
                            <button
                                type="button"
                                class="shrink-0 text-destructive hover:opacity-75"
                                @click="removeRow(idx)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <div
                            class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs"
                        >
                            <span class="text-muted-foreground"
                                >{{ t('pageDealer.suppliers.returnWarehouse') }}
                                {{ r.stock
                                }}{{
                                    r.unit ? ' ' + unitLabel(r.unit) : ''
                                }}</span
                            >
                            <template v-if="r.pack_size > 1">
                                <Input
                                    type="number"
                                    min="0"
                                    v-model.number="r.return_pack_qty"
                                    class="h-8 w-14 text-right tabular-nums"
                                    @change="clamp(r)"
                                />
                                <span class="text-muted-foreground">{{
                                    t('pageDealer.suppliers.returnBlock')
                                }}</span>
                                <template v-if="!r.bulk_only">
                                    <Input
                                        type="number"
                                        min="0"
                                        v-model.number="r.return_unit_qty"
                                        class="h-8 w-14 text-right tabular-nums"
                                        @change="clamp(r)"
                                    />
                                    <span class="text-muted-foreground">{{
                                        unitLabel(r.unit ?? 'dona')
                                    }}</span>
                                </template>
                            </template>
                            <template v-else>
                                <Input
                                    type="number"
                                    min="0"
                                    v-model.number="r.return_unit_qty"
                                    class="h-8 w-20 text-right tabular-nums"
                                    @change="clamp(r)"
                                />
                                <span class="text-muted-foreground">{{
                                    unitLabel(r.unit ?? 'dona')
                                }}</span>
                            </template>
                            <span class="ml-auto text-muted-foreground">{{
                                t('pageDealer.suppliers.returnCost')
                            }}</span>
                            <Input
                                type="number"
                                min="0"
                                step="0.000001"
                                v-model.number="r.unit_cost"
                                class="h-8 w-24 text-right tabular-nums"
                            />
                        </div>
                    </div>
                </div>

                <p
                    v-if="error"
                    class="rounded bg-destructive/10 px-3 py-2 text-sm text-destructive"
                >
                    {{ error }}
                </p>
            </div>

            <div class="border-t bg-muted/30 px-3 py-2.5 sm:px-4 sm:py-3">
                <div class="flex items-baseline justify-between">
                    <span class="text-xs text-muted-foreground">{{
                        t('pageDealer.suppliers.returnAmount')
                    }}</span>
                    <span
                        class="font-mono text-base font-semibold tabular-nums"
                        >{{ formatWithSymbol(total) }}</span
                    >
                </div>

                <div class="mt-2 grid grid-cols-2 gap-2">
                    <Input
                        id="sup_return_paid_cash"
                        type="number"
                        min="0"
                        v-model.number="paidCash"
                        class="h-9"
                        :placeholder="
                            t('pageDealer.suppliers.returnCashPlaceholder')
                        "
                    />
                    <Input
                        id="sup_return_paid_card"
                        type="number"
                        min="0"
                        v-model.number="paidCard"
                        class="h-9"
                        :placeholder="
                            t('pageDealer.suppliers.returnCardPlaceholder')
                        "
                    />
                </div>
                <div class="mt-1 flex flex-wrap gap-1 text-xs">
                    <button
                        type="button"
                        class="rounded border px-2 py-0.5 hover:bg-muted"
                        :class="
                            paymentMode === 'all-cash'
                                ? 'border-primary bg-primary/5 text-primary'
                                : ''
                        "
                        @click="paymentMode = 'all-cash'"
                    >
                        {{ t('pageDealer.suppliers.returnAllCash') }}
                    </button>
                    <button
                        type="button"
                        class="rounded border px-2 py-0.5 hover:bg-muted"
                        :class="
                            paymentMode === 'all-card'
                                ? 'border-primary bg-primary/5 text-primary'
                                : ''
                        "
                        @click="paymentMode = 'all-card'"
                    >
                        {{ t('pageDealer.suppliers.returnAllCard') }}
                    </button>
                    <button
                        type="button"
                        class="rounded border px-2 py-0.5 hover:bg-muted"
                        :class="
                            paymentMode === 'none'
                                ? 'border-primary bg-primary/5 text-primary'
                                : ''
                        "
                        @click="paymentMode = 'none'"
                    >
                        0
                    </button>
                </div>

                <Input
                    v-if="paidCard > 0"
                    id="sup_return_cardholder"
                    type="text"
                    v-model="cardholderName"
                    :placeholder="
                        t('pageDealer.suppliers.returnCardholderPlaceholder')
                    "
                    class="mt-2 h-9"
                />

                <div
                    class="mt-2 flex flex-wrap items-center justify-between gap-2 border-t pt-2 text-xs"
                >
                    <span class="text-muted-foreground">{{
                        t('pageDealer.suppliers.returnBalance')
                    }}</span>
                    <span class="font-mono tabular-nums">
                        {{ formatWithSymbol(previousBalance) }}
                        <span class="text-muted-foreground">→</span>
                        <span class="font-semibold">{{
                            formatWithSymbol(finalBalance)
                        }}</span>
                        <span
                            v-if="balanceCredit !== 0"
                            class="ml-1 inline-flex items-center rounded px-1 py-0.5 font-medium"
                            :class="
                                balanceCredit > 0
                                    ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                    : 'bg-rose-500/15 text-rose-700 dark:text-rose-300'
                            "
                        >
                            {{ balanceDeltaLabel(balanceCredit) }}
                        </span>
                    </span>
                </div>
            </div>

            <DialogFooter class="flex-row gap-2 border-t px-3 py-2 sm:px-4">
                <Button
                    variant="outline"
                    size="sm"
                    class="flex-1 sm:flex-none"
                    @click="emit('update:open', false)"
                    >{{ t('pageDealer.suppliers.returnCancel') }}</Button
                >
                <Button
                    size="sm"
                    class="flex-1 sm:flex-none"
                    :disabled="processing || rows.length === 0"
                    @click="submit"
                >
                    {{
                        processing
                            ? t('pageDealer.suppliers.returnSaving')
                            : t('pageDealer.suppliers.returnSubmit')
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
