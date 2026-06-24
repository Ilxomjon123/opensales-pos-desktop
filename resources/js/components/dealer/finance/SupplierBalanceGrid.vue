<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useCurrency } from '@/composables/useCurrency';

type SupplierOption = {
    id: number;
    name: string;
    balance: number;
};

const { formatWithSymbol } = useCurrency();

defineProps<{
    suppliers: SupplierOption[];
    activeSupplierId?: string;
}>();

defineEmits<{ toggleSupplier: [supplierId: number] }>();
</script>

<template>
    <div
        class="grid gap-3 sm:grid-cols-2 sm:gap-4 md:grid-cols-3 lg:grid-cols-4"
    >
        <Card
            v-for="supplier in suppliers"
            :key="supplier.id"
            class="cursor-pointer transition-colors hover:bg-muted/40"
            :class="
                activeSupplierId === String(supplier.id)
                    ? 'border-primary ring-2 ring-primary/30'
                    : ''
            "
            @click="$emit('toggleSupplier', supplier.id)"
        >
            <CardContent class="flex items-center justify-between gap-2 pt-6">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm text-muted-foreground">
                        {{ supplier.name }}
                    </p>
                    <p
                        class="text-lg font-bold sm:text-xl"
                        :class="supplier.balance < 0 ? 'text-destructive' : ''"
                    >
                        {{ formatWithSymbol(supplier.balance) }}
                    </p>
                </div>
                <Badge
                    :variant="
                        supplier.balance < 0 ? 'destructive' : 'secondary'
                    "
                    class="shrink-0"
                >
                    {{
                        supplier.balance < 0
                            ? 'Qarz'
                            : supplier.balance > 0
                              ? 'Ortiqcha'
                              : 'Nol'
                    }}
                </Badge>
            </CardContent>
        </Card>
    </div>
</template>
