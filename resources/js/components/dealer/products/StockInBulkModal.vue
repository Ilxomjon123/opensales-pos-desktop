<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Package, Trash2, Truck } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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
import type { Product } from '@/types';

const props = defineProps<{
    open: boolean;
    products: Product[];
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
    product_type_id: number | null;
    qty: number;
    unit_cost: number | null;
};

type Entry = {
    key: string;
    product_id: number;
    product_type_id: number | null;
    name: string;
    code: string | null;
    stock: number;
    unit: string;
};

function entryKey(productId: number, typeId: number | null): string {
    return `${productId}:${typeId ?? 0}`;
}

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

const picker = ref<string | null>(null);

const flatEntries = computed<Entry[]>(() => {
    const out: Entry[] = [];

    props.products.forEach((p) => {
        if (p.has_types && (p.types?.length ?? 0) > 0) {
            (p.types ?? []).forEach((t) => {
                out.push({
                    key: entryKey(p.id, t.id),
                    product_id: p.id,
                    product_type_id: t.id,
                    name: `${p.name} — ${t.name}`,
                    code: t.code,
                    stock: t.stock,
                    unit: p.unit,
                });
            });

            return;
        }

        out.push({
            key: entryKey(p.id, null),
            product_id: p.id,
            product_type_id: null,
            name: p.name,
            code: null,
            stock: p.stock,
            unit: p.unit,
        });
    });

    return out;
});

const selectedKeys = computed(
    () =>
        new Set(
            form.items.map((i) => entryKey(i.product_id, i.product_type_id)),
        ),
);

const availableEntries = computed(() =>
    flatEntries.value.filter((e) => !selectedKeys.value.has(e.key)),
);

const grandTotalCost = computed(() => {
    let total = 0;
    let any = false;
    form.items.forEach((line) => {
        if (line.unit_cost !== null && line.unit_cost > 0 && line.qty > 0) {
            total += line.unit_cost * line.qty;
            any = true;
        }
    });

    return any ? total : null;
});

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        form.reset();
        form.clearErrors();
        form.note = '';
        form.items = [];
        form.supplier_id =
            props.suppliers.length === 1 ? props.suppliers[0].id : null;
        form.paid_amount = null;
        form.payment_method = 'cash';
        form.cardholder_name = '';
        picker.value = null;
    },
);

const remainingDebtBulk = computed(() => {
    if (grandTotalCost.value === null) {
        return null;
    }

    return grandTotalCost.value - (form.paid_amount ?? 0);
});

function onPickEntry(value: string | number | null) {
    if (value === null || value === undefined) {
        return;
    }

    const key = String(value);
    const entry = flatEntries.value.find((e) => e.key === key);

    if (!entry || selectedKeys.value.has(key)) {
        return;
    }

    form.items.push({
        product_id: entry.product_id,
        product_type_id: entry.product_type_id,
        qty: 0,
        unit_cost: null,
    });
    picker.value = null;
}

function removeLine(idx: number) {
    form.items.splice(idx, 1);
}

function close() {
    emit('update:open', false);
}

function submit() {
    if (form.items.length === 0 || !form.supplier_id) {
        return;
    }

    const paid = form.paid_amount ?? 0;

    if (paid <= 0) {
        form.payment_method = 'cash';
        form.cardholder_name = '';
    } else if (form.payment_method !== 'card') {
        form.cardholder_name = '';
    }

    form.post('/dealer/stock-transactions', {
        preserveScroll: true,
        onSuccess: () => {
            emit('success');
            close();
        },
    });
}

function formatMoney(amount: number): string {
    return String(Math.round(amount)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

function entryFor(line: Line): Entry | undefined {
    return flatEntries.value.find(
        (e) =>
            e.product_id === line.product_id &&
            e.product_type_id === line.product_type_id,
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent
            class="flex max-h-[calc(100dvh-2rem)] flex-col gap-3 p-4 sm:max-w-xl sm:gap-4 sm:p-6"
        >
            <DialogHeader>
                <DialogTitle
                    class="flex items-center gap-2 pr-8 text-base sm:text-lg"
                >
                    <Package class="h-5 w-5" />
                    {{ t('pageDealer.products.stockInBulk.title') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('pageDealer.products.stockInBulk.description') }}
                </DialogDescription>
            </DialogHeader>

            <form
                id="bulkStockInForm"
                @submit.prevent="submit"
                class="-mx-4 flex-1 space-y-4 overflow-y-auto px-4 sm:-mx-6 sm:px-6"
            >
                <div
                    v-if="suppliers.length === 0"
                    class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-sm"
                >
                    {{ t('pageDealer.products.stockInBulk.addSupplierFirst') }}
                    <a
                        href="/dealer/suppliers/create"
                        class="font-medium underline"
                        >{{
                            t('pageDealer.products.stockInBulk.newSupplier')
                        }}</a
                    >.
                </div>

                <div v-else>
                    <Label class="mb-1.5 flex items-center gap-1.5">
                        <Truck class="h-3.5 w-3.5 text-muted-foreground" />
                        {{ t('pageDealer.products.stockInBulk.supplierLabel') }}
                        <span class="text-destructive">*</span>
                    </Label>
                    <SearchableSelect
                        v-model="form.supplier_id as any"
                        :items="suppliers"
                        value-key="id"
                        label-key="name"
                        :placeholder="
                            t(
                                'pageDealer.products.stockInBulk.supplierPlaceholder',
                            )
                        "
                        :search-placeholder="
                            t(
                                'pageDealer.products.stockInBulk.supplierSearchPlaceholder',
                            )
                        "
                        :empty-text="
                            t('pageDealer.products.stockInBulk.notFound')
                        "
                    />
                    <InputError :message="form.errors.supplier_id" />
                </div>

                <div>
                    <Label class="mb-1.5">{{
                        t('pageDealer.products.stockInBulk.pickLabel')
                    }}</Label>
                    <SearchableSelect
                        :model-value="picker"
                        :items="availableEntries"
                        value-key="key"
                        label-key="name"
                        :placeholder="
                            t('pageDealer.products.stockInBulk.pickPlaceholder')
                        "
                        :search-placeholder="
                            t(
                                'pageDealer.products.stockInBulk.pickSearchPlaceholder',
                            )
                        "
                        :empty-text="
                            t('pageDealer.products.stockInBulk.pickEmptyText')
                        "
                        :clearable="false"
                        @update:model-value="onPickEntry"
                    >
                        <template #item="{ item }">
                            <div class="flex min-w-0 flex-col">
                                <span class="truncate font-medium">{{
                                    item.name
                                }}</span>
                                <span class="text-xs text-muted-foreground">
                                    {{
                                        t(
                                            'pageDealer.products.stockInBulk.stockLabel',
                                        )
                                    }}
                                    {{ item.stock }} {{ unitLabel(item.unit) }}
                                    <span
                                        v-if="item.code"
                                        class="ml-1 font-mono"
                                        >· {{ item.code }}</span
                                    >
                                </span>
                            </div>
                        </template>
                    </SearchableSelect>
                </div>

                <div v-if="form.items.length > 0" class="space-y-2">
                    <div
                        v-for="(line, idx) in form.items"
                        :key="`${line.product_id}:${line.product_type_id ?? 0}`"
                        class="rounded-lg border bg-card p-3"
                    >
                        <div class="flex items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">
                                    {{ entryFor(line)?.name }}
                                </p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{
                                        t(
                                            'pageDealer.products.stockInBulk.currentStockLabel',
                                        )
                                    }}
                                    {{ entryFor(line)?.stock }}
                                    {{ unitLabel(entryFor(line)?.unit) }}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8 shrink-0 text-destructive"
                                @click="removeLine(idx)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>

                        <div
                            class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                        >
                            <div>
                                <Label
                                    :for="`qty-${idx}`"
                                    class="mb-1 text-xs text-muted-foreground"
                                >
                                    {{
                                        t(
                                            'pageDealer.products.stockInBulk.qtyLabel',
                                            {
                                                unit: unitLabel(
                                                    entryFor(line)?.unit,
                                                ),
                                            },
                                        )
                                    }}
                                </Label>
                                <Input
                                    :id="`qty-${idx}`"
                                    type="number"
                                    v-model.number="line.qty"
                                    min="1"
                                    placeholder="0"
                                />
                                <InputError
                                    :message="
                                        form.errors[
                                            `items.${idx}.qty` as keyof typeof form.errors
                                        ]
                                    "
                                />
                            </div>
                            <div>
                                <Label
                                    :for="`cost-${idx}`"
                                    class="mb-1 text-xs text-muted-foreground"
                                >
                                    {{
                                        t(
                                            'pageDealer.products.stockInBulk.costLabel',
                                        )
                                    }}
                                    <span class="text-destructive">*</span>
                                </Label>
                                <Input
                                    :id="`cost-${idx}`"
                                    type="number"
                                    step="0.01"
                                    v-model.number="line.unit_cost"
                                    min="0.01"
                                    placeholder="0"
                                />
                                <InputError
                                    :message="
                                        form.errors[
                                            `items.${idx}.unit_cost` as keyof typeof form.errors
                                        ]
                                    "
                                />
                            </div>
                            <div class="text-right text-xs sm:min-w-[100px]">
                                <p class="text-muted-foreground">
                                    {{
                                        t(
                                            'pageDealer.products.stockInBulk.lineTotal',
                                        )
                                    }}
                                </p>
                                <p class="font-mono font-semibold">
                                    <span
                                        v-if="
                                            line.unit_cost !== null &&
                                            line.unit_cost > 0 &&
                                            line.qty > 0
                                        "
                                    >
                                        {{
                                            formatMoney(
                                                line.unit_cost * line.qty,
                                            )
                                        }}
                                    </span>
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="grandTotalCost !== null"
                        class="flex items-center justify-between rounded-lg bg-muted/40 px-4 py-2.5 text-sm"
                    >
                        <span class="text-muted-foreground">{{
                            t('pageDealer.products.stockInBulk.grandTotalLabel')
                        }}</span>
                        <span class="font-mono font-semibold"
                            >{{ formatMoney(grandTotalCost) }}
                            {{ symbol }}</span
                        >
                    </div>

                    <div
                        v-if="grandTotalCost !== null && grandTotalCost > 0"
                        class="space-y-2 rounded-lg border bg-muted/30 p-3"
                    >
                        <Label class="text-sm">
                            {{
                                t('pageDealer.products.stockInBulk.payNowLabel')
                            }}
                            <span
                                class="text-xs font-normal text-muted-foreground"
                                >{{
                                    t(
                                        'pageDealer.products.stockInBulk.optional',
                                    )
                                }}</span
                            >
                        </Label>
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
                                @click="form.paid_amount = grandTotalCost"
                            >
                                {{
                                    t('pageDealer.products.stockInBulk.payAll')
                                }}
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
                                    {{
                                        t(
                                            'pageDealer.products.stockInBulk.cash',
                                        )
                                    }}
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
                                    {{
                                        t(
                                            'pageDealer.products.stockInBulk.card',
                                        )
                                    }}
                                </button>
                            </div>
                            <Input
                                v-if="form.payment_method === 'card'"
                                v-model="form.cardholder_name"
                                class="mt-2"
                                :placeholder="
                                    t(
                                        'pageDealer.products.stockInBulk.cardholderPlaceholder',
                                    )
                                "
                            />
                            <InputError
                                :message="form.errors.cardholder_name"
                            />
                        </div>

                        <div
                            v-if="remainingDebtBulk !== null"
                            class="flex justify-between border-t pt-2 text-xs"
                        >
                            <span class="text-muted-foreground">{{
                                t(
                                    'pageDealer.products.stockInBulk.remainingDebtLabel',
                                )
                            }}</span>
                            <span
                                class="font-mono font-semibold"
                                :class="
                                    remainingDebtBulk > 0
                                        ? 'text-rose-600'
                                        : 'text-emerald-600'
                                "
                            >
                                {{ formatMoney(remainingDebtBulk) }}
                                {{ symbol }}
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-lg border border-dashed p-6 text-center"
                >
                    <Package class="mx-auto h-8 w-8 text-muted-foreground/40" />
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t('pageDealer.products.stockInBulk.emptyHint') }}
                    </p>
                </div>

                <div>
                    <Label for="bulk-note" class="mb-1.5">{{
                        t('pageDealer.products.stockInBulk.noteLabel')
                    }}</Label>
                    <Input
                        id="bulk-note"
                        v-model="form.note"
                        :placeholder="
                            t('pageDealer.products.stockInBulk.notePlaceholder')
                        "
                        maxlength="500"
                    />
                    <InputError :message="form.errors.note" />
                </div>
            </form>

            <DialogFooter>
                <Button variant="outline" type="button" @click="close">{{
                    t('pageDealer.products.stockInBulk.cancel')
                }}</Button>
                <Button
                    type="submit"
                    form="bulkStockInForm"
                    :disabled="
                        form.processing ||
                        form.items.length === 0 ||
                        !form.supplier_id ||
                        form.items.some((l) => l.qty <= 0 || !l.unit_cost)
                    "
                >
                    <Spinner v-if="form.processing" class="mr-2" />
                    <template v-if="!form.supplier_id">{{
                        t('pageDealer.products.stockInBulk.submitPickSupplier')
                    }}</template>
                    <template v-else-if="form.items.length === 0">{{
                        t('pageDealer.products.stockInBulk.submitPickProduct')
                    }}</template>
                    <template v-else>{{
                        t('pageDealer.products.stockInBulk.submitCount', {
                            n: form.items.length,
                        })
                    }}</template>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
