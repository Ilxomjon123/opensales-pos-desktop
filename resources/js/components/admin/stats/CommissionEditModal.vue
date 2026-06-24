<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Bike, CalendarClock, Package, Percent, Store, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { currencySymbol, formatMoney } from '@/lib/format';

export type CommissionType = 'turnover_percentage' | 'fixed_per_shop' | 'fixed_per_order' | 'fixed_per_deliveryman' | 'fixed_monthly';

const props = defineProps<{
    dealerId: number;
    dealerName: string;
    commissionType: CommissionType;
    feeRate: number;
    fixedAmount: number | null;
}>();

const emit = defineEmits<{ close: [] }>();

const { t } = useI18n();

const selectedType = ref<CommissionType>(props.commissionType);
const percentage = ref<number>(props.feeRate || 0);
const fixedValue = ref<number>(props.fixedAmount ?? 0);
const submitting = ref(false);

const isPercentage = computed(() => selectedType.value === 'turnover_percentage');
const isFixedPerShop = computed(() => selectedType.value === 'fixed_per_shop');
const isFixedPerDeliveryman = computed(() => selectedType.value === 'fixed_per_deliveryman');
const isFixedMonthly = computed(() => selectedType.value === 'fixed_monthly');

const previewPercentage = computed(() => Number(percentage.value || 0).toFixed(2));
const previewFixed = computed(() => formatMoney(Number(fixedValue.value || 0)));

type TypeOption = {
    value: CommissionType;
    title: string;
    subtitle: string;
    icon: typeof Percent;
    accent: string;
    accentBg: string;
};

const options = computed<TypeOption[]>(() => [
    {
        value: 'turnover_percentage',
        title: t('pageAdmin.stats.commissionModal.turnoverTitle'),
        subtitle: t('pageAdmin.stats.commissionModal.turnoverSubtitle'),
        icon: Percent,
        accent: 'text-sky-500',
        accentBg: 'bg-sky-500/10',
    },
    {
        value: 'fixed_per_shop',
        title: t('pageAdmin.stats.commissionModal.perShopTitle'),
        subtitle: t('pageAdmin.stats.commissionModal.perShopSubtitle'),
        icon: Store,
        accent: 'text-violet-500',
        accentBg: 'bg-violet-500/10',
    },
    {
        value: 'fixed_per_order',
        title: t('pageAdmin.stats.commissionModal.perOrderTitle'),
        subtitle: t('pageAdmin.stats.commissionModal.perOrderSubtitle'),
        icon: Package,
        accent: 'text-emerald-500',
        accentBg: 'bg-emerald-500/10',
    },
    {
        value: 'fixed_per_deliveryman',
        title: t('pageAdmin.stats.commissionModal.perDeliverymanTitle'),
        subtitle: t('pageAdmin.stats.commissionModal.perDeliverymanSubtitle'),
        icon: Bike,
        accent: 'text-amber-500',
        accentBg: 'bg-amber-500/10',
    },
    {
        value: 'fixed_monthly',
        title: t('pageAdmin.stats.commissionModal.monthlyTitle'),
        subtitle: t('pageAdmin.stats.commissionModal.monthlySubtitle'),
        icon: CalendarClock,
        accent: 'text-rose-500',
        accentBg: 'bg-rose-500/10',
    },
]);

const fixedFieldLabel = computed(() => {
    if (isFixedPerShop.value) {
        return t('pageAdmin.stats.commissionModal.fixedLabelPerShop');
    }

    if (isFixedPerDeliveryman.value) {
        return t('pageAdmin.stats.commissionModal.fixedLabelPerDeliveryman');
    }

    if (isFixedMonthly.value) {
        return t('pageAdmin.stats.commissionModal.fixedLabelMonthly');
    }

    return t('pageAdmin.stats.commissionModal.fixedLabelPerOrder');
});

const fixedFieldHint = computed(() => {
    const amount = `${previewFixed.value} ${currencySymbol()}`;

    if (isFixedPerShop.value) {
        return t('pageAdmin.stats.commissionModal.hintPerShop', { amount });
    }

    if (isFixedPerDeliveryman.value) {
        return t('pageAdmin.stats.commissionModal.hintPerDeliveryman', { amount });
    }

    if (isFixedMonthly.value) {
        return t('pageAdmin.stats.commissionModal.hintMonthly', { amount });
    }

    return t('pageAdmin.stats.commissionModal.hintPerOrder', { amount });
});

const canSave = computed(() => {
    if (isPercentage.value) {
        return percentage.value >= 0 && percentage.value <= 100;
    }

    return fixedValue.value > 0;
});

function save() {
    if (!canSave.value || submitting.value) {
        return;
    }

    submitting.value = true;

    const payload: Record<string, number | string | null> = {
        commission_type: selectedType.value,
    };

    if (isPercentage.value) {
        payload.platform_fee_rate = percentage.value;
        payload.fixed_commission_amount = null;
    } else {
        payload.platform_fee_rate = null;
        payload.fixed_commission_amount = fixedValue.value;
    }

    router.patch(`/admin/dealers/${props.dealerId}/commission`, payload, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onFinish: () => {
            submitting.value = false;
        },
    });
}
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-md"
        @click.self="emit('close')"
    >
        <div
            class="w-full max-w-md overflow-hidden rounded-2xl border border-border/60 bg-card shadow-2xl"
            role="dialog"
            aria-modal="true"
        >
            <!-- Header -->
            <div class="flex items-start justify-between gap-3 px-5 pb-3 pt-5">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-muted-foreground">
                        {{ t('pageAdmin.stats.commissionModal.eyebrow') }}
                    </p>
                    <h2 class="mt-1 truncate text-lg font-semibold leading-tight">{{ dealerName }}</h2>
                </div>
                <button
                    type="button"
                    class="-mr-1 -mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    @click="emit('close')"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Body -->
            <div class="space-y-5 px-5 pb-5">
                <!-- Type options -->
                <div>
                    <Label class="mb-2 block text-xs font-medium text-muted-foreground">{{ t('pageAdmin.stats.commissionModal.typeLabel') }}</Label>
                    <div class="space-y-1.5">
                        <button
                            v-for="opt in options"
                            :key="opt.value"
                            type="button"
                            class="group relative flex w-full items-center gap-3 rounded-xl border p-3 text-left transition-all duration-150"
                            :class="
                                selectedType === opt.value
                                    ? 'border-primary/70 bg-primary/[0.06] ring-1 ring-primary/30'
                                    : 'border-border/60 hover:border-border hover:bg-muted/40'
                            "
                            @click="selectedType = opt.value"
                        >
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition-colors"
                                :class="[opt.accentBg, opt.accent]"
                            >
                                <component :is="opt.icon" class="h-4.5 w-4.5" :stroke-width="2.25" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold leading-tight">{{ opt.title }}</p>
                                <p class="mt-0.5 truncate text-xs text-muted-foreground">{{ opt.subtitle }}</p>
                            </div>

                            <span
                                class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 transition-colors"
                                :class="
                                    selectedType === opt.value
                                        ? 'border-primary'
                                        : 'border-border group-hover:border-muted-foreground/40'
                                "
                            >
                                <span
                                    v-if="selectedType === opt.value"
                                    class="h-1.5 w-1.5 rounded-full bg-primary"
                                />
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Value input -->
                <div class="space-y-2">
                    <Label
                        :for="isPercentage ? 'commission_pct' : 'commission_fixed'"
                        class="text-xs font-medium text-muted-foreground"
                    >
                        {{ isPercentage ? t('pageAdmin.stats.commissionModal.percentLabel') : fixedFieldLabel }}
                    </Label>

                    <div v-if="isPercentage" class="relative">
                        <Input
                            id="commission_pct"
                            v-model.number="percentage"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            class="h-12 pr-12 text-lg font-semibold tabular-nums"
                            autofocus
                        />
                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm font-medium text-muted-foreground">
                            %
                        </span>
                    </div>

                    <div v-else class="relative">
                        <Input
                            id="commission_fixed"
                            v-model.number="fixedValue"
                            type="number"
                            min="1"
                            step="1000"
                            class="h-12 pr-16 text-lg font-semibold tabular-nums"
                            autofocus
                        />
                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm font-medium text-muted-foreground">
                            {{ currencySymbol() }}
                        </span>
                    </div>

                    <p class="flex items-start gap-1.5 text-xs leading-relaxed text-muted-foreground">
                        <span class="mt-1 h-1 w-1 shrink-0 rounded-full bg-muted-foreground/40" />
                        <span v-if="isPercentage">
                            {{ t('pageAdmin.stats.commissionModal.percentHintPrefix') }}
                            <span class="font-semibold text-foreground tabular-nums">{{ previewPercentage }}%</span>
                            {{ t('pageAdmin.stats.commissionModal.percentHintSuffix') }}
                        </span>
                        <span v-else>{{ fixedFieldHint }}</span>
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-end gap-2 border-t border-border/60 bg-muted/20 px-5 py-3">
                <Button variant="ghost" type="button" class="h-9" @click="emit('close')">
                    {{ t('pageAdmin.stats.commissionModal.cancel') }}
                </Button>
                <Button :disabled="!canSave || submitting" type="button" class="h-9 min-w-[110px]" @click="save">
                    {{ submitting ? t('pageAdmin.stats.commissionModal.saving') : t('pageAdmin.stats.commissionModal.save') }}
                </Button>
            </div>
        </div>
    </div>
</template>
