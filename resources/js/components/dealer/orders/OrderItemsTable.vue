<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useCurrency } from '@/composables/useCurrency';
import { useUnitLabel } from '@/composables/useUnitLabel';
import type { Order, OrderItem } from '@/types';

const props = defineProps<{
    order: Order;
}>();

const { t } = useI18n();
const unitLabel = useUnitLabel();
const { formatWithSymbol } = useCurrency();

type DisplayStage = 'ordered' | 'prepared' | 'delivered';

const displayStage = computed<DisplayStage>(() => {
    const s = props.order.status;

    if (s === 'delivered' || s === 'received') {
        return 'delivered';
    }

    if (s === 'assembling' || s === 'delivering') {
        return 'prepared';
    }

    return 'ordered';
});

function itemSubtotal(item: OrderItem): number {
    if (displayStage.value === 'delivered') {
        return item.delivered_subtotal;
    }

    if (displayStage.value === 'prepared') {
        return item.prepared_subtotal;
    }

    return item.subtotal;
}

function itemSubtotalChanged(item: OrderItem): boolean {
    return (
        displayStage.value !== 'ordered' && itemSubtotal(item) !== item.subtotal
    );
}

const orderTotal = computed<number>(() => {
    if (displayStage.value === 'delivered') {
        return props.order.delivered_total ?? 0;
    }

    if (displayStage.value === 'prepared') {
        return (
            props.order.display_total ??
            props.order.prepared_total ??
            props.order.total
        );
    }

    return props.order.total;
});

const orderTotalChanged = computed<boolean>(
    () =>
        displayStage.value !== 'ordered' &&
        orderTotal.value !== props.order.total,
);

const emit = defineEmits<{ 'view-product': [productId: number] }>();

const isOpen = ref(true);

function packSize(item: OrderItem): number {
    return Math.max(1, item.pack_size ?? 1);
}

function looseQty(
    item: OrderItem,
    total: number,
    packQty: number | null | undefined,
): number {
    const packs = Math.max(0, packQty ?? 0);

    return Math.max(0, total - packs * packSize(item));
}

function hasPickedInfo(item: OrderItem): boolean {
    return (item.picked_qty ?? 0) > 0;
}

function hasReturnedInfo(item: OrderItem): boolean {
    return (item.returned_qty ?? 0) > 0;
}

function hasDeliveredInfo(item: OrderItem): boolean {
    return (item.delivered_qty ?? 0) > 0;
}

function hasCarryInfo(item: OrderItem): boolean {
    return (item.carry_qty ?? 0) > 0;
}

function packBreakdown(
    item: OrderItem,
    total: number,
    packQty: number | null | undefined,
): string {
    const packs = Math.max(0, packQty ?? 0);

    if (packs <= 0 || packSize(item) <= 1) {
        return '';
    }

    const loose = looseQty(item, total, packs);
    const blok = t('pageDealer.orders.blockUnit');

    if (loose > 0) {
        return `${packs} ${blok} + ${loose} ${item.unit}`;
    }

    return `${packs} ${blok} × ${packSize(item)}`;
}
</script>

<template>
    <Card :class="isOpen ? 'gap-3 py-3' : 'gap-0 py-0'">
        <CardHeader class="px-3 sm:px-4">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-2 py-2 text-left"
                :aria-expanded="isOpen"
                @click="isOpen = !isOpen"
            >
                <CardTitle class="text-sm">{{
                    t('pageDealer.orders.orderContents')
                }}</CardTitle>
                <ChevronDown
                    class="h-4 w-4 shrink-0 text-muted-foreground transition-transform"
                    :class="isOpen ? 'rotate-180' : ''"
                />
            </button>
        </CardHeader>
        <CardContent v-show="isOpen" class="p-0">
            <!-- Desktop table -->
            <div class="hidden sm:block">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="px-4 py-3 font-medium">
                                {{ t('pageDealer.orders.productCol') }}
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                {{ t('pageDealer.orders.price') }}
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                {{ t('pageDealer.orders.quantity') }}
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                {{ t('pageDealer.orders.total') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="item in order.items"
                            :key="item.id"
                            class="align-top"
                        >
                            <td class="px-4 py-3">
                                <button
                                    type="button"
                                    class="text-left font-medium hover:text-primary hover:underline"
                                    @click="
                                        emit('view-product', item.product_id)
                                    "
                                >
                                    {{ item.product_name }}
                                </button>
                                <span
                                    v-if="item.product_type_name"
                                    class="text-muted-foreground"
                                >
                                    — {{ item.product_type_name }}</span
                                >
                            </td>
                            <td class="px-4 py-3 text-right font-mono">
                                {{ formatWithSymbol(item.price) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-col items-end gap-0.5">
                                    <div class="flex items-baseline gap-2">
                                        <span
                                            class="text-[11px] tracking-wide text-muted-foreground uppercase"
                                            >{{
                                                t('pageDealer.orders.requested')
                                            }}</span
                                        >
                                        <span class="font-mono"
                                            >{{ item.qty }}
                                            <span
                                                class="text-xs text-muted-foreground"
                                                >{{
                                                    unitLabel(item.unit)
                                                }}</span
                                            ></span
                                        >
                                    </div>
                                    <div
                                        v-if="
                                            packBreakdown(
                                                item,
                                                item.qty,
                                                item.pack_qty,
                                            )
                                        "
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        {{
                                            packBreakdown(
                                                item,
                                                item.qty,
                                                item.pack_qty,
                                            )
                                        }}
                                    </div>

                                    <div
                                        v-if="hasPickedInfo(item)"
                                        class="flex items-baseline gap-2"
                                    >
                                        <span
                                            class="text-[11px] tracking-wide text-sky-600 uppercase dark:text-sky-400"
                                            >{{
                                                t('pageDealer.orders.fromStock')
                                            }}</span
                                        >
                                        <span class="font-mono"
                                            >{{ item.picked_qty }}
                                            <span
                                                class="text-xs text-muted-foreground"
                                                >{{
                                                    unitLabel(item.unit)
                                                }}</span
                                            ></span
                                        >
                                    </div>
                                    <div
                                        v-if="
                                            hasPickedInfo(item) &&
                                            packBreakdown(
                                                item,
                                                item.picked_qty ?? 0,
                                                item.picked_pack_qty,
                                            )
                                        "
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        {{
                                            packBreakdown(
                                                item,
                                                item.picked_qty ?? 0,
                                                item.picked_pack_qty,
                                            )
                                        }}
                                    </div>

                                    <div
                                        v-if="hasDeliveredInfo(item)"
                                        class="flex items-baseline gap-2"
                                    >
                                        <span
                                            class="text-[11px] tracking-wide text-emerald-600 uppercase dark:text-emerald-400"
                                            >{{
                                                t('pageDealer.orders.delivered')
                                            }}</span
                                        >
                                        <span class="font-mono font-medium"
                                            >{{ item.delivered_qty }}
                                            <span
                                                class="text-xs text-muted-foreground"
                                                >{{
                                                    unitLabel(item.unit)
                                                }}</span
                                            ></span
                                        >
                                    </div>
                                    <div
                                        v-if="
                                            hasDeliveredInfo(item) &&
                                            packBreakdown(
                                                item,
                                                item.delivered_qty,
                                                item.delivered_pack_qty,
                                            )
                                        "
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        {{
                                            packBreakdown(
                                                item,
                                                item.delivered_qty,
                                                item.delivered_pack_qty,
                                            )
                                        }}
                                    </div>

                                    <div
                                        v-if="hasReturnedInfo(item)"
                                        class="flex items-baseline gap-2"
                                    >
                                        <span
                                            class="text-[11px] tracking-wide text-rose-600 uppercase dark:text-rose-400"
                                            >{{
                                                t('pageDealer.orders.returned')
                                            }}</span
                                        >
                                        <span class="font-mono"
                                            >{{ item.returned_qty }}
                                            <span
                                                class="text-xs text-muted-foreground"
                                                >{{
                                                    unitLabel(item.unit)
                                                }}</span
                                            ></span
                                        >
                                    </div>
                                    <div
                                        v-if="
                                            hasReturnedInfo(item) &&
                                            packBreakdown(
                                                item,
                                                item.returned_qty,
                                                item.returned_pack_qty,
                                            )
                                        "
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        {{
                                            packBreakdown(
                                                item,
                                                item.returned_qty,
                                                item.returned_pack_qty,
                                            )
                                        }}
                                    </div>

                                    <div
                                        v-if="hasCarryInfo(item)"
                                        class="flex items-baseline gap-2"
                                    >
                                        <span
                                            class="text-[11px] tracking-wide text-amber-600 uppercase dark:text-amber-400"
                                            >{{
                                                t(
                                                    'pageDealer.orders.atDeliveryman',
                                                )
                                            }}</span
                                        >
                                        <span class="font-mono"
                                            >{{ item.carry_qty }}
                                            <span
                                                class="text-xs text-muted-foreground"
                                                >{{
                                                    unitLabel(item.unit)
                                                }}</span
                                            ></span
                                        >
                                    </div>
                                </div>
                            </td>
                            <td
                                class="px-4 py-3 text-right font-mono font-medium"
                            >
                                <div class="flex flex-col items-end">
                                    <span>{{
                                        formatWithSymbol(itemSubtotal(item))
                                    }}</span>
                                    <span
                                        v-if="itemSubtotalChanged(item)"
                                        class="text-[11px] font-normal text-muted-foreground line-through"
                                    >
                                        {{ formatWithSymbol(item.subtotal) }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t bg-muted/20">
                        <tr>
                            <td
                                colspan="3"
                                class="px-4 py-3 text-right font-bold"
                            >
                                {{ t('pageDealer.orders.totalColon') }}
                            </td>
                            <td
                                class="px-4 py-3 text-right font-mono text-lg font-bold"
                            >
                                <div class="flex flex-col items-end">
                                    <span>{{
                                        formatWithSymbol(orderTotal)
                                    }}</span>
                                    <span
                                        v-if="orderTotalChanged"
                                        class="text-xs font-normal text-muted-foreground line-through"
                                    >
                                        {{ formatWithSymbol(order.total) }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Mobile card list -->
            <div class="flex flex-col divide-y sm:hidden">
                <div
                    v-for="item in order.items"
                    :key="`m-${item.id}`"
                    class="flex flex-col gap-1.5 p-4"
                >
                    <button
                        type="button"
                        class="text-left font-medium hover:text-primary hover:underline"
                        @click="emit('view-product', item.product_id)"
                    >
                        {{ item.product_name }}
                        <span
                            v-if="item.product_type_name"
                            class="text-muted-foreground"
                            >— {{ item.product_type_name }}</span
                        >
                    </button>
                    <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-sm">
                        <div class="flex justify-between gap-2">
                            <span class="text-muted-foreground">{{
                                t('pageDealer.orders.price')
                            }}</span>
                            <span class="font-mono">{{
                                formatWithSymbol(item.price)
                            }}</span>
                        </div>
                        <div class="flex justify-between gap-2 font-medium">
                            <span class="text-muted-foreground">{{
                                t('pageDealer.orders.total')
                            }}</span>
                            <span class="text-right">
                                <span class="font-mono">{{
                                    formatWithSymbol(itemSubtotal(item))
                                }}</span>
                                <span
                                    v-if="itemSubtotalChanged(item)"
                                    class="block font-mono text-[11px] font-normal text-muted-foreground line-through"
                                >
                                    {{ formatWithSymbol(item.subtotal) }}
                                </span>
                            </span>
                        </div>
                        <div class="col-span-2 flex justify-between gap-2">
                            <span class="text-muted-foreground">{{
                                t('pageDealer.orders.requested')
                            }}</span>
                            <span class="text-right">
                                {{ item.qty }}
                                <span class="text-xs text-muted-foreground">{{
                                    unitLabel(item.unit)
                                }}</span>
                                <span
                                    v-if="
                                        packBreakdown(
                                            item,
                                            item.qty,
                                            item.pack_qty,
                                        )
                                    "
                                    class="block text-xs text-muted-foreground"
                                >
                                    {{
                                        packBreakdown(
                                            item,
                                            item.qty,
                                            item.pack_qty,
                                        )
                                    }}
                                </span>
                            </span>
                        </div>
                        <div
                            v-if="hasPickedInfo(item)"
                            class="col-span-2 flex justify-between gap-2"
                        >
                            <span class="text-sky-700 dark:text-sky-400">{{
                                t('pageDealer.orders.fromStock')
                            }}</span>
                            <span class="text-right">
                                {{ item.picked_qty }}
                                <span class="text-xs text-muted-foreground">{{
                                    unitLabel(item.unit)
                                }}</span>
                                <span
                                    v-if="
                                        packBreakdown(
                                            item,
                                            item.picked_qty ?? 0,
                                            item.picked_pack_qty,
                                        )
                                    "
                                    class="block text-xs text-muted-foreground"
                                >
                                    {{
                                        packBreakdown(
                                            item,
                                            item.picked_qty ?? 0,
                                            item.picked_pack_qty,
                                        )
                                    }}
                                </span>
                            </span>
                        </div>
                        <div
                            v-if="hasDeliveredInfo(item)"
                            class="col-span-2 flex justify-between gap-2"
                        >
                            <span
                                class="text-emerald-700 dark:text-emerald-400"
                                >{{ t('pageDealer.orders.delivered') }}</span
                            >
                            <span class="text-right font-medium">
                                {{ item.delivered_qty }}
                                <span class="text-xs text-muted-foreground">{{
                                    unitLabel(item.unit)
                                }}</span>
                                <span
                                    v-if="
                                        packBreakdown(
                                            item,
                                            item.delivered_qty,
                                            item.delivered_pack_qty,
                                        )
                                    "
                                    class="block text-xs font-normal text-muted-foreground"
                                >
                                    {{
                                        packBreakdown(
                                            item,
                                            item.delivered_qty,
                                            item.delivered_pack_qty,
                                        )
                                    }}
                                </span>
                            </span>
                        </div>
                        <div
                            v-if="hasReturnedInfo(item)"
                            class="col-span-2 flex justify-between gap-2"
                        >
                            <span class="text-rose-700 dark:text-rose-400">{{
                                t('pageDealer.orders.returned')
                            }}</span>
                            <span class="text-right">
                                {{ item.returned_qty }}
                                <span class="text-xs text-muted-foreground">{{
                                    unitLabel(item.unit)
                                }}</span>
                                <span
                                    v-if="
                                        packBreakdown(
                                            item,
                                            item.returned_qty,
                                            item.returned_pack_qty,
                                        )
                                    "
                                    class="block text-xs text-muted-foreground"
                                >
                                    {{
                                        packBreakdown(
                                            item,
                                            item.returned_qty,
                                            item.returned_pack_qty,
                                        )
                                    }}
                                </span>
                            </span>
                        </div>
                        <div
                            v-if="hasCarryInfo(item)"
                            class="col-span-2 flex justify-between gap-2"
                        >
                            <span class="text-amber-700 dark:text-amber-400">{{
                                t('pageDealer.orders.atDeliveryman')
                            }}</span>
                            <span class="text-right">
                                {{ item.carry_qty }}
                                <span class="text-xs text-muted-foreground">{{
                                    unitLabel(item.unit)
                                }}</span>
                            </span>
                        </div>
                    </div>
                </div>
                <div
                    class="flex justify-between bg-muted/20 p-4 text-base font-bold"
                >
                    <span>{{ t('pageDealer.orders.totalColon') }}</span>
                    <span class="text-right">
                        <span class="font-mono">{{
                            formatWithSymbol(orderTotal)
                        }}</span>
                        <span
                            v-if="orderTotalChanged"
                            class="block font-mono text-xs font-normal text-muted-foreground line-through"
                        >
                            {{ formatWithSymbol(order.total) }}
                        </span>
                    </span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
