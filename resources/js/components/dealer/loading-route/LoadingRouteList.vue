<script setup lang="ts">
import { ArrowRight, MapPin, Package, Phone, Warehouse } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { useCurrency } from '@/composables/useCurrency';
import {
    formatDistance,
    formatDuration,
    type LoadingRouteResult,
} from './types';

const { t } = useI18n();
const { formatWithSymbol } = useCurrency();

const props = defineProps<{
    result: LoadingRouteResult;
    mode: 'loading' | 'delivery';
}>();

const stops = computed(() =>
    props.mode === 'loading'
        ? props.result.loading_sequence
        : props.result.delivery_sequence,
);

function shopLocation(stop: (typeof stops.value)[number]): string {
    const parts = [
        stop.payload.shop_region,
        stop.payload.shop_district,
        stop.payload.shop_address,
    ]
        .map((p) => (p ?? '').trim())
        .filter((p) => p.length > 0);

    return parts.join(' · ');
}

function navigatorUrl(latitude: number, longitude: number): string {
    return `https://yandex.com/maps/?rtext=~${latitude},${longitude}&rtt=auto`;
}
</script>

<template>
    <div class="space-y-3">
        <!-- Ombor — boshlanish nuqtasi (faqat delivery rejimida birinchi) -->
        <div
            v-if="mode === 'delivery'"
            class="flex items-start gap-3 rounded-lg border border-primary/30 bg-primary/5 p-3"
        >
            <div
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/15"
            >
                <Warehouse class="h-4 w-4 text-primary" />
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold">{{
                        t('pageDealer.loadingRoute.warehouseLabel')
                    }}</span>
                    <Badge variant="outline" class="text-[10px]">{{
                        t('pageDealer.loadingRoute.start')
                    }}</Badge>
                </div>
                <p
                    v-if="result.warehouse.address"
                    class="mt-0.5 text-xs text-muted-foreground"
                >
                    {{ result.warehouse.address }}
                </p>
            </div>
        </div>

        <!-- Stops -->
        <div
            v-for="(stop, idx) in stops"
            :key="stop.payload.order_id"
            class="flex items-start gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/30"
        >
            <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full font-bold text-white"
                :class="mode === 'loading' ? 'bg-orange-500' : 'bg-emerald-600'"
            >
                {{
                    mode === 'loading'
                        ? stop.loading_position
                        : stop.delivery_position
                }}
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold">{{
                        stop.payload.shop_name
                    }}</span>
                    <span
                        class="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground"
                    >
                        #{{ stop.payload.order_number }}
                    </span>
                    <Badge variant="outline" class="text-[10px]">{{
                        stop.payload.order_status_label
                    }}</Badge>
                </div>

                <div
                    v-if="shopLocation(stop)"
                    class="mt-1 flex items-start gap-1 text-xs text-muted-foreground"
                >
                    <MapPin class="mt-0.5 h-3 w-3 shrink-0" />
                    <span>{{ shopLocation(stop) }}</span>
                </div>

                <div
                    class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs"
                >
                    <span class="font-mono font-semibold tabular-nums">{{
                        formatWithSymbol(stop.payload.order_total)
                    }}</span>
                    <span
                        v-if="stop.payload.shop_phone"
                        class="inline-flex items-center gap-1 text-muted-foreground"
                    >
                        <Phone class="h-3 w-3" />
                        {{ stop.payload.shop_phone }}
                    </span>
                    <span
                        v-if="mode === 'delivery'"
                        class="inline-flex items-center gap-1 text-muted-foreground"
                    >
                        <ArrowRight class="h-3 w-3" />
                        {{ formatDistance(stop.distance_from_prev_m) }} ·
                        {{ formatDuration(stop.duration_from_prev_s) }}
                    </span>
                </div>

                <a
                    v-if="mode === 'delivery'"
                    :href="
                        navigatorUrl(
                            stop.payload.shop_latitude,
                            stop.payload.shop_longitude,
                        )
                    "
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                >
                    <MapPin class="h-3 w-3" />
                    {{ t('pageDealer.loadingRoute.openInMap') }}
                </a>
            </div>
        </div>

        <div
            v-if="stops.length === 0"
            class="rounded-lg border border-dashed p-6 text-center"
        >
            <Package class="mx-auto h-8 w-8 text-muted-foreground/30" />
            <p class="mt-2 text-sm text-muted-foreground">
                {{ t('pageDealer.loadingRoute.empty') }}
            </p>
        </div>

        <!-- Return to warehouse (delivery mode) -->
        <div
            v-if="mode === 'delivery' && result.return_distance_meters > 0"
            class="flex items-start gap-3 rounded-lg border border-dashed bg-muted/20 p-3"
        >
            <div
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted"
            >
                <Warehouse class="h-4 w-4 text-muted-foreground" />
            </div>
            <div class="min-w-0 flex-1">
                <span class="font-medium text-muted-foreground">
                    {{ t('pageDealer.loadingRoute.returnToWarehouse') }}
                </span>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    {{ formatDistance(result.return_distance_meters) }} ·
                    {{ formatDuration(result.return_duration_seconds) }}
                </p>
            </div>
        </div>
    </div>
</template>
