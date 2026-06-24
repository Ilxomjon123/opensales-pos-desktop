<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/date';
import { formatMoneySum } from '@/lib/format';

export type DailyPoint = { date: string; count: number; total: number };

const props = defineProps<{
    data: DailyPoint[];
    title?: string;
}>();

const maxCount = computed(() => Math.max(...props.data.map((p) => p.count), 1));
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ title ?? "So'nggi 30 kun — buyurtmalar" }}</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="flex h-48 gap-[2px]">
                <div
                    v-for="point in data"
                    :key="point.date"
                    class="group relative flex flex-1 flex-col justify-end"
                >
                    <div
                        class="w-full rounded-t bg-primary transition-all hover:bg-primary/80"
                        :style="{ height: `${(point.count / maxCount) * 100}%`, minHeight: point.count > 0 ? '4px' : '0' }"
                    />
                    <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-popover px-2 py-1 text-xs shadow-md group-hover:block">
                        <div class="font-medium">{{ formatDate(point.date) }}</div>
                        <div>Buyurtmalar: {{ point.count }}</div>
                        <div>Summa: {{ formatMoneySum(point.total) }}</div>
                    </div>
                </div>
            </div>
            <div class="mt-2 flex justify-between text-xs text-muted-foreground">
                <span>{{ formatDate(data[0]?.date ?? '') }}</span>
                <span>{{ formatDate(data[Math.floor(data.length / 2)]?.date ?? '') }}</span>
                <span>{{ formatDate(data[data.length - 1]?.date ?? '') }}</span>
            </div>
        </CardContent>
    </Card>
</template>
