<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { MapPin, Phone, Undo2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import MapLinkButton from '@/components/dealer/MapLinkButton.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useCurrency } from '@/composables/useCurrency';

type ShopInfo = {
    id: number;
    name: string;
    address: string | null;
    region: string | null;
    district: string | null;
    phone: string | null;
    latitude: number | null;
    longitude: number | null;
};

type Order = {
    id: number;
    number: number;
    status: string;
    status_label: string;
    total: number;
    delivered_total: number | null;
    discount: number | null;
    items_count: number;
    note: string | null;
    created_at: string;
    delivering_at: string | null;
    delivered_at: string | null;
    received_at: string | null;
    has_pending_return?: boolean;
    shop: ShopInfo | null;
};

const props = defineProps<{
    order: Order;
}>();

const emit = defineEmits<{
    (e: 'navigate'): void;
}>();

const { t } = useI18n();
const { formatWithSymbol } = useCurrency();

const statusClass: Record<string, string> = {
    pending:
        'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30',
    assembling:
        'bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-500/30',
    delivering:
        'bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-500/30',
    delivered:
        'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
    received:
        'bg-teal-500/15 text-teal-700 dark:text-teal-300 border-teal-500/30',
    cancelled:
        'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30',
};

const statusStrip: Record<string, string> = {
    pending: 'bg-amber-500',
    assembling: 'bg-sky-500',
    delivering: 'bg-blue-500',
    delivered: 'bg-emerald-500',
    received: 'bg-teal-500',
    cancelled: 'bg-rose-500',
};

function isCompleted(status: string): boolean {
    return status === 'delivered' || status === 'received';
}

function isCancelled(status: string): boolean {
    return status === 'cancelled';
}

function isFinalized(order: Order): boolean {
    return order.status === 'delivered' || order.status === 'received';
}

function displayTotal(order: Order): number {
    if (isFinalized(order)) {
        return Math.max(
            0,
            (order.delivered_total ?? 0) - (order.discount ?? 0),
        );
    }
    return order.total;
}

function hasTotalBreakdown(order: Order): boolean {
    if (!isFinalized(order)) return false;
    const delivered = order.delivered_total ?? 0;
    return delivered !== order.total || (order.discount ?? 0) > 0;
}

function deliveredDiffers(order: Order): boolean {
    return (order.delivered_total ?? 0) !== order.total;
}

function formatLocation(shop: ShopInfo): string {
    return [shop.region, shop.district]
        .filter((v): v is string => Boolean(v && v.trim()))
        .join(', ');
}

function open() {
    emit('navigate');
    router.get(`/dealer/orders/${props.order.id}`);
}
</script>

<template>
    <Card
        class="relative cursor-pointer overflow-hidden py-0 transition-all active:scale-[0.99]"
        :class="{
            'opacity-70': isCompleted(order.status),
            'opacity-50': isCancelled(order.status),
        }"
        @click="open"
    >
        <span
            class="absolute inset-y-0 left-0 w-1"
            :class="statusStrip[order.status] ?? 'bg-muted-foreground'"
        />
        <CardContent class="flex flex-col gap-2 py-3 pr-3 pl-4">
            <div class="flex items-center justify-between gap-2">
                <div class="flex min-w-0 flex-1 items-center gap-2">
                    <span
                        class="shrink-0 rounded-md bg-muted px-1.5 py-0.5 font-mono text-[11px] font-medium text-muted-foreground"
                        >#{{ order.number }}</span
                    >
                    <span
                        class="truncate text-base leading-tight font-semibold"
                        >{{ order.shop?.name ?? '—' }}</span
                    >
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <span
                        v-if="order.has_pending_return"
                        class="inline-flex items-center gap-1 rounded-full border border-rose-500/30 bg-rose-500/15 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700 dark:text-rose-300"
                        :title="t('pageDealer.routesToday.pendingReturn')"
                    >
                        <Undo2 class="h-3 w-3" />
                    </span>
                    <Badge
                        class="border text-[10px]"
                        :class="statusClass[order.status] ?? ''"
                    >
                        {{ order.status_label }}
                    </Badge>
                </div>
            </div>

            <div
                v-if="
                    order.shop &&
                    (order.shop.address ||
                        order.shop.region ||
                        order.shop.district)
                "
                class="flex items-start gap-2 rounded-lg bg-muted/40 px-2.5 py-2"
            >
                <MapPin class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                <span class="min-w-0 text-[13px] leading-snug">
                    <span
                        v-if="formatLocation(order.shop)"
                        class="font-medium text-foreground"
                        >{{ formatLocation(order.shop) }}</span
                    >
                    <span
                        v-if="formatLocation(order.shop) && order.shop.address"
                        class="text-muted-foreground/50"
                    >
                        ·
                    </span>
                    <span
                        v-if="order.shop.address"
                        class="text-muted-foreground"
                        >{{ order.shop.address }}</span
                    >
                </span>
            </div>

            <p
                v-if="order.note"
                class="line-clamp-2 text-xs text-muted-foreground italic"
            >
                "{{ order.note }}"
            </p>

            <div class="flex items-center justify-between gap-2 pt-0.5">
                <div
                    class="flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <span
                        >{{ order.items_count }}
                        {{ t('pageDealer.routesToday.items') }}</span
                    >
                    <span class="text-muted-foreground/50">·</span>
                    <div class="flex flex-col">
                        <span
                            class="font-mono text-sm font-bold text-foreground tabular-nums"
                            >{{ formatWithSymbol(displayTotal(order)) }}</span
                        >
                        <div
                            v-if="hasTotalBreakdown(order)"
                            class="mt-0.5 flex flex-wrap gap-x-1.5 text-[10px] font-normal text-muted-foreground"
                        >
                            <span v-if="deliveredDiffers(order)"
                                >{{ t('pageDealer.ordersIndex.orderedShort') }}:
                                {{ formatWithSymbol(order.total) }}</span
                            >
                            <span
                                v-if="(order.discount ?? 0) > 0"
                                class="text-rose-600"
                                >−{{
                                    formatWithSymbol(order.discount ?? 0)
                                }}</span
                            >
                        </div>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-1.5">
                    <a
                        v-if="order.shop?.phone"
                        :href="`tel:${order.shop.phone}`"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-full border bg-background hover:bg-muted active:scale-95"
                        :title="order.shop.phone"
                        @click.stop
                    >
                        <Phone class="h-3.5 w-3.5" />
                    </a>
                    <MapLinkButton
                        v-if="order.shop"
                        :shop="order.shop"
                        variant="icon"
                        :title="t('pageDealer.routesToday.openInMap')"
                    />
                </div>
            </div>
        </CardContent>
    </Card>
</template>
