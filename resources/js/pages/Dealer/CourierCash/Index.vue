<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Banknote,
    HandCoins,
    Phone,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { Card, CardContent } from '@/components/ui/card';
import { useCurrency } from '@/composables/useCurrency';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/format';
import courierCash from '@/routes/dealer/courier-cash';

type Row = {
    id: number;
    name: string | null;
    phone: string | null;
    balance: number;
};

defineProps<{
    rows: Row[];
    summary: {
        deliverymen: number;
        total_balance: number;
        with_balance: number;
    };
}>();

const { t } = useI18n();
const { symbol } = useCurrency();

defineOptions({ layout: AppLayout });

function shortMoney(amount: number): string {
    if (amount >= 1_000_000) {
        const m = amount / 1_000_000;

        return (
            (m >= 10
                ? Math.round(m).toString()
                : m.toFixed(1).replace(/\.0$/, '')) + 'M'
        );
    }

    if (amount >= 1_000) {
        const k = amount / 1_000;

        return (
            (k >= 10
                ? Math.round(k).toString()
                : k.toFixed(1).replace(/\.0$/, '')) + 'K'
        );
    }

    return amount.toString();
}

function initials(name: string | null): string {
    if (!name) {
        return '#';
    }

    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '#';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return (parts[0][0] + parts[1][0]).toUpperCase();
}
</script>

<template>
    <Head :title="t('pageDealer.courierCash.headTitle')" />

    <div class="flex flex-col gap-3 p-3 sm:gap-5 sm:p-6">
        <div>
            <h1
                class="text-lg leading-tight font-bold tracking-tight sm:text-2xl"
            >
                {{ t('pageDealer.courierCash.indexTitle') }}
            </h1>
            <p class="mt-0.5 text-xs text-muted-foreground sm:text-sm">
                {{ t('pageDealer.courierCash.indexSubtitle') }}
            </p>
        </div>

        <div class="grid grid-cols-3 gap-2 sm:gap-3">
            <div
                class="rounded-lg border bg-card p-3 transition hover:border-primary/30"
            >
                <div
                    class="flex items-center justify-between gap-1 text-xs text-muted-foreground"
                >
                    <span class="truncate">{{
                        t('pageDealer.courierCash.summary.deliverymen')
                    }}</span>
                    <Users class="h-3.5 w-3.5 shrink-0" />
                </div>
                <p
                    class="mt-1.5 text-xl leading-none font-bold sm:mt-2 sm:text-2xl"
                >
                    {{ summary.deliverymen }}
                </p>
            </div>

            <div
                class="rounded-lg border bg-card p-3 transition hover:border-amber-400/40"
                :class="
                    summary.with_balance > 0
                        ? 'border-amber-300/50 dark:border-amber-500/30'
                        : ''
                "
            >
                <div
                    class="flex items-center justify-between gap-1 text-xs text-muted-foreground"
                >
                    <span class="truncate">{{
                        t('pageDealer.courierCash.summary.withBalance')
                    }}</span>
                    <HandCoins
                        class="h-3.5 w-3.5 shrink-0"
                        :class="
                            summary.with_balance > 0 ? 'text-amber-500' : ''
                        "
                    />
                </div>
                <p
                    class="mt-1.5 text-xl leading-none font-bold sm:mt-2 sm:text-2xl"
                    :class="
                        summary.with_balance > 0
                            ? 'text-amber-600 dark:text-amber-400'
                            : ''
                    "
                >
                    {{ summary.with_balance }}
                </p>
            </div>

            <div
                class="rounded-lg border bg-card p-3 transition hover:border-rose-400/40"
                :class="
                    summary.total_balance > 0
                        ? 'border-rose-300/50 dark:border-rose-500/30'
                        : ''
                "
            >
                <div
                    class="flex items-center justify-between gap-1 text-xs text-muted-foreground"
                >
                    <span class="truncate">{{
                        t('pageDealer.courierCash.summary.totalBalance')
                    }}</span>
                    <Banknote
                        class="h-3.5 w-3.5 shrink-0"
                        :class="
                            summary.total_balance > 0 ? 'text-rose-500' : ''
                        "
                    />
                </div>
                <p
                    class="mt-1.5 font-mono text-xl leading-none font-bold tabular-nums sm:mt-2 sm:text-2xl"
                    :class="
                        summary.total_balance > 0
                            ? 'text-rose-600 dark:text-rose-400'
                            : ''
                    "
                >
                    <span class="sm:hidden">{{
                        shortMoney(summary.total_balance)
                    }}</span>
                    <span class="hidden sm:inline">{{
                        formatMoney(summary.total_balance)
                    }}</span>
                </p>
                <p class="mt-0.5 text-[10px] text-muted-foreground sm:text-xs">
                    {{ symbol }}
                </p>
            </div>
        </div>

        <Card v-if="rows.length === 0">
            <CardContent
                class="flex flex-col items-center gap-3 p-10 text-center sm:p-12"
            >
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-full bg-muted"
                >
                    <Wallet class="h-7 w-7 text-muted-foreground/60" />
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ t('pageDealer.courierCash.empty') }}
                </p>
            </CardContent>
        </Card>

        <Card v-else class="overflow-hidden py-0">
            <div class="divide-y">
                <Link
                    v-for="r in rows"
                    :key="r.id"
                    :href="courierCash.show(r.id).url"
                    class="group flex items-center justify-between gap-2 px-3 py-2.5 transition hover:bg-muted/40 active:bg-muted/60 sm:px-5 sm:py-3.5"
                >
                    <div class="flex min-w-0 items-center gap-2.5 sm:gap-3">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold ring-2 sm:h-10 sm:w-10 sm:text-sm"
                            :class="
                                r.balance > 0
                                    ? 'bg-rose-500/15 text-rose-700 ring-rose-500/30 dark:text-rose-300'
                                    : 'bg-muted text-muted-foreground/70 ring-border'
                            "
                        >
                            {{ initials(r.name) }}
                        </div>
                        <div class="min-w-0">
                            <p
                                class="truncate text-sm leading-tight font-semibold sm:text-base"
                            >
                                {{ r.name ?? `#${r.id}` }}
                            </p>
                            <p
                                v-if="r.phone"
                                class="flex items-center gap-1 text-[11px] leading-tight text-muted-foreground"
                            >
                                <Phone class="h-2.5 w-2.5 shrink-0" />{{
                                    r.phone
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-1.5 sm:gap-3">
                        <div class="text-right">
                            <p
                                class="font-mono text-sm leading-none font-bold tabular-nums sm:text-base"
                                :class="
                                    r.balance > 0
                                        ? 'text-rose-600 dark:text-rose-400'
                                        : 'text-muted-foreground/50'
                                "
                            >
                                <span class="sm:hidden">{{
                                    shortMoney(r.balance)
                                }}</span>
                                <span class="hidden sm:inline">{{
                                    formatMoney(r.balance)
                                }}</span>
                            </p>
                            <p class="mt-0.5 text-[10px] text-muted-foreground">
                                {{ symbol }}
                            </p>
                        </div>
                        <ArrowRight
                            class="h-4 w-4 text-muted-foreground/40 transition group-hover:translate-x-0.5 group-hover:text-foreground"
                        />
                    </div>
                </Link>
            </div>
        </Card>
    </div>
</template>
