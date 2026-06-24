<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    ChevronDown,
    Clock,
    DoorOpen,
    FileText,
    Hand,
    MapPin,
    Package,
    Pencil,
    Phone,
    Truck,
    UserPlus,
    XCircle,
} from 'lucide-vue-next';
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import MapLinkButton from '@/components/dealer/MapLinkButton.vue';
import AcceptReturnModal from '@/components/dealer/orders/AcceptReturnModal.vue';
import AssembleModal from '@/components/dealer/orders/AssembleModal.vue';
import CancelOrderDialog from '@/components/dealer/orders/CancelOrderDialog.vue';
import DeliverymanPicker from '@/components/dealer/orders/DeliverymanPicker.vue';
import DeliveryModal from '@/components/dealer/orders/DeliveryModal.vue';
import OrderItemsTable from '@/components/dealer/orders/OrderItemsTable.vue';
import OrderMessages from '@/components/dealer/orders/OrderMessages.vue';
import ProductDetailsModal from '@/components/dealer/orders/ProductDetailsModal.vue';
import ShopDetailsModal from '@/components/dealer/orders/ShopDetailsModal.vue';
import ShopReturnModal from '@/components/dealer/orders/ShopReturnModal.vue';
import StatusTimeline from '@/components/dealer/orders/StatusTimeline.vue';
// Leaflet `window` ga tayanadi → SSR'da yiqiladi. Faqat klientda lazy yuklanadi.
const LocationPicker = defineAsyncComponent(
    () => import('@/components/LocationPicker.vue'),
);
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useCurrency } from '@/composables/useCurrency';
import { confirm } from '@/composables/useConfirm';
import { useUnitLabel } from '@/composables/useUnitLabel';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/date';
import type { Order, OrderAbilities, OrderStatus, Product } from '@/types';

type Deliveryman = { id: number; name: string; phone: string | null };

const props = defineProps<{
    order: { data: Order };
    abilities: OrderAbilities;
    deliverymen: Deliveryman[];
    dealerProducts?: { data: Product[] };
}>();

const { t } = useI18n();
const unitLabel = useUnitLabel();
const { formatWithSymbol } = useCurrency();

const order = computed(() => props.order.data);
const catalog = ref<Product[]>([]);

watch(
    () => props.dealerProducts?.data,
    (val) => {
        if (val) {
            catalog.value = val;
        }
    },
    { immediate: true },
);

const hasCatalog = computed(() => catalog.value.length > 0);
const showDelivery = ref(false);
const showCancel = ref(false);
const showDeliverymanPicker = ref(false);
const showAssemble = ref(false);
const catalogLoading = ref(false);
const assembleLoading = ref(false);
const dispatchProcessing = ref(false);
const showProductDetails = ref(false);
const showShopDetails = ref(false);
const selectedProductId = ref<number | null>(null);

function viewProduct(productId: number) {
    selectedProductId.value = productId;
    showProductDetails.value = true;
}

const hasShopLocation = computed(
    () =>
        order.value.shop?.latitude != null &&
        order.value.shop?.longitude != null,
);

const shopLocationText = computed(() => {
    const shop = order.value.shop;

    if (!shop) {
        return null;
    }

    return [shop.region, shop.district]
        .filter((v): v is string => Boolean(v && v.trim()))
        .join(', ');
});

const shopForMap = computed(() => {
    const shop = order.value.shop;

    if (!shop || shop.latitude == null || shop.longitude == null) {
        return null;
    }

    return {
        latitude: shop.latitude,
        longitude: shop.longitude,
        address: shop.address ?? null,
    };
});

const isDelivered = computed(() => order.value.delivered_at !== null);
const isCancelled = computed(() => order.value.status === 'cancelled');

// Status'ga qarab "jami" — backend `display_total` qaytaradi (DELIVERED/RECEIVED:
// delivered_total; ASSEMBLING/DELIVERING: prepared_total; PENDING/CANCELLED: live).
const finalTotal = computed(() => {
    const o = order.value;

    if (isDelivered.value) {
        return o.delivered_total ?? 0;
    }

    return o.display_total ?? o.prepared_total ?? o.total;
});
const isPreparedStage = computed(
    () =>
        order.value.status === 'assembling' ||
        order.value.status === 'delivering',
);
const showOrderedStrike = computed(
    () => isPreparedStage.value && finalTotal.value !== order.value.total,
);
const debtAmount = computed(() =>
    Math.max(0, finalTotal.value - order.value.paid_amount),
);

type LifecycleStep = {
    key: string;
    label: string;
    at: string | null;
    icon: typeof Clock;
};

const lifecycleSteps = computed<LifecycleStep[]>(() => {
    const all: LifecycleStep[] = [
        {
            key: 'created',
            label: t('pageDealer.ordersShow.stepCreated'),
            at: order.value.created_at,
            icon: Clock,
        },
        {
            key: 'assembling',
            label: t('pageDealer.ordersShow.stepAssembling'),
            at: order.value.assembling_at,
            icon: Package,
        },
        {
            key: 'delivering',
            label: t('pageDealer.ordersShow.stepDelivering'),
            at: order.value.delivering_at,
            icon: Truck,
        },
        {
            key: 'delivered',
            label: t('pageDealer.ordersShow.stepDelivered'),
            at: order.value.delivered_at,
            icon: CheckCircle2,
        },
        {
            key: 'received',
            label: t('pageDealer.ordersShow.stepReceived'),
            at: order.value.received_at,
            icon: CheckCircle2,
        },
    ];

    if (order.value.cancelled_at) {
        all.push({
            key: 'cancelled',
            label: t('pageDealer.ordersShow.stepCancelled'),
            at: order.value.cancelled_at,
            icon: XCircle,
        });
    }

    return all.filter((s) => s.at !== null);
});

function openDelivery() {
    if (!hasCatalog.value) {
        catalogLoading.value = true;
        router.reload({
            only: ['dealerProducts'],
            onFinish: () => {
                catalogLoading.value = false;
                showDelivery.value = true;
            },
        });
    } else {
        showDelivery.value = true;
    }
}

function openAssemble() {
    if (!hasCatalog.value) {
        assembleLoading.value = true;
        router.reload({
            only: ['dealerProducts'],
            onFinish: () => {
                assembleLoading.value = false;
                showAssemble.value = true;
            },
        });
    } else {
        showAssemble.value = true;
    }
}

const showEditPicked = ref(false);

function openEditPicked() {
    if (!hasCatalog.value) {
        assembleLoading.value = true;
        router.reload({
            only: ['dealerProducts'],
            onFinish: () => {
                assembleLoading.value = false;
                showEditPicked.value = true;
            },
        });
    } else {
        showEditPicked.value = true;
    }
}

const statusClass: Record<OrderStatus, string> = {
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

const statusStrip: Record<OrderStatus, string> = {
    pending: 'bg-amber-500',
    assembling: 'bg-sky-500',
    delivering: 'bg-blue-500',
    delivered: 'bg-emerald-500',
    received: 'bg-teal-500',
    cancelled: 'bg-rose-500',
};

const canEdit = computed(
    () =>
        Boolean((order.value as { can_edit?: boolean }).can_edit) &&
        order.value.channel !== 'marketplace',
);

const hasAnyAction = computed(
    () =>
        props.abilities.assemble ||
        props.abilities.dispatch ||
        props.abilities.deliver ||
        props.abilities.cancel ||
        props.abilities.assignDeliveryman ||
        props.abilities.selfAssign ||
        props.abilities.releaseSelf ||
        (props.abilities.acceptReturn && (order.value.has_carry ?? false)) ||
        props.abilities.editPicked ||
        canEdit.value,
);

const showAcceptReturn = ref(false);
const showShopReturn = ref(false);
const historyOpen = ref(true);
const mapOpen = ref(true);
const actionsOpen = ref(true);
const canShopReturn = computed(() => {
    if (order.value.channel === 'marketplace') {
        return false;
    }

    const status = order.value.status;

    return status === 'delivered' || status === 'received';
});

function postAction(path: string) {
    router.post(
        `/dealer/orders/${order.value.id}/${path}`,
        {},
        { preserveScroll: true },
    );
}

// Birja (marketplace) sotuvi — bot bilan bir xil sahifa/oqim, amallar Birja endpointlariga ketadi.
const isMarketplace = computed(() => order.value.channel === 'marketplace');

// Birja sotuvida mijoz = xaridor diller; oddiy buyurtmada = do'kon.
const customerName = computed(() =>
    isMarketplace.value
        ? (order.value.customer_name ?? order.value.buyer_dealer?.name ?? '—')
        : (order.value.shop?.name ?? '—'),
);
const customerPhone = computed(() =>
    isMarketplace.value
        ? (order.value.buyer_dealer?.phone ?? null)
        : (order.value.shop?.phone ?? null),
);

function mpAct(action: string) {
    router.post(
        `/dealer/marketplace/sales/${order.value.id}/${action}`,
        {},
        { preserveScroll: true },
    );
}

function onAssembleAction() {
    isMarketplace.value ? mpAct('accept') : openAssemble();
}

function onDispatchAction() {
    isMarketplace.value ? mpAct('ship') : onDispatchClick();
}

function onDeliverAction() {
    isMarketplace.value ? mpAct('deliver') : openDelivery();
}

async function onCancelAction() {
    if (isMarketplace.value) {
        const ok = await confirm({
            title: t('pageDealer.ordersShow.cancelConfirm'),
            confirmText: t('pageDealer.ordersShow.cancelOrder'),
        });

        if (ok) {
            mpAct('cancel');
        }

        return;
    }

    showCancel.value = true;
}

async function onDispatchClick() {
    if (!order.value.deliveryman_id) {
        const ok = await confirm({
            title: t('pageDealer.ordersShow.dispatch'),
            description: t('pageDealer.ordersShow.dispatchNoDeliveryman'),
            confirmText: t('pageDealer.ordersShow.assignDeliveryman'),
        });

        if (!ok) {
            return;
        }

        showDeliverymanPicker.value = true;

        return;
    }

    const ok = await confirm({
        title: t('pageDealer.ordersShow.dispatch'),
        description: t('pageDealer.ordersShow.dispatchConfirmDesc', {
            id: order.value.number,
        }),
        confirmText: t('pageDealer.ordersShow.dispatchConfirm'),
    });

    if (!ok) {
        return;
    }

    dispatchProcessing.value = true;
    router.post(
        `/dealer/orders/${order.value.id}/dispatch`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                dispatchProcessing.value = false;
            },
        },
    );
}

async function onSelfAssignClick() {
    const ok = await confirm({
        title: t('pageDealer.ordersShow.selfAssignTitle'),
        description: t('pageDealer.ordersShow.selfAssignDesc', {
            id: order.value.id,
            shop: order.value.shop?.name ?? '—',
        }),
        confirmText: t('pageDealer.ordersShow.selfAssignConfirm'),
    });

    if (!ok) {
        return;
    }

    postAction('self-assign');
}

async function onReleaseSelfClick() {
    const ok = await confirm({
        title: t('pageDealer.ordersShow.releaseTitle'),
        description: t('pageDealer.ordersShow.releaseDesc', {
            id: order.value.id,
        }),
        confirmText: t('pageDealer.ordersShow.releaseConfirm'),
        variant: 'destructive',
    });

    if (!ok) {
        return;
    }

    postAction('release-self');
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
    <Head :title="t('pageDealer.ordersShow.headTitle', { id: order.number })" />

    <div class="flex flex-col gap-3 p-3 sm:gap-4 sm:p-4 lg:gap-5 lg:p-6">
        <!-- Header -->
        <div class="flex flex-col gap-2">
            <!-- Top row: nav controls -->
            <div class="flex items-center justify-between gap-2">
                <div class="flex min-w-0 items-center gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-8 px-2"
                        @click="goBack"
                    >
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <span
                        class="shrink-0 rounded-md bg-muted px-2 py-1 font-mono text-xs font-medium text-muted-foreground"
                    >
                        #{{ order.number }}
                    </span>
                    <span
                        class="shrink-0 rounded-md border px-2 py-0.5 text-[11px] font-medium"
                        :class="statusClass[order.status]"
                    >
                        {{ order.status_label }}
                    </span>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 shrink-0"
                    @click="router.get(`/dealer/orders/${order.id}/invoice`)"
                >
                    <FileText class="h-3.5 w-3.5 sm:mr-1.5" />
                    <span class="hidden sm:inline">{{
                        t('pageDealer.ordersShow.invoice')
                    }}</span>
                </Button>
            </div>
        </div>

        <!-- Cancellation banner -->
        <div
            v-if="isCancelled && order.cancellation_reason"
            class="flex gap-3 rounded-lg border border-rose-500/30 bg-rose-500/5 p-3"
        >
            <XCircle class="mt-0.5 h-4 w-4 shrink-0 text-rose-600" />
            <div class="min-w-0 flex-1 text-sm">
                <div
                    class="flex flex-wrap items-baseline justify-between gap-2"
                >
                    <span
                        class="font-semibold text-rose-700 dark:text-rose-300"
                        >{{ t('pageDealer.ordersShow.cancelled') }}</span
                    >
                    <span
                        class="text-xs text-rose-700/70 dark:text-rose-300/70"
                    >
                        {{
                            order.cancelled_at
                                ? formatDateTime(order.cancelled_at)
                                : ''
                        }}
                    </span>
                </div>
                <p class="mt-1 text-foreground/90">
                    <span
                        v-if="order.cancelled_by"
                        class="text-muted-foreground"
                    >
                        {{ order.cancelled_by.name }}:
                    </span>
                    {{ order.cancellation_reason }}
                </p>
            </div>
        </div>

        <div class="grid gap-3 sm:gap-4 lg:grid-cols-3">
            <!-- Shop + Money + Delivery (kolonka 1-2) -->
            <div class="flex flex-col gap-3 sm:gap-4 lg:col-span-2">
                <!-- Shop info card -->
                <Card class="relative overflow-hidden py-0">
                    <span
                        class="absolute inset-y-0 left-0 w-1"
                        :class="statusStrip[order.status]"
                    />
                    <CardContent
                        class="flex flex-col gap-2.5 px-3 py-3 pl-4 sm:gap-3 sm:px-4 sm:pl-5"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p
                                    class="text-[10px] tracking-wide text-muted-foreground uppercase sm:text-[11px]"
                                >
                                    {{ t('pageDealer.ordersShow.shop') }}
                                </p>
                                <button
                                    v-if="order.shop"
                                    type="button"
                                    class="max-w-full truncate text-left text-sm font-semibold underline decoration-dotted underline-offset-2 hover:text-primary sm:text-base"
                                    @click="showShopDetails = true"
                                >
                                    {{ customerName }}
                                </button>
                                <p
                                    v-else
                                    class="truncate text-sm font-semibold sm:text-base"
                                >
                                    {{ customerName }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1.5">
                                <a
                                    v-if="customerPhone"
                                    :href="`tel:${customerPhone}`"
                                    class="inline-flex h-8 items-center gap-1.5 rounded-md border bg-background px-2 text-xs hover:bg-muted active:scale-95 sm:px-2.5"
                                    :title="customerPhone"
                                >
                                    <Phone class="h-3.5 w-3.5" />
                                    <span class="hidden sm:inline">{{
                                        customerPhone
                                    }}</span>
                                </a>
                                <MapLinkButton
                                    v-if="shopForMap"
                                    :shop="shopForMap"
                                    variant="icon"
                                    :title="t('pageDealer.ordersShow.openMap')"
                                />
                            </div>
                        </div>

                        <div
                            v-if="shopLocationText || order.shop?.address"
                            class="flex items-start gap-2 rounded-lg bg-muted/40 px-2.5 py-2"
                        >
                            <MapPin
                                class="mt-0.5 h-4 w-4 shrink-0 text-primary"
                            />
                            <span class="min-w-0 text-[13px] leading-snug">
                                <span
                                    v-if="shopLocationText"
                                    class="font-medium"
                                    >{{ shopLocationText }}</span
                                >
                                <span
                                    v-if="
                                        shopLocationText && order.shop?.address
                                    "
                                    class="text-muted-foreground/50"
                                >
                                    ·
                                </span>
                                <span
                                    v-if="order.shop?.address"
                                    class="text-muted-foreground"
                                    >{{ order.shop.address }}</span
                                >
                            </span>
                        </div>

                        <p
                            v-if="order.note"
                            class="rounded-md bg-muted/30 px-3 py-2 text-sm text-muted-foreground italic"
                        >
                            "{{ order.note }}"
                        </p>
                    </CardContent>
                </Card>

                <!-- Buyurtma ichidagi xabarlar (diller → mijoz) -->
                <OrderMessages
                    :order-id="order.id"
                    :messages="order.messages ?? []"
                />

                <!-- Money mini-grid (faqat yetkazilgandan keyin) -->
                <div
                    v-if="isDelivered"
                    class="grid grid-cols-2 gap-1.5 sm:gap-2 md:grid-cols-4"
                >
                    <Card class="py-0">
                        <CardContent
                            class="flex flex-col gap-0.5 px-2.5 py-2 sm:px-3 sm:py-2.5"
                        >
                            <p
                                class="text-[10px] tracking-wide text-muted-foreground uppercase sm:text-[11px]"
                            >
                                {{ t('pageDealer.ordersShow.order') }}
                            </p>
                            <p
                                class="truncate font-mono text-xs font-bold tabular-nums sm:text-sm"
                            >
                                {{ formatWithSymbol(order.total) }}
                            </p>
                        </CardContent>
                    </Card>
                    <Card class="py-0">
                        <CardContent
                            class="flex flex-col gap-0.5 px-2.5 py-2 sm:px-3 sm:py-2.5"
                        >
                            <p
                                class="text-[10px] tracking-wide text-muted-foreground uppercase sm:text-[11px]"
                            >
                                {{ t('pageDealer.ordersShow.delivered') }}
                            </p>
                            <p
                                class="truncate font-mono text-xs font-bold tabular-nums sm:text-sm"
                            >
                                {{
                                    formatWithSymbol(order.delivered_total ?? 0)
                                }}
                            </p>
                        </CardContent>
                    </Card>
                    <Card class="py-0">
                        <CardContent
                            class="flex flex-col gap-0.5 px-2.5 py-2 sm:px-3 sm:py-2.5"
                        >
                            <p
                                class="text-[10px] tracking-wide text-muted-foreground uppercase sm:text-[11px]"
                            >
                                {{ t('pageDealer.ordersShow.paid') }}
                            </p>
                            <p
                                class="truncate font-mono text-xs font-bold text-emerald-600 tabular-nums sm:text-sm"
                            >
                                {{ formatWithSymbol(order.paid_amount) }}
                            </p>
                        </CardContent>
                    </Card>
                    <Card
                        class="py-0"
                        :class="debtAmount > 0 ? '' : 'opacity-60'"
                    >
                        <CardContent
                            class="flex flex-col gap-0.5 px-2.5 py-2 sm:px-3 sm:py-2.5"
                        >
                            <p
                                class="text-[10px] tracking-wide text-muted-foreground uppercase sm:text-[11px]"
                            >
                                {{ t('pageDealer.ordersShow.debt') }}
                            </p>
                            <p
                                class="truncate font-mono text-xs font-bold tabular-nums sm:text-sm"
                                :class="
                                    debtAmount > 0
                                        ? 'text-rose-600'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ formatWithSymbol(debtAmount) }}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Yetkazib beruvchidagi qoldiq (carry) -->
                <Card
                    v-if="
                        (order.has_carry ?? false) &&
                        (order.carry_total ?? 0) > 0
                    "
                    class="border-amber-500/40 bg-amber-500/5"
                >
                    <CardContent
                        class="flex items-start justify-between gap-3 px-3 py-2.5"
                    >
                        <div class="min-w-0 space-y-1">
                            <p
                                class="text-[10px] tracking-wide text-amber-700 uppercase sm:text-[11px] dark:text-amber-300"
                            >
                                Yetkazib beruvchida qoldi
                            </p>
                            <div class="flex flex-col gap-0.5 text-xs">
                                <div
                                    v-for="item in (order.items ?? []).filter(
                                        (i) => (i.carry_qty ?? 0) > 0,
                                    )"
                                    :key="`carry-${item.id}`"
                                    class="flex items-start justify-between gap-2"
                                >
                                    <span class="min-w-0 break-words">
                                        {{ item.product_name
                                        }}<span
                                            v-if="item.product_type_name"
                                            class="text-muted-foreground"
                                        >
                                            — {{ item.product_type_name }}</span
                                        >
                                    </span>
                                    <span class="shrink-0 font-mono">
                                        {{ item.carry_qty }}
                                        {{ unitLabel(item.unit) }}
                                        <span class="ml-1 text-muted-foreground"
                                            >({{
                                                formatWithSymbol(
                                                    item.carry_subtotal,
                                                )
                                            }})</span
                                        >
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <p
                                class="text-[10px] tracking-wide text-muted-foreground uppercase"
                            >
                                Jami
                            </p>
                            <p
                                class="font-mono text-sm font-bold text-amber-700 tabular-nums sm:text-base dark:text-amber-300"
                            >
                                {{ formatWithSymbol(order.carry_total ?? 0) }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Yetkazib beruvchi mini-row -->
                <div
                    class="flex items-center justify-between gap-2 rounded-lg border bg-card px-3 py-2 text-sm"
                >
                    <div class="flex min-w-0 items-center gap-2">
                        <UserPlus
                            class="h-4 w-4 shrink-0 text-muted-foreground"
                        />
                        <span class="text-muted-foreground">{{
                            t('pageDealer.ordersShow.deliveryman')
                        }}</span>
                        <span
                            class="truncate"
                            :class="
                                order.deliveryman
                                    ? 'font-medium'
                                    : 'text-muted-foreground italic'
                            "
                        >
                            {{
                                order.deliveryman?.name ??
                                t('pageDealer.ordersShow.notAssigned')
                            }}
                        </span>
                    </div>
                    <a
                        v-if="order.deliveryman?.phone"
                        :href="`tel:${order.deliveryman.phone}`"
                        class="shrink-0 text-xs text-primary hover:underline"
                    >
                        {{ order.deliveryman.phone }}
                    </a>
                </div>
            </div>

            <!-- Action panel (kolonka 3) -->
            <Card
                v-if="hasAnyAction"
                class="lg:sticky lg:top-4 lg:self-start"
                :class="actionsOpen ? 'gap-3 py-3' : 'gap-0 py-0'"
            >
                <CardHeader class="px-3 sm:px-4">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-2 py-2 text-left"
                        :aria-expanded="actionsOpen"
                        @click="actionsOpen = !actionsOpen"
                    >
                        <CardTitle class="text-sm">{{
                            t('pageDealer.ordersShow.actions')
                        }}</CardTitle>
                        <ChevronDown
                            class="h-4 w-4 shrink-0 text-muted-foreground transition-transform"
                            :class="actionsOpen ? 'rotate-180' : ''"
                        />
                    </button>
                </CardHeader>
                <CardContent
                    v-show="actionsOpen"
                    class="grid gap-1.5 px-3 sm:grid-cols-2 sm:px-4 lg:grid-cols-1"
                >
                    <Button
                        v-if="abilities.assemble"
                        :disabled="assembleLoading"
                        class="w-full justify-start"
                        @click="onAssembleAction"
                    >
                        <Package class="mr-2 h-4 w-4" />
                        {{
                            assembleLoading
                                ? t('pageDealer.ordersShow.catalogLoading')
                                : t('pageDealer.ordersShow.startAssemble')
                        }}
                    </Button>

                    <Button
                        v-if="abilities.editPicked"
                        variant="outline"
                        :disabled="assembleLoading"
                        class="w-full justify-start"
                        @click="openEditPicked"
                    >
                        <Pencil class="mr-2 h-4 w-4" />
                        {{
                            assembleLoading
                                ? t('pageDealer.ordersShow.catalogLoading')
                                : t('pageDealer.ordersShow.editPicked')
                        }}
                    </Button>

                    <Button
                        v-if="abilities.dispatch"
                        :disabled="dispatchProcessing"
                        class="w-full justify-start"
                        @click="onDispatchAction"
                    >
                        <Truck class="mr-2 h-4 w-4" />
                        {{
                            dispatchProcessing
                                ? t('pageDealer.ordersShow.catalogLoading')
                                : t('pageDealer.ordersShow.dispatch')
                        }}
                    </Button>

                    <Button
                        v-if="abilities.deliver"
                        :disabled="catalogLoading"
                        class="w-full justify-start"
                        @click="onDeliverAction"
                    >
                        <CheckCircle2 class="mr-2 h-4 w-4" />
                        {{
                            catalogLoading
                                ? t('pageDealer.ordersShow.catalogLoading')
                                : t('pageDealer.ordersShow.deliverDone')
                        }}
                    </Button>

                    <Button
                        v-if="abilities.assignDeliveryman"
                        variant="outline"
                        size="sm"
                        class="w-full justify-start"
                        @click="showDeliverymanPicker = true"
                    >
                        <UserPlus class="mr-2 h-4 w-4" />
                        {{
                            order.deliveryman
                                ? t('pageDealer.ordersShow.changeDeliveryman')
                                : t('pageDealer.ordersShow.assignDeliveryman')
                        }}
                    </Button>

                    <Button
                        v-if="abilities.selfAssign"
                        variant="outline"
                        size="sm"
                        class="w-full justify-start"
                        @click="onSelfAssignClick"
                    >
                        <Hand class="mr-2 h-4 w-4" />
                        {{ t('pageDealer.ordersShow.selfAssign') }}
                    </Button>

                    <Button
                        v-if="abilities.releaseSelf"
                        variant="outline"
                        size="sm"
                        class="w-full justify-start border-rose-500/40 text-rose-700 hover:bg-rose-500/10 hover:text-rose-700 dark:text-rose-300 dark:hover:text-rose-300"
                        @click="onReleaseSelfClick"
                    >
                        <DoorOpen class="mr-2 h-4 w-4" />
                        {{ t('pageDealer.ordersShow.release') }}
                    </Button>

                    <Button
                        v-if="
                            abilities.acceptReturn && (order.has_carry ?? false)
                        "
                        variant="outline"
                        size="sm"
                        class="w-full justify-start border-emerald-500/40 text-emerald-700 hover:bg-emerald-500/10 hover:text-emerald-700 dark:text-emerald-300 dark:hover:text-emerald-300"
                        @click="showAcceptReturn = true"
                    >
                        <DoorOpen class="mr-2 h-4 w-4" />
                        {{ t('pageDealer.ordersShow.acceptReturn') }}
                    </Button>

                    <Button
                        v-if="canShopReturn"
                        variant="outline"
                        size="sm"
                        class="w-full justify-start border-amber-500/40 text-amber-700 hover:bg-amber-500/10 hover:text-amber-700 dark:text-amber-300 dark:hover:text-amber-300"
                        @click="showShopReturn = true"
                    >
                        <DoorOpen class="mr-2 h-4 w-4" />
                        {{ t('pageDealer.ordersShow.shopReturn') }}
                    </Button>

                    <Button
                        v-if="canEdit"
                        variant="outline"
                        size="sm"
                        class="w-full justify-start"
                        @click="router.get(`/dealer/orders/${order.id}/edit`)"
                    >
                        <Pencil class="mr-2 h-4 w-4" />
                        {{ t('pageDealer.ordersShow.editOrder') }}
                    </Button>

                    <Button
                        v-if="abilities.cancel"
                        variant="outline"
                        size="sm"
                        class="w-full justify-start border-rose-500/40 text-rose-700 hover:bg-rose-500/10 hover:text-rose-700 dark:text-rose-300 dark:hover:text-rose-300"
                        @click="onCancelAction"
                    >
                        <XCircle class="mr-2 h-4 w-4" />
                        {{ t('pageDealer.ordersShow.cancelOrder') }}
                    </Button>
                </CardContent>
            </Card>

            <!-- Bo'sh action panel placeholder, faqat lg da -->
            <div
                v-else
                class="hidden rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground lg:flex lg:items-center lg:justify-center lg:self-start"
            >
                {{ t('pageDealer.ordersShow.noActions') }}
            </div>
        </div>

        <OrderItemsTable :order="order" @view-product="viewProduct" />

        <div class="grid gap-3 sm:gap-4 md:grid-cols-2">
            <!-- Lifecycle + Status timeline -->
            <Card :class="historyOpen ? 'gap-3 py-3' : 'gap-0 py-0'">
                <CardHeader class="px-3 sm:px-4">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-2 py-2 text-left"
                        :aria-expanded="historyOpen"
                        @click="historyOpen = !historyOpen"
                    >
                        <CardTitle class="text-sm">{{
                            t('pageDealer.ordersShow.history')
                        }}</CardTitle>
                        <ChevronDown
                            class="h-4 w-4 shrink-0 text-muted-foreground transition-transform"
                            :class="historyOpen ? 'rotate-180' : ''"
                        />
                    </button>
                </CardHeader>
                <CardContent
                    v-show="historyOpen"
                    class="flex flex-col gap-3 px-3 sm:px-4"
                >
                    <!-- Lifecycle compact strip -->
                    <div
                        v-if="lifecycleSteps.length"
                        class="grid gap-1.5 sm:grid-cols-2"
                    >
                        <div
                            v-for="step in lifecycleSteps"
                            :key="step.key"
                            class="flex items-center gap-2 rounded-md bg-muted/40 px-2.5 py-1.5 text-xs"
                        >
                            <component
                                :is="step.icon"
                                class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                            />
                            <span class="font-medium">{{ step.label }}</span>
                            <span
                                class="ml-auto truncate text-muted-foreground"
                            >
                                {{ step.at ? formatDateTime(step.at) : '—' }}
                            </span>
                        </div>
                    </div>

                    <StatusTimeline
                        v-if="order.status_history?.length"
                        :history="order.status_history"
                        :current-status="order.status"
                    />
                </CardContent>
            </Card>

            <!-- Mijoz lokatsiyasi -->
            <Card
                v-if="hasShopLocation"
                :class="mapOpen ? 'gap-3 py-3' : 'gap-0 py-0'"
            >
                <CardHeader class="px-3 sm:px-4">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-2 py-2 text-left"
                        :aria-expanded="mapOpen"
                        @click="mapOpen = !mapOpen"
                    >
                        <CardTitle class="text-sm">{{
                            t('pageDealer.ordersShow.shopLocation')
                        }}</CardTitle>
                        <ChevronDown
                            class="h-4 w-4 shrink-0 text-muted-foreground transition-transform"
                            :class="mapOpen ? 'rotate-180' : ''"
                        />
                    </button>
                </CardHeader>
                <CardContent
                    v-show="mapOpen"
                    class="flex flex-col gap-2 px-3 sm:px-4"
                >
                    <LocationPicker
                        :latitude="order.shop!.latitude ?? null"
                        :longitude="order.shop!.longitude ?? null"
                        :provider="order.shop!.map_provider ?? 'yandex'"
                        readonly
                        height="h-44 sm:h-56"
                    />
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 text-xs"
                    >
                        <span class="font-mono text-muted-foreground">
                            {{ order.shop!.latitude }},
                            {{ order.shop!.longitude }}
                        </span>
                        <MapLinkButton
                            v-if="shopForMap"
                            :shop="shopForMap"
                            variant="button"
                            :label="t('pageDealer.ordersShow.openLargeMap')"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>

        <ProductDetailsModal
            v-model:open="showProductDetails"
            :product-id="selectedProductId"
        />

        <ShopDetailsModal
            v-model:open="showShopDetails"
            :shop="order.shop ?? null"
        />

        <DeliveryModal
            v-if="hasCatalog"
            v-model:open="showDelivery"
            :order="order"
            :catalog="catalog"
            :can-assemble="abilities.assemble"
            @request-assemble="openAssemble"
        />
        <CancelOrderDialog v-model:open="showCancel" :order="order" />
        <DeliverymanPicker
            v-model:open="showDeliverymanPicker"
            :order="order"
            :deliverymen="deliverymen"
        />
        <AssembleModal
            v-if="hasCatalog"
            v-model:open="showAssemble"
            :order="order"
            :catalog="catalog"
        />
        <AssembleModal
            v-if="hasCatalog"
            v-model:open="showEditPicked"
            mode="edit-picked"
            :order="order"
            :catalog="catalog"
        />
        <AcceptReturnModal v-model:open="showAcceptReturn" :order="order" />
        <ShopReturnModal v-model:open="showShopReturn" :order="order" />
    </div>
</template>
