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
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useUnitLabel } from '@/composables/useUnitLabel';
import type { Order, OrderItem, Product } from '@/types';

type AssembleRow = {
    product_id: number;
    product_type_id: number | null;
    product_name: string;
    product_type_name: string | null;
    ordered_qty: number;
    ordered_pack_qty: number;
    pack_size: number;
    bulk_only: boolean;
    picked_pack_qty: number;
    picked_unit_qty: number;
    unit: string | null;
    is_new: boolean;
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

function rowKey(productId: number, productTypeId: number | null): string {
    return `${productId}:${productTypeId ?? 0}`;
}

function totalPicked(r: AssembleRow): number {
    const packs = Math.max(0, Number(r.picked_pack_qty) || 0);
    const units = Math.max(0, Number(r.picked_unit_qty) || 0);

    return packs * Math.max(1, r.pack_size) + units;
}

function orderedLooseQty(r: AssembleRow): number {
    return Math.max(0, r.ordered_qty - r.ordered_pack_qty * Math.max(1, r.pack_size));
}

const props = withDefaults(
    defineProps<{
        open: boolean;
        order: Order;
        catalog?: Product[];
        // 'assemble': boshlang'ich tayyorlash (so'ralgandan to'ldiriladi).
        // 'edit-picked': owner skladdan berilgan miqdorni tahrirlaydi
        // (joriy picked_qty'dan to'ldiriladi, /picked endpoint'ga yuboriladi).
        mode?: 'assemble' | 'edit-picked';
    }>(),
    { mode: 'assemble' },
);

const isEditPicked = computed(() => props.mode === 'edit-picked');

const { t } = useI18n();
const unitLabel = useUnitLabel();

const flattenedCatalog = computed<CatalogEntry[]>(() => {
    const out: CatalogEntry[] = [];

    (props.catalog ?? []).forEach((p) => {
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

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const rows = reactive<AssembleRow[]>([]);
const addEntryKey = ref<string | null>(null);
const processing = ref(false);
const error = ref<string | null>(null);

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        rows.splice(0, rows.length);

        (props.order.items ?? []).forEach((i: OrderItem) => {
            const packSize = Math.max(1, i.pack_size ?? 1);
            const orderedPackQty = Math.max(0, i.pack_qty ?? 0);
            const entry = flattenedCatalog.value.find(
                (e) => e.product_id === i.product_id && e.product_type_id === (i.product_type_id ?? null),
            );
            const bulkOnly = Boolean(entry?.bulk_only) && packSize > 1;
            let initialPackQty: number;
            let initialUnitQty: number;

            if (isEditPicked.value) {
                // Joriy skladdan berilgan miqdordan to'ldiramiz
                const pickedPack = Math.max(0, i.picked_pack_qty ?? 0);
                const pickedTotal = Number(i.picked_qty ?? 0);

                if (bulkOnly) {
                    initialPackQty = pickedPack;
                    initialUnitQty = 0;
                } else if (packSize > 1) {
                    initialPackQty = pickedPack;
                    initialUnitQty = Math.max(0, pickedTotal - pickedPack * packSize);
                } else {
                    initialPackQty = 0;
                    initialUnitQty = pickedTotal;
                }
            } else if (bulkOnly) {
                const minPacks = Math.ceil(i.qty / packSize);
                initialPackQty = Math.max(orderedPackQty, minPacks);
                initialUnitQty = 0;
            } else if (packSize > 1) {
                initialPackQty = orderedPackQty;
                initialUnitQty = Math.max(0, i.qty - orderedPackQty * packSize);
            } else {
                initialPackQty = 0;
                initialUnitQty = i.qty;
            }

            rows.push({
                product_id: i.product_id,
                product_type_id: i.product_type_id ?? null,
                product_name: i.product_name,
                product_type_name: i.product_type_name ?? null,
                ordered_qty: i.qty,
                ordered_pack_qty: orderedPackQty,
                pack_size: packSize,
                bulk_only: bulkOnly,
                picked_pack_qty: initialPackQty,
                picked_unit_qty: initialUnitQty,
                unit: i.unit,
                is_new: false,
            });
        });

        addEntryKey.value = null;
        error.value = null;
    },
    { immediate: true },
);

const availableToAdd = computed(() => flattenedCatalog.value.filter(
    (e) => !rows.some((r) => r.product_id === e.product_id && r.product_type_id === e.product_type_id),
));

function addProduct() {
    const key = addEntryKey.value;

    if (!key) {
        return;
    }

    const entry = flattenedCatalog.value.find((e) => e.key === key);

    if (!entry) {
        return;
    }

    if (rows.some((r) => rowKey(r.product_id, r.product_type_id) === key)) {
        error.value = 'Bu mahsulot ro\'yxatda bor';

        return;
    }

    const product = (props.catalog ?? []).find((p) => p.id === entry.product_id);
    const typeRow = entry.product_type_id !== null
        ? product?.types?.find((t) => t.id === entry.product_type_id)
        : null;

    const newBulkOnly = entry.bulk_only && entry.pack_size > 1;
    rows.push({
        product_id: entry.product_id,
        product_type_id: entry.product_type_id,
        product_name: product?.name ?? entry.name,
        product_type_name: typeRow?.name ?? null,
        ordered_qty: 0,
        ordered_pack_qty: 0,
        pack_size: entry.pack_size,
        bulk_only: newBulkOnly,
        picked_pack_qty: entry.pack_size > 1 ? 1 : 0,
        picked_unit_qty: newBulkOnly || entry.pack_size > 1 ? 0 : 1,
        unit: entry.unit,
        is_new: true,
    });
    addEntryKey.value = null;
    error.value = null;
}

function removeRow(idx: number) {
    rows.splice(idx, 1);
}

function fillFromOrdered() {
    rows.forEach((r) => {
        if (r.pack_size > 1) {
            r.picked_pack_qty = r.ordered_pack_qty;
            r.picked_unit_qty = Math.max(0, r.ordered_qty - r.ordered_pack_qty * r.pack_size);
        } else {
            r.picked_pack_qty = 0;
            r.picked_unit_qty = r.ordered_qty;
        }
    });
}

function clearAll() {
    rows.forEach((r) => {
        r.picked_pack_qty = 0;
        r.picked_unit_qty = 0;
    });
}

function submit() {
    processing.value = true;
    error.value = null;

    const payload = {
        items: rows.map((r) => ({
            product_id: r.product_id,
            product_type_id: r.product_type_id,
            picked_qty: totalPicked(r),
            picked_pack_qty: r.pack_size > 1 ? Math.max(0, Number(r.picked_pack_qty) || 0) : 0,
        })),
    };

    const url = isEditPicked.value
        ? `/dealer/orders/${props.order.id}/picked`
        : `/dealer/orders/${props.order.id}/assemble`;

    router.post(url, payload, {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
        onError: (e) => {
            error.value = Object.values(e).join(', ') || t('pageDealer.assembleModal.errorFallback');
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent
            class="flex max-h-[calc(100dvh-2rem)] flex-col gap-3 p-4 sm:max-w-3xl sm:gap-4 sm:p-6"
            @open-auto-focus="(e: Event) => e.preventDefault()"
            @pointer-down-outside="(e: Event) => {
                const target = e.target as HTMLElement | null;
                if (target?.closest('[data-searchable-select-popup]')) {
                    e.preventDefault();
                }
            }"
            @interact-outside="(e: Event) => {
                const target = e.target as HTMLElement | null;
                if (target?.closest('[data-searchable-select-popup]')) {
                    e.preventDefault();
                }
            }"
        >
            <DialogHeader>
                <DialogTitle class="pr-8 text-base sm:text-lg">
                    {{ isEditPicked
                        ? t('pageDealer.assembleModal.editPickedTitle', { number: order.number })
                        : t('pageDealer.assembleModal.title', { number: order.number }) }}
                </DialogTitle>
            </DialogHeader>

            <div class="-mx-4 flex-1 space-y-3 overflow-y-auto px-4 sm:-mx-6 sm:px-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                    <span class="text-xs text-muted-foreground">
                        {{ t('pageDealer.assembleModal.desc') }}
                    </span>
                    <div class="flex shrink-0 gap-1.5 text-xs">
                        <button type="button" class="rounded border px-2 py-1 hover:bg-muted" @click="fillFromOrdered">
                            {{ t('pageDealer.assembleModal.fillFromOrdered') }}
                        </button>
                        <button type="button" class="rounded border px-2 py-1 hover:bg-muted" @click="clearAll">
                            {{ t('pageDealer.assembleModal.clearAll') }}
                        </button>
                    </div>
                </div>

                <div class="rounded-lg border">
                    <!-- Desktop table -->
                    <table class="hidden w-full text-left text-sm sm:table">
                        <thead class="border-b bg-muted/40">
                            <tr>
                                <th class="px-3 py-2 font-medium">{{ t('pageDealer.assembleModal.colProduct') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.assembleModal.colOrder') }}</th>
                                <th class="w-64 px-3 py-2 text-right font-medium">{{ t('pageDealer.assembleModal.colPicked') }}</th>
                                <th class="px-3 py-2 text-right font-medium">{{ t('pageDealer.assembleModal.colTotal') }}</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="(r, idx) in rows"
                                :key="idx"
                                :class="r.is_new ? 'bg-emerald-50/40 dark:bg-emerald-950/20' : ''"
                            >
                                <td class="px-3 py-2">
                                    {{ r.product_name }}
                                    <span v-if="r.product_type_name" class="text-muted-foreground">
                                        — {{ r.product_type_name }}
                                    </span>
                                    <span v-if="r.is_new" class="ml-1 text-xs text-emerald-600">{{ t('pageDealer.assembleModal.newBadge') }}</span>
                                </td>
                                <td class="px-3 py-2 text-right text-muted-foreground">
                                    <div>{{ r.ordered_qty }} {{ unitLabel(r.unit) }}</div>
                                    <div v-if="r.pack_size > 1 && r.ordered_pack_qty > 0" class="text-xs">
                                        <template v-if="orderedLooseQty(r) > 0">
                                            {{ r.ordered_pack_qty }} {{ t('pageDealer.assembleModal.blok') }} + {{ orderedLooseQty(r) }} {{ unitLabel(r.unit) }}
                                        </template>
                                        <template v-else>
                                            {{ r.ordered_pack_qty }} {{ t('pageDealer.assembleModal.blok') }} × {{ r.pack_size }}
                                        </template>
                                    </div>
                                </td>
                                <td class="px-3 py-2">
                                    <div v-if="r.pack_size > 1" class="flex items-center justify-end gap-1.5">
                                        <div class="flex items-center gap-1">
                                            <Input
                                                type="number"
                                                min="0"
                                                v-model.number="r.picked_pack_qty"
                                                class="h-8 w-16 text-right"
                                            />
                                            <span class="text-xs text-muted-foreground">{{ t('pageDealer.assembleModal.blok') }}</span>
                                        </div>
                                        <template v-if="!r.bulk_only">
                                            <span class="text-muted-foreground">+</span>
                                            <div class="flex items-center gap-1">
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    v-model.number="r.picked_unit_qty"
                                                    class="h-8 w-16 text-right"
                                                />
                                                <span class="text-xs text-muted-foreground">{{ unitLabel(r.unit) }}</span>
                                            </div>
                                        </template>
                                    </div>
                                    <Input
                                        v-else
                                        type="number"
                                        min="0"
                                        v-model.number="r.picked_unit_qty"
                                        class="h-8 text-right"
                                    />
                                </td>
                                <td class="px-3 py-2 text-right font-mono text-sm">
                                    {{ totalPicked(r) }} {{ unitLabel(r.unit) }}
                                </td>
                                <td class="px-2 py-2">
                                    <Button
                                        v-if="r.is_new"
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
                                <td colspan="5" class="px-3 py-4 text-center text-muted-foreground">
                                    {{ t('pageDealer.assembleModal.noItems') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Mobile list -->
                    <div class="flex flex-col divide-y sm:hidden">
                        <div
                            v-for="(r, idx) in rows"
                            :key="`m-${idx}`"
                            class="flex flex-col gap-2 p-3"
                            :class="r.is_new ? 'bg-emerald-50/40 dark:bg-emerald-950/20' : ''"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="break-words font-medium">
                                        {{ r.product_name }}
                                        <span v-if="r.product_type_name" class="text-muted-foreground">
                                            — {{ r.product_type_name }}
                                        </span>
                                    </p>
                                    <p v-if="r.is_new" class="text-xs text-emerald-600">{{ t('pageDealer.assembleModal.newBadge') }}</p>
                                </div>
                                <Button
                                    v-if="r.is_new"
                                    variant="ghost"
                                    size="icon"
                                    class="h-7 w-7 shrink-0 text-destructive"
                                    @click="removeRow(idx)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </Button>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs text-muted-foreground">
                                    {{ t('pageDealer.assembleModal.orderedLabel') }}: {{ r.ordered_qty }} {{ unitLabel(r.unit) }}
                                    <span v-if="r.pack_size > 1 && r.ordered_pack_qty > 0">
                                        ({{ r.ordered_pack_qty }} {{ t('pageDealer.assembleModal.blok') }} × {{ r.pack_size }})
                                    </span>
                                </p>
                            </div>
                            <div v-if="r.pack_size > 1" class="grid gap-2" :class="r.bulk_only ? 'grid-cols-1' : 'grid-cols-2'">
                                <div>
                                    <Label :for="`a-pack-${idx}`" class="text-xs text-muted-foreground">
                                        {{ t('pageDealer.assembleModal.pickedPackLabel') }}
                                    </Label>
                                    <Input
                                        :id="`a-pack-${idx}`"
                                        type="number"
                                        min="0"
                                        v-model.number="r.picked_pack_qty"
                                        class="mt-1 h-9 text-right"
                                    />
                                </div>
                                <div v-if="!r.bulk_only">
                                    <Label :for="`a-unit-${idx}`" class="text-xs text-muted-foreground">
                                        {{ t('pageDealer.assembleModal.pickedUnitLabel', { unit: unitLabel(r.unit ?? 'dona') }) }}
                                    </Label>
                                    <Input
                                        :id="`a-unit-${idx}`"
                                        type="number"
                                        min="0"
                                        v-model.number="r.picked_unit_qty"
                                        class="mt-1 h-9 text-right"
                                    />
                                </div>
                            </div>
                            <div v-else class="flex items-center gap-2">
                                <Label :for="`a-qty-${idx}`" class="text-xs text-muted-foreground">{{ t('pageDealer.assembleModal.pickedLabel') }}</Label>
                                <Input
                                    :id="`a-qty-${idx}`"
                                    type="number"
                                    min="0"
                                    v-model.number="r.picked_unit_qty"
                                    class="h-9 flex-1 text-right"
                                />
                                <span v-if="r.unit" class="shrink-0 text-xs text-muted-foreground">{{ unitLabel(r.unit) }}</span>
                            </div>
                            <div class="text-right text-xs text-muted-foreground">
                                {{ t('pageDealer.assembleModal.totalPicked') }}:
                                <span class="font-mono font-medium text-foreground">
                                    {{ totalPicked(r) }} {{ unitLabel(r.unit) }}
                                </span>
                            </div>
                        </div>
                        <div v-if="rows.length === 0" class="p-4 text-center text-sm text-muted-foreground">
                            {{ t('pageDealer.assembleModal.noItems') }}
                        </div>
                    </div>
                </div>

                <div v-if="(catalog?.length ?? 0) > 0" class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <Label class="mb-1.5">{{ t('pageDealer.assembleModal.addExtraLabel') }}</Label>
                        <SearchableSelect
                            v-model="addEntryKey"
                            :items="availableToAdd"
                            value-key="key"
                            label-key="name"
                            :placeholder="t('pageDealer.assembleModal.addExtraPlaceholder')"
                            :search-placeholder="t('pageDealer.assembleModal.addExtraSearch')"
                            :empty-text="t('pageDealer.assembleModal.addExtraEmpty')"
                        />
                    </div>
                    <Button type="button" variant="outline" class="w-full sm:w-auto" :disabled="!addEntryKey" @click="addProduct">
                        <Plus class="mr-1 h-4 w-4" />
                        {{ t('pageDealer.assembleModal.addExtraBtn') }}
                    </Button>
                </div>

                <p v-if="error" class="rounded bg-destructive/10 px-3 py-2 text-sm text-destructive">
                    {{ error }}
                </p>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">{{ t('pageDealer.assembleModal.cancel') }}</Button>
                <Button :disabled="processing || rows.length === 0" @click="submit">
                    {{ processing ? t('pageDealer.assembleModal.saving') : t('pageDealer.assembleModal.submit') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
