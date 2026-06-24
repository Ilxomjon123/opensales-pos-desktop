<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Package, Truck } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Spinner } from '@/components/ui/spinner';
import { useCurrency } from '@/composables/useCurrency';
import { useUnitLabel } from '@/composables/useUnitLabel';
import type { Product, ProductType } from '@/types';

const props = defineProps<{
    open: boolean;
    product: Product | null;
    suppliers: { id: number; name: string }[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();

const { t } = useI18n();
const unitLabel = useUnitLabel();
const { symbol } = useCurrency();

type Line = {
    product_id: number;
    product_type_id?: number | null;
    qty: number;
    unit_cost: number | null;
};

const form = useForm<{
    supplier_id: number | null;
    note: string;
    items: Line[];
    paid_amount: number | null;
    payment_method: 'cash' | 'card';
    cardholder_name: string;
}>({
    supplier_id: null,
    note: '',
    items: [],
    paid_amount: null,
    payment_method: 'cash',
    cardholder_name: '',
});

const grandTotal = computed(() => {
    let total = 0;
    let any = false;
    form.items.forEach((l) => {
        if (l.unit_cost !== null && l.unit_cost > 0 && l.qty > 0) {
            total += l.unit_cost * l.qty;
            any = true;
        }
    });

    return any ? total : null;
});

const remainingDebt = computed(() => {
    if (grandTotal.value === null) {
        return null;
    }

    return grandTotal.value - (form.paid_amount ?? 0);
});

const hasTypes = computed(
    () =>
        Boolean(props.product?.has_types) &&
        (props.product?.types?.length ?? 0) > 0,
);

const packSize = computed(() => Math.max(1, props.product?.pack_size ?? 1));
const hasPack = computed(() => packSize.value > 1);

const qty = computed({
    get: () => form.items[0]?.qty ?? 0,
    set: (v) => {
        if (form.items[0]) {
            form.items[0].qty = v;
        }
    },
});
const unitCost = computed({
    get: () => form.items[0]?.unit_cost ?? null,
    set: (v) => {
        if (form.items[0]) {
            form.items[0].unit_cost = v;
        }
    },
});

const packEquivalent = computed(() => {
    if (!hasPack.value || qty.value <= 0) {
        return null;
    }

    const blocks = Math.floor(qty.value / packSize.value);
    const remainder = qty.value % packSize.value;

    if (blocks === 0) {
        return null;
    }

    return remainder === 0
        ? t('pageDealer.products.stockIn.packBlocks', { n: blocks })
        : t('pageDealer.products.stockIn.packBlocksRemainder', {
              n: blocks,
              remainder,
              unit: unitLabel(props.product?.unit ?? ''),
          });
});

const totalCost = computed(() => {
    if (unitCost.value === null || unitCost.value <= 0 || qty.value <= 0) {
        return null;
    }

    return unitCost.value * qty.value;
});

const filledCount = computed(() => form.items.filter((l) => l.qty > 0).length);

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen || !props.product) {
            return;
        }

        form.reset();
        form.clearErrors();
        form.note = '';
        form.supplier_id =
            props.suppliers.length === 1 ? props.suppliers[0].id : null;
        form.paid_amount = null;
        form.payment_method = 'cash';
        form.cardholder_name = '';

        if (hasTypes.value) {
            form.items = (props.product.types ?? []).map((t) => ({
                product_id: props.product!.id,
                product_type_id: t.id,
                qty: 0,
                unit_cost: null,
            }));
        } else {
            form.items = [
                { product_id: props.product.id, qty: 0, unit_cost: null },
            ];
        }
    },
);

function formatMoney(amount: number): string {
    return String(Math.round(amount)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

function close() {
    emit('update:open', false);
}

function submit() {
    if (!props.product) {
        return;
    }

    const paid = form.paid_amount ?? 0;
    const payload = {
        supplier_id: form.supplier_id,
        note: form.note,
        items: form.items.filter((l) => l.qty > 0),
        paid_amount: paid,
        payment_method: paid > 0 ? form.payment_method : 'cash',
        cardholder_name:
            paid > 0 && form.payment_method === 'card'
                ? form.cardholder_name
                : '',
    };

    if (payload.items.length === 0 || !payload.supplier_id) {
        return;
    }

    form.transform(() => payload).post('/dealer/stock-transactions', {
        preserveScroll: true,
        onSuccess: () => {
            emit('success');
            close();
        },
    });
}

function addByPack() {
    if (!props.product) {
        return;
    }

    qty.value = qty.value + packSize.value;
}

function findType(typeId?: number | null): ProductType | undefined {
    if (!typeId) {
        return undefined;
    }

    return props.product?.types?.find((t) => t.id === typeId);
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Package class="h-5 w-5" />
                    {{ t('pageDealer.products.stockIn.title') }}
                </DialogTitle>
                <DialogDescription v-if="product">
                    {{ product.name }}
                    <span v-if="!hasTypes"
                        >{{
                            t('pageDealer.products.stockIn.currentStockLabel')
                        }}
                        <span class="font-mono font-medium"
                            >{{ product.stock }}
                            {{ unitLabel(product.unit) }}</span
                        ></span
                    >
                    <span v-else class="text-xs">{{
                        t('pageDealer.products.stockIn.perTypeHint')
                    }}</span>
                </DialogDescription>
            </DialogHeader>

            <form id="stockInForm" @submit.prevent="submit" class="space-y-4">
                <div
                    v-if="suppliers.length === 0"
                    class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm"
                >
                    {{ t('pageDealer.products.stockIn.addSupplierFirst') }}
                    <a
                        href="/dealer/suppliers/create"
                        class="font-medium underline"
                        >{{ t('pageDealer.products.stockIn.newSupplier') }}</a
                    >.
                </div>

                <div v-else>
                    <Label class="mb-1.5 flex items-center gap-1.5">
                        <Truck class="h-3.5 w-3.5 text-muted-foreground" />
                        {{ t('pageDealer.products.stockIn.supplierLabel') }}
                        <span class="text-destructive">*</span>
                    </Label>
                    <SearchableSelect
                        v-model="form.supplier_id as any"
                        :items="suppliers"
                        value-key="id"
                        label-key="name"
                        :placeholder="
                            t('pageDealer.products.stockIn.supplierPlaceholder')
                        "
                        :search-placeholder="
                            t(
                                'pageDealer.products.stockIn.supplierSearchPlaceholder',
                            )
                        "
                        :empty-text="t('pageDealer.products.stockIn.notFound')"
                    />
                    <InputError :message="form.errors.supplier_id" />
                </div>

                <!-- Per-type lines -->
                <template v-if="hasTypes">
                    <div class="max-h-[40vh] space-y-3 overflow-y-auto">
                        <div
                            v-for="(line, idx) in form.items"
                            :key="line.product_type_id ?? idx"
                            class="rounded-lg border p-3"
                        >
                            <div class="mb-2 flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold">
                                        {{
                                            findType(line.product_type_id)?.name
                                        }}
                                    </p>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        t(
                                            'pageDealer.products.stockIn.currentLabel',
                                        )
                                    }}
                                    <span class="font-mono">{{
                                        findType(line.product_type_id)?.stock ??
                                        0
                                    }}</span>
                                </p>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <Label class="mb-1 text-xs">{{
                                        t(
                                            'pageDealer.products.stockIn.qtyLabel',
                                        )
                                    }}</Label>
                                    <Input
                                        type="number"
                                        v-model.number="line.qty"
                                        min="0"
                                        placeholder="0"
                                    />
                                    <InputError
                                        :message="
                                            form.errors[`items.${idx}.qty`]
                                        "
                                    />
                                </div>
                                <div>
                                    <Label class="mb-1 text-xs"
                                        >{{
                                            t(
                                                'pageDealer.products.stockIn.costLabel',
                                            )
                                        }}
                                        <span class="text-destructive"
                                            >*</span
                                        ></Label
                                    >
                                    <Input
                                        type="number"
                                        step="0.01"
                                        v-model.number="line.unit_cost"
                                        min="0"
                                    />
                                    <InputError
                                        :message="
                                            form.errors[
                                                `items.${idx}.unit_cost`
                                            ]
                                        "
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div>
                        <Label for="qty" class="mb-1.5">
                            {{
                                t('pageDealer.products.stockIn.qtyLabelUnit', {
                                    unit: unitLabel(product?.unit ?? ''),
                                })
                            }}
                            <span class="text-destructive">*</span>
                        </Label>
                        <div class="flex gap-2">
                            <Input
                                id="qty"
                                type="number"
                                v-model.number="qty"
                                min="1"
                                required
                                autofocus
                                placeholder="0"
                            />
                            <Button
                                v-if="hasPack"
                                type="button"
                                variant="outline"
                                @click="addByPack"
                            >
                                {{
                                    t(
                                        'pageDealer.products.stockIn.addPackButton',
                                        { size: packSize },
                                    )
                                }}
                            </Button>
                        </div>
                        <p
                            v-if="packEquivalent"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            ≈ {{ packEquivalent }}
                        </p>
                        <InputError :message="form.errors['items.0.qty']" />
                    </div>

                    <div>
                        <Label for="unit_cost" class="mb-1.5">
                            {{
                                t('pageDealer.products.stockIn.costLabelUnit', {
                                    unit: unitLabel(product?.unit ?? ''),
                                })
                            }}
                            <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            id="unit_cost"
                            type="number"
                            step="0.01"
                            v-model.number="unitCost"
                            min="0.01"
                            required
                            :placeholder="
                                t('pageDealer.products.stockIn.costPlaceholder')
                            "
                        />
                        <p
                            v-if="totalCost !== null"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            {{
                                t('pageDealer.products.stockIn.totalCostLabel')
                            }}
                            <span class="font-mono">{{
                                formatMoney(totalCost)
                            }}</span>
                            {{ symbol }}
                            {{ t('pageDealer.products.stockIn.debtNote') }}
                        </p>
                        <InputError
                            :message="form.errors['items.0.unit_cost']"
                        />
                    </div>

                    <div
                        v-if="qty > 0 && product"
                        class="rounded-lg border bg-muted/30 p-3 text-sm"
                    >
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">{{
                                t('pageDealer.products.stockIn.newStockLabel')
                            }}</span>
                            <span class="font-mono font-semibold">
                                {{ product.stock + qty }}
                                {{ unitLabel(product.unit) }}
                            </span>
                        </div>
                    </div>
                </template>

                <div
                    v-if="grandTotal !== null && grandTotal > 0"
                    class="space-y-2 rounded-lg border bg-muted/30 p-3"
                >
                    <div class="flex items-center justify-between">
                        <Label class="text-sm"
                            >{{ t('pageDealer.products.stockIn.payNowLabel') }}
                            <span
                                class="text-xs font-normal text-muted-foreground"
                                >{{
                                    t('pageDealer.products.stockIn.optional')
                                }}</span
                            ></Label
                        >
                        <span class="font-mono text-xs text-muted-foreground"
                            >{{ t('pageDealer.products.stockIn.totalShort') }}
                            {{ formatMoney(grandTotal) }}</span
                        >
                    </div>
                    <div class="flex gap-2">
                        <Input
                            type="number"
                            v-model.number="form.paid_amount"
                            min="0"
                            placeholder="0"
                            class="font-mono"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="shrink-0"
                            @click="form.paid_amount = grandTotal"
                        >
                            {{ t('pageDealer.products.stockIn.payAll') }}
                        </Button>
                    </div>
                    <InputError :message="form.errors.paid_amount" />

                    <div v-if="(form.paid_amount ?? 0) > 0">
                        <div
                            class="grid grid-cols-2 gap-2 rounded-md border p-1"
                        >
                            <button
                                type="button"
                                class="rounded py-1.5 text-xs font-medium transition-colors"
                                :class="
                                    form.payment_method === 'cash'
                                        ? 'bg-primary/10 text-primary ring-1 ring-primary/30'
                                        : 'text-muted-foreground hover:bg-muted/50'
                                "
                                @click="form.payment_method = 'cash'"
                            >
                                {{ t('pageDealer.products.stockIn.cash') }}
                            </button>
                            <button
                                type="button"
                                class="rounded py-1.5 text-xs font-medium transition-colors"
                                :class="
                                    form.payment_method === 'card'
                                        ? 'bg-primary/10 text-primary ring-1 ring-primary/30'
                                        : 'text-muted-foreground hover:bg-muted/50'
                                "
                                @click="form.payment_method = 'card'"
                            >
                                {{ t('pageDealer.products.stockIn.card') }}
                            </button>
                        </div>
                        <Input
                            v-if="form.payment_method === 'card'"
                            v-model="form.cardholder_name"
                            class="mt-2"
                            :placeholder="
                                t(
                                    'pageDealer.products.stockIn.cardholderPlaceholder',
                                )
                            "
                        />
                        <InputError :message="form.errors.cardholder_name" />
                    </div>

                    <div
                        v-if="remainingDebt !== null"
                        class="flex justify-between border-t pt-2 text-xs"
                    >
                        <span class="text-muted-foreground">{{
                            t('pageDealer.products.stockIn.remainingDebtLabel')
                        }}</span>
                        <span
                            class="font-mono font-semibold"
                            :class="
                                remainingDebt > 0
                                    ? 'text-rose-600'
                                    : 'text-emerald-600'
                            "
                        >
                            {{ formatMoney(remainingDebt) }} {{ symbol }}
                        </span>
                    </div>
                </div>

                <div>
                    <Label for="note" class="mb-1.5">{{
                        t('pageDealer.products.stockIn.noteLabel')
                    }}</Label>
                    <Input
                        id="note"
                        v-model="form.note"
                        :placeholder="
                            t('pageDealer.products.stockIn.notePlaceholder')
                        "
                        maxlength="500"
                    />
                    <InputError :message="form.errors.note" />
                </div>
            </form>

            <DialogFooter>
                <Button variant="outline" type="button" @click="close">{{
                    t('pageDealer.products.stockIn.cancel')
                }}</Button>
                <Button
                    type="submit"
                    form="stockInForm"
                    :disabled="
                        form.processing ||
                        filledCount === 0 ||
                        !form.supplier_id
                    "
                >
                    <Spinner v-if="form.processing" class="mr-2" />
                    {{ t('pageDealer.products.stockIn.submit') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
