<script setup lang="ts">
import { ArrowLeft, Search, Store } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCurrency } from '@/composables/useCurrency';
import type { Order } from '@/types';

type ShopOpt = { id: number; name: string };

type ReturnableOrder = {
    id: number;
    number: number | null;
    status: string;
    delivered_at: string | null;
    total: number;
    shop: { id: number; name: string; balance: number } | null;
    items: Order['items'];
};

const props = defineProps<{
    open: boolean;
    shops: ShopOpt[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    pick: [order: Order];
}>();

const { t } = useI18n();
const { formatWithSymbol } = useCurrency();

const selectedShop = ref<ShopOpt | null>(null);
const orders = ref<ReturnableOrder[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const shopSearch = ref('');

const filteredShops = computed<ShopOpt[]>(() => {
    const q = shopSearch.value.trim().toLowerCase();

    if (!q) {
        return props.shops;
    }

    return props.shops.filter((s) => s.name.toLowerCase().includes(q));
});

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        selectedShop.value = null;
        orders.value = [];
        loading.value = false;
        error.value = null;
        shopSearch.value = '';
    },
);

async function pickShop(shop: ShopOpt) {
    selectedShop.value = shop;
    orders.value = [];
    error.value = null;
    loading.value = true;

    try {
        const res = await fetch(
            `/dealer/shops-api/${shop.id}/returnable-orders`,
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );

        if (!res.ok) {
            throw new Error(t('pageDealer.orders.loadFailed'));
        }

        const json = await res.json();
        orders.value = json.data ?? [];

        if (orders.value.length === 0) {
            error.value = t('pageDealer.orders.noReturnableOrders');
        }
    } catch (e) {
        error.value =
            e instanceof Error
                ? e.message
                : t('pageDealer.orders.errorGeneric');
    } finally {
        loading.value = false;
    }
}

function backToShops() {
    selectedShop.value = null;
    orders.value = [];
    error.value = null;
}

function formatDate(iso: string | null): string {
    if (iso === null) {
        return '—';
    }

    return new Date(iso).toLocaleDateString('uz-UZ', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function itemCount(o: ReturnableOrder): number {
    return Array.isArray(o.items) ? o.items.length : 0;
}

function pickOrder(o: ReturnableOrder) {
    emit('pick', o as unknown as Order);
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent
            class="flex h-[100dvh] max-h-[100dvh] w-screen max-w-none flex-col gap-0 rounded-none border-0 p-0 sm:h-[min(800px,calc(100dvh-2rem))] sm:max-h-[calc(100dvh-2rem)] sm:w-[90vw] sm:max-w-4xl sm:rounded-lg sm:border lg:w-[80vw] lg:max-w-5xl xl:max-w-6xl"
            @open-auto-focus="(e: Event) => e.preventDefault()"
        >
            <DialogHeader class="border-b p-4 sm:p-6 sm:pb-4">
                <div class="flex items-center gap-2">
                    <Button
                        v-if="selectedShop !== null"
                        variant="ghost"
                        size="icon"
                        class="h-7 w-7 shrink-0"
                        @click="backToShops"
                    >
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                    <div class="min-w-0 flex-1">
                        <DialogTitle class="pr-8 text-base sm:text-lg">
                            <span v-if="selectedShop === null">{{
                                t('pageDealer.orders.selectShop')
                            }}</span>
                            <span v-else class="truncate">{{
                                t('pageDealer.orders.selectOrderForShop', {
                                    shop: selectedShop.name,
                                })
                            }}</span>
                        </DialogTitle>
                        <p class="text-xs text-muted-foreground">
                            <span v-if="selectedShop === null">
                                {{ t('pageDealer.orders.whichShopReturn') }}
                            </span>
                            <span v-else>
                                {{
                                    t('pageDealer.orders.clickReturnableOrder')
                                }}
                            </span>
                        </p>
                    </div>
                </div>
            </DialogHeader>

            <div
                v-if="selectedShop === null"
                class="flex flex-1 flex-col overflow-hidden"
            >
                <div class="border-b px-4 py-3 sm:px-6">
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <input
                            v-model="shopSearch"
                            type="text"
                            :placeholder="
                                t('pageDealer.orders.searchShopPlaceholder')
                            "
                            class="h-10 w-full rounded-md border border-input bg-background pr-3 pl-9 text-sm outline-none focus:ring-2 focus:ring-ring"
                        />
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-3 sm:p-4">
                    <div
                        v-if="filteredShops.length === 0"
                        class="rounded-lg border border-dashed bg-muted/20 px-3 py-8 text-center text-sm text-muted-foreground"
                    >
                        {{ t('pageDealer.orders.shopNotFound') }}
                    </div>
                    <div v-else class="space-y-1.5">
                        <button
                            v-for="s in filteredShops"
                            :key="s.id"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-lg border bg-background p-3 text-left transition-colors hover:border-primary/60 hover:bg-primary/[0.04]"
                            @click="pickShop(s)"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary/10"
                            >
                                <Store class="h-4 w-4 text-primary" />
                            </div>
                            <span
                                class="min-w-0 flex-1 truncate text-sm font-medium"
                                >{{ s.name }}</span
                            >
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="flex flex-1 flex-col overflow-y-auto p-4 sm:p-6">
                <p
                    v-if="loading"
                    class="rounded-lg border bg-muted/20 px-3 py-6 text-center text-sm text-muted-foreground"
                >
                    {{ t('pageDealer.orders.loading') }}
                </p>

                <div v-else-if="orders.length > 0" class="space-y-2">
                    <button
                        v-for="o in orders"
                        :key="o.id"
                        type="button"
                        class="flex w-full items-center justify-between gap-3 rounded-lg border bg-background p-3 text-left transition-colors hover:border-primary/60 hover:bg-primary/[0.04]"
                        @click="pickOrder(o)"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">
                                {{
                                    t('pageDealer.orders.orderNumber', {
                                        number: o.number ?? o.id,
                                    })
                                }}
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{
                                    t('pageDealer.orders.orderDateItems', {
                                        date: formatDate(o.delivered_at),
                                        count: itemCount(o),
                                    })
                                }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 font-mono text-sm font-semibold tabular-nums"
                        >
                            {{ formatWithSymbol(o.total) }}
                        </span>
                    </button>
                </div>

                <p
                    v-if="error"
                    class="mt-2 rounded bg-destructive/10 px-3 py-2 text-sm text-destructive"
                >
                    {{ error }}
                </p>
            </div>

            <DialogFooter class="border-t p-3 sm:p-6 sm:pt-4">
                <Button
                    variant="outline"
                    class="w-full sm:w-auto"
                    @click="emit('update:open', false)"
                    >{{ t('pageDealer.orders.cancelShort') }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
