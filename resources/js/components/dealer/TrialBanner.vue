<script setup lang="ts">
import { Clock, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    trial: {
        ends_at: string;
        days_left: number;
        expired: boolean;
    };
}>();

const { t } = useI18n();

const expired = computed(() => props.trial.expired);
</script>

<template>
    <div
        class="flex items-center gap-3 rounded-xl border p-3 sm:p-4"
        :class="expired
            ? 'border-destructive/30 bg-destructive/5'
            : 'border-amber-500/30 bg-amber-500/5'"
    >
        <div
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
            :class="expired ? 'bg-destructive/10 text-destructive' : 'bg-amber-500/15 text-amber-600 dark:text-amber-400'"
        >
            <TriangleAlert v-if="expired" class="h-4.5 w-4.5" />
            <Clock v-else class="h-4.5 w-4.5" />
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold">
                {{ expired ? t('trialBanner.expiredTitle') : t('trialBanner.activeTitle', { days: props.trial.days_left }) }}
            </p>
            <p class="text-xs text-muted-foreground">
                {{ expired ? t('trialBanner.expiredText') : t('trialBanner.activeText') }}
            </p>
        </div>
    </div>
</template>
