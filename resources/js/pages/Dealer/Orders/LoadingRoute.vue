<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Loader2,
    Package,
    Printer,
    Settings,
    Truck,
} from 'lucide-vue-next';
import { computed, defineAsyncComponent, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import LoadingRouteList from '@/components/dealer/loading-route/LoadingRouteList.vue';
import {
    formatDistance,
    formatDuration,
    type LoadingRouteResult,
} from '@/components/dealer/loading-route/types';
// Leaflet `window` ga tayanadi → SSR'da yiqiladi. Faqat klientda lazy yuklanadi.
const OrdersMap = defineAsyncComponent(() => import('@/components/dealer/OrdersMap.vue'));
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

const { t } = useI18n();

const props = defineProps<{
    warehouseConfigured: boolean;
    warehouse: { latitude: number; longitude: number; address: string | null } | null;
    orderIds: number[];
    maxStops: number;
}>();

const loading = ref(false);
const error = ref<string | null>(null);
const result = ref<LoadingRouteResult | null>(null);
const mode = ref<'loading' | 'delivery'>('loading');

const totalDistanceWithReturn = computed(() => {
    if (!result.value) {
        return 0;
    }

    return result.value.total_distance_meters + result.value.return_distance_meters;
});

const totalDurationWithReturn = computed(() => {
    if (!result.value) {
        return 0;
    }

    return result.value.total_duration_seconds + result.value.return_duration_seconds;
});

const mapPoints = computed(() => {
    if (!result.value) {
        return [];
    }

    const stops = mode.value === 'loading'
        ? result.value.loading_sequence
        : result.value.delivery_sequence;

    return stops.map((s) => ({
        id: s.payload.order_id,
        number: s.payload.order_number,
        status: s.payload.order_status,
        shopName: `${mode.value === 'loading' ? s.loading_position : s.delivery_position}. ${s.payload.shop_name}`,
        shopAddress: [s.payload.shop_region, s.payload.shop_district, s.payload.shop_address]
            .filter(Boolean)
            .join(' · ') || null,
        latitude: s.payload.shop_latitude,
        longitude: s.payload.shop_longitude,
    }));
});

async function compute() {
    if (props.orderIds.length === 0) {
        error.value = t('pageDealer.loadingRoute.errorNoOrders');

        return;
    }

    loading.value = true;
    error.value = null;
    result.value = null;

    try {
        const csrfMeta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
        const res = await fetch('/dealer/orders/loading-route', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfMeta ? { 'X-CSRF-TOKEN': csrfMeta.content } : {}),
            },
            body: JSON.stringify({ order_ids: props.orderIds }),
        });

        const data = await res.json();

        if (!res.ok) {
            error.value = (data?.message as string) ?? t('pageDealer.loadingRoute.errorGeneric');

            return;
        }

        result.value = data as LoadingRouteResult;
    } catch {
        error.value = t('pageDealer.loadingRoute.errorNetwork');
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    if (props.warehouseConfigured && props.orderIds.length > 0) {
        void compute();
    }
});

function printPage() {
    window.print();
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.loadingRoute.pageTitle')" />

    <div class="flex flex-col gap-4 p-3 sm:gap-5 sm:p-4 lg:gap-6 lg:p-6 print:p-2">
        <!-- Header -->
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between print:hidden">
            <div class="flex items-center gap-3">
                <Button variant="ghost" size="sm" @click="router.get('/dealer/orders')">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                    <Truck class="h-5 w-5 text-primary" />
                </div>
                <div class="min-w-0">
                    <h1 class="text-lg font-bold tracking-tight sm:text-xl lg:text-2xl">
                        {{ t('pageDealer.loadingRoute.pageTitle') }}
                    </h1>
                    <p class="text-xs text-muted-foreground sm:text-sm">
                        {{ t('pageDealer.loadingRoute.pageSubtitle', { count: orderIds.length }) }}
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <Button v-if="result" variant="outline" @click="printPage">
                    <Printer class="mr-2 h-4 w-4" />
                    {{ t('pageDealer.loadingRoute.print') }}
                </Button>
            </div>
        </div>

        <!-- Warehouse not configured -->
        <Card v-if="!warehouseConfigured" class="border-amber-300 bg-amber-50/50">
            <CardContent class="flex flex-col items-center gap-3 p-6 text-center sm:p-8">
                <AlertTriangle class="h-10 w-10 text-amber-600" />
                <h2 class="text-base font-semibold sm:text-lg">
                    {{ t('pageDealer.loadingRoute.warehouseRequiredTitle') }}
                </h2>
                <p class="max-w-md text-sm text-muted-foreground">
                    {{ t('pageDealer.loadingRoute.warehouseRequiredDesc') }}
                </p>
                <Button @click="router.get('/dealer/settings/warehouse')">
                    <Settings class="mr-2 h-4 w-4" />
                    {{ t('pageDealer.loadingRoute.configureWarehouse') }}
                </Button>
            </CardContent>
        </Card>

        <!-- No orders -->
        <Card v-else-if="orderIds.length === 0" class="border-dashed">
            <CardContent class="flex flex-col items-center gap-3 p-8 text-center">
                <Package class="h-10 w-10 text-muted-foreground/30" />
                <p class="text-sm text-muted-foreground">{{ t('pageDealer.loadingRoute.errorNoOrders') }}</p>
                <Button variant="outline" @click="router.get('/dealer/orders')">
                    {{ t('pageDealer.loadingRoute.backToOrders') }}
                </Button>
            </CardContent>
        </Card>

        <!-- Loading -->
        <div v-else-if="loading" class="flex flex-col items-center justify-center gap-3 py-16">
            <Loader2 class="h-10 w-10 animate-spin text-primary" />
            <p class="text-sm font-medium">{{ t('pageDealer.loadingRoute.computing') }}</p>
            <p class="text-xs text-muted-foreground">{{ t('pageDealer.loadingRoute.computingHint') }}</p>
        </div>

        <!-- Error -->
        <Card v-else-if="error" class="border-rose-300 bg-rose-50">
            <CardContent class="flex flex-col items-center gap-2 p-6 text-center">
                <AlertTriangle class="h-8 w-8 text-rose-600" />
                <p class="text-sm font-medium text-rose-700">{{ error }}</p>
                <Button variant="outline" @click="compute">
                    {{ t('pageDealer.loadingRoute.retry') }}
                </Button>
            </CardContent>
        </Card>

        <!-- Result -->
        <template v-else-if="result">
            <!-- Summary cards -->
            <div class="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4">
                <Card class="gap-0 py-0">
                    <CardContent class="px-3 py-2 sm:px-4 sm:py-3">
                        <p class="text-[10px] text-muted-foreground sm:text-xs">
                            {{ t('pageDealer.loadingRoute.statStops') }}
                        </p>
                        <p class="mt-0.5 text-base font-bold tabular-nums sm:text-lg">
                            {{ result.delivery_sequence.length }}
                        </p>
                    </CardContent>
                </Card>
                <Card class="gap-0 py-0">
                    <CardContent class="px-3 py-2 sm:px-4 sm:py-3">
                        <p class="text-[10px] text-muted-foreground sm:text-xs">
                            {{ t('pageDealer.loadingRoute.statTotalDistance') }}
                        </p>
                        <p class="mt-0.5 text-base font-bold tabular-nums sm:text-lg">
                            {{ formatDistance(totalDistanceWithReturn) }}
                        </p>
                    </CardContent>
                </Card>
                <Card class="gap-0 py-0">
                    <CardContent class="px-3 py-2 sm:px-4 sm:py-3">
                        <p class="text-[10px] text-muted-foreground sm:text-xs">
                            {{ t('pageDealer.loadingRoute.statTotalDuration') }}
                        </p>
                        <p class="mt-0.5 text-base font-bold tabular-nums sm:text-lg">
                            {{ formatDuration(totalDurationWithReturn) }}
                        </p>
                    </CardContent>
                </Card>
                <Card class="gap-0 py-0">
                    <CardContent class="px-3 py-2 sm:px-4 sm:py-3">
                        <p class="text-[10px] text-muted-foreground sm:text-xs">
                            {{ t('pageDealer.loadingRoute.statReturn') }}
                        </p>
                        <p class="mt-0.5 text-base font-bold tabular-nums sm:text-lg">
                            {{ formatDistance(result.return_distance_meters) }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Mode tabs -->
            <div class="flex gap-2 rounded-lg bg-muted p-1 print:hidden">
                <button
                    type="button"
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                    :class="mode === 'loading' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    @click="mode = 'loading'"
                >
                    <Package class="h-4 w-4" />
                    {{ t('pageDealer.loadingRoute.tabLoading') }}
                </button>
                <button
                    type="button"
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                    :class="mode === 'delivery' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    @click="mode = 'delivery'"
                >
                    <Truck class="h-4 w-4" />
                    {{ t('pageDealer.loadingRoute.tabDelivery') }}
                </button>
            </div>

            <!-- Mode hint -->
            <div
                v-if="mode === 'loading'"
                class="rounded-md bg-orange-50 px-3 py-2 text-xs text-orange-800 dark:bg-orange-950/30 dark:text-orange-200 print:hidden"
            >
                {{ t('pageDealer.loadingRoute.loadingHint') }}
            </div>
            <div
                v-else
                class="rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200 print:hidden"
            >
                {{ t('pageDealer.loadingRoute.deliveryHint') }}
            </div>

            <!-- Map (only delivery mode) -->
            <div v-if="mode === 'delivery' && mapPoints.length > 0" class="print:hidden">
                <OrdersMap :orders="mapPoints" :preordered="true" height="h-[300px] sm:h-[400px]" />
            </div>

            <!-- List -->
            <LoadingRouteList :result="result" :mode="mode" />

            <!-- Skipped -->
            <Card
                v-if="result.skipped.length > 0"
                class="border-amber-200 bg-amber-50 print:hidden"
            >
                <CardContent class="p-3 text-xs text-amber-800">
                    <p class="font-semibold">
                        {{ t('pageDealer.loadingRoute.skippedTitle', { count: result.skipped.length }) }}
                    </p>
                    <ul class="mt-1 list-inside list-disc">
                        <li v-for="s in result.skipped" :key="s.order_id">
                            #{{ s.order_id }} {{ s.shop_name ?? '—' }}
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </template>
    </div>
</template>
