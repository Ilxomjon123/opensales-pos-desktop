<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useCurrency } from '@/composables/useCurrency';
import type { Shop } from '@/types';

const { formatWithSymbol } = useCurrency();

defineProps<{
    shops: Shop[];
    activeShopId?: string;
}>();

defineEmits<{ toggleShop: [shopId: number] }>();
</script>

<template>
    <div
        class="grid gap-3 sm:grid-cols-2 sm:gap-4 md:grid-cols-3 lg:grid-cols-4"
    >
        <Card
            v-for="shop in shops"
            :key="shop.id"
            class="cursor-pointer transition-colors hover:bg-muted/40"
            :class="
                activeShopId === String(shop.id)
                    ? 'border-primary ring-2 ring-primary/30'
                    : ''
            "
            @click="$emit('toggleShop', shop.id)"
        >
            <CardContent class="flex items-center justify-between gap-2 pt-6">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm text-muted-foreground">
                        {{ shop.name }}
                    </p>
                    <p
                        class="text-lg font-bold sm:text-xl"
                        :class="shop.balance < 0 ? 'text-destructive' : ''"
                    >
                        {{ formatWithSymbol(shop.balance) }}
                    </p>
                </div>
                <Badge
                    :variant="shop.balance >= 0 ? 'secondary' : 'destructive'"
                    class="shrink-0"
                >
                    {{ shop.balance >= 0 ? 'Musbat' : 'Qarz' }}
                </Badge>
            </CardContent>
        </Card>
    </div>
</template>
