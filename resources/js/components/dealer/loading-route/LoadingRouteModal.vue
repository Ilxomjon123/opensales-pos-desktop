<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { AlertTriangle, ExternalLink, Loader2, Package, Truck } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import LoadingRouteList from './LoadingRouteList.vue';
import { formatDistance, formatDuration, type LoadingRouteResult } from './types';

const { t } = useI18n();

const props = defineProps<{
    open: boolean;
    orderIds: number[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const loading = ref(false);
const error = ref<string | null>(null);
const warehouseNotConfigured = ref(false);
const result = ref<LoadingRouteResult | null>(null);
const mode = ref<'loading' | 'delivery'>('loading');

const orderCount = computed(() => props.orderIds.length);

async function compute() {
    if (props.orderIds.length === 0) {
        return;
    }

    loading.value = true;
    error.value = null;
    warehouseNotConfigured.value = false;
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
            warehouseNotConfigured.value = data?.code === 'warehouse_not_configured';

            return;
        }

        result.value = data as LoadingRouteResult;
    } catch {
        error.value = t('pageDealer.loadingRoute.errorNetwork');
    } finally {
        loading.value = false;
    }
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            mode.value = 'loading';
            void compute();
        } else {
            result.value = null;
            error.value = null;
            warehouseNotConfigured.value = false;
        }
    },
);

function openFullPage() {
    router.get('/dealer/orders/loading-route', { order_ids: props.orderIds });
}

function openWarehouseSettings() {
    router.get('/dealer/settings/warehouse');
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="flex max-h-[calc(100dvh-2rem)] flex-col gap-3 p-4 sm:max-w-2xl sm:gap-4 sm:p-6">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-base sm:text-lg">
                    <Truck class="h-5 w-5 text-primary" />
                    {{ t('pageDealer.loadingRoute.modalTitle') }}
                </DialogTitle>
                <DialogDescription>
                    {{ t('pageDealer.loadingRoute.modalDesc', { count: orderCount }) }}
                </DialogDescription>
            </DialogHeader>

            <!-- Loading state -->
            <div v-if="loading" class="flex flex-col items-center justify-center gap-3 py-10">
                <Loader2 class="h-8 w-8 animate-spin text-primary" />
                <p class="text-sm text-muted-foreground">{{ t('pageDealer.loadingRoute.computing') }}</p>
                <p class="text-xs text-muted-foreground">{{ t('pageDealer.loadingRoute.computingHint') }}</p>
            </div>

            <!-- Error state -->
            <div
                v-else-if="error"
                class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-rose-300 bg-rose-50 p-6 text-center"
            >
                <AlertTriangle class="h-8 w-8 text-rose-600" />
                <p class="text-sm font-medium text-rose-700">{{ error }}</p>
                <Button v-if="warehouseNotConfigured" class="mt-2" size="sm" @click="openWarehouseSettings">
                    {{ t('pageDealer.loadingRoute.configureWarehouse') }}
                </Button>
            </div>

            <!-- Result -->
            <div v-else-if="result" class="-mx-4 flex-1 overflow-y-auto px-4 sm:-mx-6 sm:px-6">
                <!-- Mode tabs -->
                <div class="mb-3 flex gap-2 rounded-lg bg-muted p-1">
                    <button
                        type="button"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="mode === 'loading' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="mode = 'loading'"
                    >
                        <Package class="h-4 w-4" />
                        {{ t('pageDealer.loadingRoute.tabLoading') }}
                    </button>
                    <button
                        type="button"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="mode === 'delivery' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="mode = 'delivery'"
                    >
                        <Truck class="h-4 w-4" />
                        {{ t('pageDealer.loadingRoute.tabDelivery') }}
                    </button>
                </div>

                <!-- Summary -->
                <div class="mb-3 grid grid-cols-2 gap-2 rounded-lg border bg-muted/30 p-3 text-xs">
                    <div>
                        <p class="text-muted-foreground">{{ t('pageDealer.loadingRoute.totalDistance') }}</p>
                        <p class="mt-0.5 font-semibold tabular-nums">{{ formatDistance(result.total_distance_meters + result.return_distance_meters) }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground">{{ t('pageDealer.loadingRoute.totalDuration') }}</p>
                        <p class="mt-0.5 font-semibold tabular-nums">{{ formatDuration(result.total_duration_seconds + result.return_duration_seconds) }}</p>
                    </div>
                </div>

                <!-- Mode hint -->
                <div
                    v-if="mode === 'loading'"
                    class="mb-3 rounded-md bg-orange-50 px-3 py-2 text-xs text-orange-800 dark:bg-orange-950/30 dark:text-orange-200"
                >
                    {{ t('pageDealer.loadingRoute.loadingHint') }}
                </div>
                <div
                    v-else
                    class="mb-3 rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200"
                >
                    {{ t('pageDealer.loadingRoute.deliveryHint') }}
                </div>

                <LoadingRouteList :result="result" :mode="mode" />

                <!-- Skipped -->
                <div
                    v-if="result.skipped.length > 0"
                    class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
                >
                    <p class="font-semibold">
                        {{ t('pageDealer.loadingRoute.skippedTitle', { count: result.skipped.length }) }}
                    </p>
                    <ul class="mt-1 list-inside list-disc">
                        <li v-for="s in result.skipped" :key="s.order_id">
                            #{{ s.order_id }} {{ s.shop_name ?? '—' }}
                        </li>
                    </ul>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="emit('update:open', false)">
                    {{ t('pageDealer.loadingRoute.close') }}
                </Button>
                <Button v-if="result" @click="openFullPage">
                    <ExternalLink class="mr-2 h-4 w-4" />
                    {{ t('pageDealer.loadingRoute.openFullPage') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
