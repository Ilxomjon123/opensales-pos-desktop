<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { PackageCheck, Ban } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { PaginationBar } from '@/components/ui/pagination-bar';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoneySum } from '@/lib/format';
import type { Paginated } from '@/types';

type MarketplaceOrder = {
    id: number;
    number: number;
    status: string;
    status_label: string;
    total: number;
    currency: string;
    seller?: { id: number; name: string; phone: string | null };
    items?: {
        id: number;
        product_name: string;
        qty: number;
        subtotal: number;
    }[];
    created_at: string;
};

const { t } = useI18n();

defineProps<{
    orders: Paginated<MarketplaceOrder>;
    filters: { status?: string };
    statuses: { value: string; label: string }[];
}>();

const statusTone: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700',
    assembling: 'bg-blue-100 text-blue-700',
    delivering: 'bg-indigo-100 text-indigo-700',
    delivered: 'bg-emerald-100 text-emerald-700',
    received: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
};

function filterStatus(value: string) {
    router.get(
        '/dealer/marketplace/orders',
        { status: value || undefined },
        { preserveState: true, replace: true },
    );
}

function receive(o: MarketplaceOrder) {
    router.post(
        `/dealer/marketplace/orders/${o.id}/receive`,
        {},
        { preserveScroll: true },
    );
}

function cancel(o: MarketplaceOrder) {
    if (confirm(t('pageDealer.marketplaceOrders.confirmCancel'))) {
        router.post(
            `/dealer/marketplace/orders/${o.id}/cancel`,
            {},
            { preserveScroll: true },
        );
    }
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.marketplaceOrders.title')" />

    <div class="mx-auto w-full max-w-4xl p-3 md:p-6">
        <h1 class="mb-1 text-lg font-bold tracking-tight sm:text-xl">
            {{ t('pageDealer.marketplaceOrders.title') }}
        </h1>
        <p class="mb-4 text-xs text-muted-foreground">
            {{ t('pageDealer.marketplaceOrders.subtitle') }}
        </p>

        <div class="mb-3 flex flex-wrap gap-1.5">
            <button
                class="rounded-full border px-3 py-1 text-xs"
                :class="
                    !filters.status
                        ? 'border-primary bg-primary/10 text-primary'
                        : ''
                "
                @click="filterStatus('')"
            >
                {{ t('pageDealer.marketplaceOrders.all') }}
            </button>
            <button
                v-for="s in statuses"
                :key="s.value"
                class="rounded-full border px-3 py-1 text-xs"
                :class="
                    filters.status === s.value
                        ? 'border-primary bg-primary/10 text-primary'
                        : ''
                "
                @click="filterStatus(s.value)"
            >
                {{ s.label }}
            </button>
        </div>

        <div
            v-if="orders.data.length === 0"
            class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            {{ t('pageDealer.marketplaceOrders.empty') }}
        </div>

        <div v-else class="space-y-2.5">
            <Card v-for="o in orders.data" :key="o.id">
                <CardContent class="p-3.5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold"
                                    >#{{ o.number }}</span
                                >
                                <Badge
                                    :class="statusTone[o.status]"
                                    class="border-0 text-[10px]"
                                    >{{ o.status_label }}</Badge
                                >
                            </div>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ o.seller?.name }}
                            </p>
                            <p class="mt-1 text-[11px] text-muted-foreground">
                                {{
                                    t(
                                        'pageDealer.marketplaceOrders.itemsCount',
                                        { count: o.items?.length ?? 0 },
                                    )
                                }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-primary">
                                {{ formatMoneySum(o.total, o.currency) }}
                            </p>
                            <div class="mt-1.5 flex justify-end gap-1.5">
                                <Button
                                    v-if="o.status === 'delivered'"
                                    size="sm"
                                    class="h-7 text-xs"
                                    @click="receive(o)"
                                >
                                    <PackageCheck class="mr-1 h-3 w-3" />
                                    {{
                                        t(
                                            'pageDealer.marketplaceOrders.received',
                                        )
                                    }}
                                </Button>
                                <Button
                                    v-if="
                                        o.status === 'pending' ||
                                        o.status === 'assembling'
                                    "
                                    size="sm"
                                    variant="outline"
                                    class="h-7 text-xs text-destructive"
                                    @click="cancel(o)"
                                >
                                    <Ban class="mr-1 h-3 w-3" />
                                    {{
                                        t('pageDealer.marketplaceOrders.cancel')
                                    }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <PaginationBar
            v-if="orders.data.length"
            :links="orders.links"
            :meta="orders.meta"
            class="mt-4"
        />
    </div>
</template>
