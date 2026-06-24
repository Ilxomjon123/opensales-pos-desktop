<script setup lang="ts">
import { AlertTriangle, AlertCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useUnitLabel } from '@/composables/useUnitLabel';

const props = defineProps<{
    stock: number;
    minStock?: number | null;
    unit?: string;
    packSize?: number;
    stockPacks?: number;
    showPacks?: boolean;
}>();

const { t } = useI18n();
const unitLabel = useUnitLabel();

type Level = 'ok' | 'low' | 'out' | 'negative';

const level = computed<Level>(() => {
    if (props.stock < 0) {
        return 'negative';
    }

    if (props.stock === 0) {
        return 'out';
    }

    if (
        props.minStock !== null &&
        props.minStock !== undefined &&
        props.stock <= props.minStock
    ) {
        return 'low';
    }

    return 'ok';
});

const styles: Record<Level, string> = {
    ok: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
    low: 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/40',
    out: 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/40',
    negative:
        'bg-rose-600/20 text-rose-800 dark:text-rose-200 border-rose-600/50 font-bold',
};

const labels = computed<{ main: string; sub: string | null }>(() => {
    const u = unitLabel(props.unit);

    switch (level.value) {
        case 'negative':
            return {
                main: `${props.stock} ${u}`,
                sub: t('pageDealer.products.stockBadge.debt'),
            };
        case 'out':
            return { main: t('pageDealer.products.stockBadge.out'), sub: null };
        case 'low':
            return {
                main: `${props.stock} ${u}`,
                sub: t('pageDealer.products.stockBadge.low'),
            };
        default:
            return { main: `${props.stock} ${u}`, sub: null };
    }
});
</script>

<template>
    <div class="inline-flex flex-col items-end gap-0.5">
        <span
            class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs font-medium"
            :class="styles[level]"
        >
            <AlertCircle v-if="level === 'negative'" class="h-3 w-3" />
            <AlertTriangle
                v-else-if="level === 'out' || level === 'low'"
                class="h-3 w-3"
            />
            {{ labels.main }}
        </span>
        <span
            v-if="labels.sub"
            class="text-[10px]"
            :class="{
                'font-semibold text-rose-600 dark:text-rose-400':
                    level === 'negative' || level === 'out',
                'text-amber-600 dark:text-amber-400': level === 'low',
            }"
        >
            {{ labels.sub }}
        </span>
        <span
            v-if="showPacks && (packSize ?? 1) > 1 && stockPacks !== undefined"
            class="text-[10px] text-muted-foreground"
        >
            ≈ {{ stockPacks }} {{ t('pageDealer.products.stockBadge.pack') }}
        </span>
    </div>
</template>
