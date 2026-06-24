<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { currencySymbol, formatMoney, formatMonth } from '@/lib/format';

export type MonthPoint = { month: string; revenue: number; orders: number; discount?: number };

const props = defineProps<{
    data: MonthPoint[];
    title?: string;
}>();

const maxRevenue = computed(() => Math.max(...props.data.map((p) => p.revenue), 1));
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ title ?? "12 oylik aylanma trendi" }}</CardTitle>
        </CardHeader>
        <CardContent>
            <div>
                <div class="flex h-48 gap-2">
                    <div
                        v-for="m in data"
                        :key="m.month"
                        class="group relative flex flex-1 flex-col justify-end"
                    >
                        <div
                            class="w-full rounded-t-md bg-gradient-to-t from-indigo-600 to-indigo-400 transition-all hover:opacity-80"
                            :style="{ height: `${(m.revenue / maxRevenue) * 100}%`, minHeight: m.revenue > 0 ? '6px' : '0' }"
                        />
                        <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-popover px-3 py-2 text-xs shadow-lg group-hover:block">
                            <p class="font-medium">{{ formatMonth(m.month) }}</p>
                            <p>Aylanma: <span class="font-mono font-semibold">{{ formatMoney(m.revenue) }}</span> {{ currencySymbol() }}</p>
                            <p v-if="(m.discount ?? 0) > 0" class="text-rose-500">
                                Chegirma: <span class="font-mono">{{ formatMoney(m.discount ?? 0) }}</span> {{ currencySymbol() }}
                            </p>
                            <p>Buyurtmalar: <span class="font-mono">{{ m.orders }}</span></p>
                        </div>
                    </div>
                </div>
                <div class="mt-1.5 flex gap-2">
                    <p
                        v-for="m in data"
                        :key="m.month"
                        class="flex-1 text-center text-[10px] text-muted-foreground"
                    >
                        {{ formatMonth(m.month) }}
                    </p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
