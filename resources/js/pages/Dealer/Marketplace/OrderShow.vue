<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useUnitLabel } from '@/composables/useUnitLabel';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoneySum } from '@/lib/format';

type OrderItem = {
    id: number;
    product_name: string;
    price: number;
    qty: number;
    subtotal: number;
    unit: string | null;
};
type MarketplaceOrder = {
    id: number;
    number: number;
    status_label: string;
    total: number;
    currency: string;
    note: string | null;
    seller?: { id: number; name: string; phone: string | null };
    items?: OrderItem[];
};

defineProps<{ order: { data: MarketplaceOrder } }>();

const { t } = useI18n();
const unitLabel = useUnitLabel();

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head
        :title="
            t('pageDealer.marketplaceOrderShow.title', {
                number: order.data.number,
            })
        "
    />

    <div class="mx-auto w-full max-w-2xl p-3 md:p-6">
        <div class="mb-4 flex items-center gap-2.5">
            <Button
                variant="ghost"
                size="icon"
                class="h-9 w-9"
                @click="router.get('/dealer/marketplace/orders')"
            >
                <ArrowLeft class="h-4.5 w-4.5" />
            </Button>
            <div>
                <h1 class="text-lg font-bold">
                    {{
                        t('pageDealer.marketplaceOrderShow.title', {
                            number: order.data.number,
                        })
                    }}
                </h1>
                <Badge class="text-[10px]">{{ order.data.status_label }}</Badge>
            </div>
        </div>

        <Card class="mb-3">
            <CardContent class="p-4 text-sm">
                <p>
                    <span class="text-muted-foreground">{{
                        t('pageDealer.marketplaceOrderShow.seller')
                    }}</span>
                    {{ order.data.seller?.name }}
                </p>
                <p v-if="order.data.seller?.phone">
                    <span class="text-muted-foreground">{{
                        t('pageDealer.marketplaceOrderShow.phone')
                    }}</span>
                    {{ order.data.seller.phone }}
                </p>
                <p v-if="order.data.note" class="mt-1">
                    <span class="text-muted-foreground">{{
                        t('pageDealer.marketplaceOrderShow.note')
                    }}</span>
                    {{ order.data.note }}
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="p-0">
                <div
                    v-for="it in order.data.items"
                    :key="it.id"
                    class="flex items-center justify-between border-b px-4 py-2.5 text-sm last:border-0"
                >
                    <div>
                        <p class="font-medium">{{ it.product_name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ it.qty }} {{ unitLabel(it.unit) }} ×
                            {{ formatMoneySum(it.price, order.data.currency) }}
                        </p>
                    </div>
                    <p class="font-semibold">
                        {{ formatMoneySum(it.subtotal, order.data.currency) }}
                    </p>
                </div>
                <div
                    class="flex items-center justify-between px-4 py-3 text-sm font-bold"
                >
                    <span>{{
                        t('pageDealer.marketplaceOrderShow.total')
                    }}</span>
                    <span class="text-primary">{{
                        formatMoneySum(order.data.total, order.data.currency)
                    }}</span>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
