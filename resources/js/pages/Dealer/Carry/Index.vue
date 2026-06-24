<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronRight,
    Package,
    Phone,
    RotateCcw,
    Truck,
    User,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AcceptReturnModal from '@/components/dealer/orders/AcceptReturnModal.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useCurrency } from '@/composables/useCurrency';
import { useUnitLabel } from '@/composables/useUnitLabel';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Order } from '@/types';

type CarryGroup = {
    deliveryman: { id: number; name: string | null; phone: string | null };
    carry_total: number;
    orders_count: number;
    items_count: number;
    orders: { data: Order[] };
};

defineProps<{
    groups: CarryGroup[];
    summary: { orders: number; carry_total: number; deliverymen_count: number };
}>();

const { t } = useI18n();
const unitLabel = useUnitLabel();
const { formatWithSymbol } = useCurrency();

const expanded = ref<Record<number, boolean>>({});
const returnModalOpen = ref(false);
const returnOrder = ref<Order | null>(null);

function toggle(id: number) {
    expanded.value = { ...expanded.value, [id]: !expanded.value[id] };
}

function openReturn(order: Order) {
    returnOrder.value = order;
    returnModalOpen.value = true;
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.carry.headTitle')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div>
            <h1 class="text-xl font-bold tracking-tight sm:text-2xl">
                {{ t('pageDealer.carry.title') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ t('pageDealer.carry.subtitle') }}
            </p>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-3 gap-2 sm:gap-3">
            <Card class="py-0">
                <CardContent class="flex flex-col gap-0.5 p-3 sm:p-4">
                    <p
                        class="text-[10px] tracking-wide text-muted-foreground uppercase sm:text-xs"
                    >
                        {{ t('pageDealer.carry.deliverymen') }}
                    </p>
                    <p class="text-lg font-bold sm:text-2xl">
                        {{ summary.deliverymen_count }}
                    </p>
                </CardContent>
            </Card>
            <Card class="py-0">
                <CardContent class="flex flex-col gap-0.5 p-3 sm:p-4">
                    <p
                        class="text-[10px] tracking-wide text-muted-foreground uppercase sm:text-xs"
                    >
                        {{ t('pageDealer.carry.orders') }}
                    </p>
                    <p class="text-lg font-bold sm:text-2xl">
                        {{ summary.orders }}
                    </p>
                </CardContent>
            </Card>
            <Card class="py-0">
                <CardContent class="flex flex-col gap-0.5 p-3 sm:p-4">
                    <p
                        class="text-[10px] tracking-wide text-muted-foreground uppercase sm:text-xs"
                    >
                        {{ t('pageDealer.carry.totalSum') }}
                    </p>
                    <p
                        class="font-mono text-base font-bold text-amber-600 tabular-nums sm:text-xl"
                    >
                        {{ formatWithSymbol(summary.carry_total) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Empty state -->
        <Card v-if="groups.length === 0">
            <CardContent
                class="flex flex-col items-center gap-2 p-6 text-center text-muted-foreground sm:p-10"
            >
                <Package class="h-10 w-10 text-muted-foreground/40" />
                <p class="text-sm">{{ t('pageDealer.carry.empty') }}</p>
            </CardContent>
        </Card>

        <!-- Groups -->
        <div v-else class="flex flex-col gap-3">
            <Card
                v-for="g in groups"
                :key="g.deliveryman.id"
                class="overflow-hidden"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-muted/30 sm:px-5 sm:py-4"
                    @click="toggle(g.deliveryman.id)"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="rounded-lg bg-primary/10 p-2 text-primary">
                            <Truck class="h-4 w-4 sm:h-5 sm:w-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-semibold">
                                {{
                                    g.deliveryman.name ?? `#${g.deliveryman.id}`
                                }}
                            </p>
                            <p
                                class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-muted-foreground"
                            >
                                <span
                                    v-if="g.deliveryman.phone"
                                    class="flex items-center gap-1"
                                >
                                    <Phone class="h-3 w-3" />
                                    {{ g.deliveryman.phone }}
                                </span>
                                <span>{{
                                    t('pageDealer.carry.ordersCount', {
                                        n: g.orders_count,
                                    })
                                }}</span>
                                <span>{{
                                    t('pageDealer.carry.itemsCount', {
                                        n: g.items_count,
                                    })
                                }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <span
                            class="font-mono text-sm font-bold text-amber-600 tabular-nums sm:text-base"
                        >
                            {{ formatWithSymbol(g.carry_total) }}
                        </span>
                        <component
                            :is="
                                expanded[g.deliveryman.id]
                                    ? ChevronDown
                                    : ChevronRight
                            "
                            class="h-4 w-4 text-muted-foreground"
                        />
                    </div>
                </button>

                <div
                    v-if="expanded[g.deliveryman.id]"
                    class="border-t bg-muted/10"
                >
                    <div class="flex flex-col divide-y">
                        <div
                            v-for="order in g.orders.data"
                            :key="order.id"
                            class="flex flex-col gap-2 p-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4 sm:p-4"
                        >
                            <div class="min-w-0 flex-1 space-y-1.5">
                                <div class="flex items-center gap-2 text-sm">
                                    <Link
                                        :href="`/dealer/orders/${order.id}`"
                                        class="font-semibold text-primary hover:underline"
                                    >
                                        #{{ order.number }}
                                    </Link>
                                    <span
                                        class="rounded-full border px-2 py-0.5 text-[10px] font-medium"
                                        >{{ order.status_label }}</span
                                    >
                                    <span
                                        class="text-xs text-muted-foreground"
                                        >{{ order.shop?.name }}</span
                                    >
                                </div>
                                <div class="space-y-0.5 text-xs">
                                    <div
                                        v-for="item in (
                                            order.items ?? []
                                        ).filter((i) => i.carry_qty > 0)"
                                        :key="item.id"
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <span class="min-w-0 break-words">
                                            <User
                                                class="mr-0.5 inline h-3 w-3 text-muted-foreground"
                                            />
                                            {{ item.product_name
                                            }}<span
                                                v-if="item.product_type_name"
                                                class="text-muted-foreground"
                                            >
                                                —
                                                {{
                                                    item.product_type_name
                                                }}</span
                                            >
                                        </span>
                                        <span class="shrink-0 text-right">
                                            <span class="font-mono"
                                                >{{ item.carry_qty }}
                                                {{ unitLabel(item.unit) }}</span
                                            >
                                            <span
                                                class="ml-1.5 font-mono text-muted-foreground"
                                            >
                                                ({{
                                                    formatWithSymbol(
                                                        item.carry_subtotal,
                                                    )
                                                }})
                                            </span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="flex flex-row items-center justify-between gap-2 sm:flex-col sm:items-end"
                            >
                                <span
                                    class="font-mono text-sm font-bold text-amber-600"
                                >
                                    {{
                                        formatWithSymbol(order.carry_total ?? 0)
                                    }}
                                </span>
                                <Button
                                    v-if="order.can_accept_return"
                                    size="sm"
                                    variant="outline"
                                    class="border-emerald-500/40 text-emerald-700 hover:bg-emerald-500/10 dark:text-emerald-300"
                                    @click="openReturn(order)"
                                >
                                    <RotateCcw class="mr-1 h-3.5 w-3.5" />
                                    {{ t('pageDealer.carry.acceptReturn') }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    </div>

    <AcceptReturnModal
        v-if="returnOrder"
        :open="returnModalOpen"
        :order="returnOrder"
        @update:open="
            (v) => {
                returnModalOpen = v;
                if (!v) returnOrder = null;
            }
        "
    />
</template>
