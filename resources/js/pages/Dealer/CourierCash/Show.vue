<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowUpRight,
    Banknote,
    ClipboardList,
    HandCoins,
    MessageSquare,
    Phone,
    ReceiptText,
    User as UserIcon,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCurrency } from '@/composables/useCurrency';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/format';
import courierCash from '@/routes/dealer/courier-cash';

type Deliveryman = { id: number; name: string | null; phone: string | null };

type CashPayment = {
    id: number;
    amount: number;
    note: string | null;
    created_at: string | null;
    shop: { id: number; name: string | null; phone: string | null } | null;
    order: { id: number; number: number | null } | null;
};

type Settlement = {
    id: number;
    amount: number;
    note: string | null;
    settled_at: string | null;
    settled_by?: { id: number; name: string | null } | null;
};

const props = defineProps<{
    deliveryman: Deliveryman;
    totals: { collected: number; settled: number; balance: number };
    payments: CashPayment[];
    settlements: { data: Settlement[] };
    can_settle: boolean;
}>();

defineOptions({ layout: AppLayout });

const { t, locale } = useI18n();
const { symbol, formatWithSymbol } = useCurrency();

const settleOpen = ref(false);

const form = useForm({
    amount: 0,
    note: '',
});

const remaining = computed<number>(() =>
    Math.max(0, props.totals.balance - (form.amount || 0)),
);

function setAll() {
    form.amount = props.totals.balance;
}

function setQuick(delta: number) {
    form.amount = Math.min(
        props.totals.balance,
        Math.max(0, (form.amount || 0) + delta),
    );
}

function clearAmount() {
    form.amount = 0;
}

function submit() {
    form.post(courierCash.settle(props.deliveryman.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            settleOpen.value = false;
            form.reset();
        },
    });
}

const dateLocale = computed<string>(() => {
    const l = locale.value;

    if (l === 'ru') {
        return 'ru-RU';
    }

    if (l === 'uz-Cyrl') {
        return 'uz-Cyrl-UZ';
    }

    return 'uz-UZ';
});

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    const d = new Date(iso);

    if (Number.isNaN(d.getTime())) {
        return '—';
    }

    return d.toLocaleString(dateLocale.value, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

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

const displayName = computed<string>(
    () => props.deliveryman.name ?? `#${props.deliveryman.id}`,
);
const initials = computed<string>(() => {
    const name = props.deliveryman.name ?? '';
    const parts = name.trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '#';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return (parts[0][0] + parts[1][0]).toUpperCase();
});
</script>

<template>
    <Head
        :title="
            t('pageDealer.courierCash.showHeadTitle', { name: displayName })
        "
    />

    <div class="flex flex-col gap-3 p-3 sm:gap-5 sm:p-6">
        <!-- Header -->
        <div class="flex items-center gap-2.5 sm:gap-3">
            <Link
                v-if="can_settle"
                href="/dealer/courier-cash"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border bg-card text-muted-foreground transition hover:bg-muted hover:text-foreground"
            >
                <ArrowLeft class="h-4 w-4" />
            </Link>
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/15 text-sm font-bold text-primary ring-2 ring-primary/20 sm:h-12 sm:w-12 sm:text-base"
            >
                {{ initials }}
            </div>
            <div class="min-w-0 flex-1">
                <h1
                    class="truncate text-base leading-tight font-bold sm:text-xl"
                >
                    {{ displayName }}
                </h1>
                <p
                    v-if="deliveryman.phone"
                    class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground"
                >
                    <Phone class="h-3 w-3" />{{ deliveryman.phone }}
                </p>
            </div>
            <Button
                v-if="can_settle && totals.balance > 0"
                size="sm"
                class="sm:size-default shrink-0 bg-emerald-600 text-white shadow-sm hover:bg-emerald-700"
                @click="settleOpen = true"
            >
                <HandCoins class="h-4 w-4 sm:mr-1.5" />
                <span class="hidden sm:inline">{{
                    t('pageDealer.courierCash.modal.openButton')
                }}</span>
            </Button>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-2 sm:gap-3">
            <div class="rounded-lg border bg-card p-3">
                <div
                    class="flex items-center justify-between gap-1 text-xs text-muted-foreground"
                >
                    <span class="truncate">{{
                        t('pageDealer.courierCash.totals.collected')
                    }}</span>
                    <Banknote class="h-3.5 w-3.5 shrink-0 text-blue-500" />
                </div>
                <p
                    class="mt-1.5 font-mono text-lg leading-none font-bold tabular-nums sm:mt-2 sm:text-2xl"
                >
                    <span class="sm:hidden">{{
                        shortMoney(totals.collected)
                    }}</span>
                    <span class="hidden sm:inline">{{
                        formatMoney(totals.collected)
                    }}</span>
                </p>
                <p class="mt-0.5 text-[10px] text-muted-foreground sm:text-xs">
                    {{ symbol }}
                </p>
            </div>

            <div
                class="rounded-lg border bg-card p-3"
                :class="
                    totals.settled > 0
                        ? 'border-emerald-300/60 bg-emerald-500/5 dark:border-emerald-500/40 dark:bg-emerald-500/10'
                        : ''
                "
            >
                <div
                    class="flex items-center justify-between gap-1 text-xs text-muted-foreground"
                >
                    <span class="truncate">{{
                        t('pageDealer.courierCash.totals.settled')
                    }}</span>
                    <ReceiptText
                        class="h-3.5 w-3.5 shrink-0"
                        :class="totals.settled > 0 ? 'text-emerald-500' : ''"
                    />
                </div>
                <p
                    class="mt-1.5 font-mono text-lg leading-none font-bold tabular-nums sm:mt-2 sm:text-2xl"
                    :class="
                        totals.settled > 0
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : 'text-muted-foreground'
                    "
                >
                    <span class="sm:hidden">{{
                        shortMoney(totals.settled)
                    }}</span>
                    <span class="hidden sm:inline">{{
                        formatMoney(totals.settled)
                    }}</span>
                </p>
                <p
                    class="mt-0.5 text-[10px] sm:text-xs"
                    :class="
                        totals.settled > 0
                            ? 'text-emerald-600/70 dark:text-emerald-400/70'
                            : 'text-muted-foreground'
                    "
                >
                    {{ symbol }}
                </p>
            </div>

            <div
                class="rounded-lg border bg-card p-3"
                :class="
                    totals.balance > 0
                        ? 'border-rose-300/60 bg-rose-500/5 dark:border-rose-500/40 dark:bg-rose-500/10'
                        : ''
                "
            >
                <div
                    class="flex items-center justify-between gap-1 text-xs text-muted-foreground"
                >
                    <span class="truncate">{{
                        t('pageDealer.courierCash.totals.balance')
                    }}</span>
                    <Wallet
                        class="h-3.5 w-3.5 shrink-0"
                        :class="totals.balance > 0 ? 'text-rose-500' : ''"
                    />
                </div>
                <p
                    class="mt-1.5 font-mono text-lg leading-none font-bold tabular-nums sm:mt-2 sm:text-2xl"
                    :class="
                        totals.balance > 0
                            ? 'text-rose-600 dark:text-rose-400'
                            : 'text-muted-foreground/60'
                    "
                >
                    <span class="sm:hidden">{{
                        shortMoney(totals.balance)
                    }}</span>
                    <span class="hidden sm:inline">{{
                        formatMoney(totals.balance)
                    }}</span>
                </p>
                <p
                    class="mt-0.5 text-[10px] sm:text-xs"
                    :class="
                        totals.balance > 0
                            ? 'text-rose-600/70 dark:text-rose-400/70'
                            : 'text-muted-foreground'
                    "
                >
                    {{ symbol }}
                </p>
            </div>
        </div>

        <div class="grid gap-3 lg:grid-cols-2 lg:gap-4">
            <!-- Payments -->
            <Card class="overflow-hidden py-0">
                <div
                    class="flex items-center justify-between border-b bg-muted/20 px-3 py-2.5 sm:px-4"
                >
                    <div class="flex items-center gap-2">
                        <div
                            class="flex h-6 w-6 items-center justify-center rounded-md bg-rose-500/15 text-rose-600 sm:h-7 sm:w-7 dark:text-rose-400"
                        >
                            <Banknote class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                        </div>
                        <div class="leading-tight">
                            <p class="text-sm font-semibold">
                                {{ t('pageDealer.courierCash.payments.title') }}
                            </p>
                            <p
                                class="hidden text-[10px] text-muted-foreground sm:block"
                            >
                                {{
                                    t(
                                        'pageDealer.courierCash.payments.subtitle',
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                    <span
                        class="rounded-full bg-background px-2 py-0.5 text-xs font-bold tabular-nums"
                    >
                        {{ payments.length }}
                    </span>
                </div>
                <div
                    v-if="payments.length === 0"
                    class="flex flex-col items-center gap-2 p-8 text-center"
                >
                    <Banknote class="h-7 w-7 text-muted-foreground/30" />
                    <p class="text-xs text-muted-foreground sm:text-sm">
                        {{ t('pageDealer.courierCash.payments.empty') }}
                    </p>
                </div>
                <div
                    v-else
                    class="max-h-[480px] divide-y overflow-y-auto sm:max-h-[560px]"
                >
                    <div
                        v-for="p in payments"
                        :key="p.id"
                        class="flex items-center justify-between gap-2 px-3 py-2 transition hover:bg-muted/30 sm:px-4 sm:py-2.5"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <Link
                                    v-if="p.order"
                                    :href="`/dealer/orders/${p.order.id}`"
                                    class="inline-flex items-center gap-0.5 text-sm font-semibold text-primary hover:underline"
                                >
                                    #{{ p.order.number ?? p.order.id }}
                                    <ArrowUpRight class="h-3 w-3" />
                                </Link>
                                <span
                                    v-else
                                    class="text-sm text-muted-foreground"
                                    >—</span
                                >
                                <span
                                    v-if="p.shop"
                                    class="truncate text-xs text-muted-foreground"
                                    >· {{ p.shop.name }}</span
                                >
                            </div>
                            <p
                                class="text-[10px] leading-tight text-muted-foreground"
                            >
                                {{ formatDateTime(p.created_at) }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p
                                class="font-mono text-sm leading-tight font-bold text-rose-600 tabular-nums dark:text-rose-400"
                            >
                                <span class="sm:hidden"
                                    >+{{ shortMoney(p.amount) }}</span
                                >
                                <span class="hidden sm:inline"
                                    >+{{ formatMoney(p.amount) }}</span
                                >
                            </p>
                            <p class="text-[9px] text-muted-foreground">
                                {{ symbol }}
                            </p>
                        </div>
                    </div>
                </div>
            </Card>

            <!-- Settlements -->
            <Card class="overflow-hidden py-0">
                <div
                    class="flex items-center justify-between border-b bg-muted/20 px-3 py-2.5 sm:px-4"
                >
                    <div class="flex items-center gap-2">
                        <div
                            class="flex h-6 w-6 items-center justify-center rounded-md bg-emerald-500/15 text-emerald-600 sm:h-7 sm:w-7 dark:text-emerald-400"
                        >
                            <ClipboardList class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
                        </div>
                        <p class="text-sm font-semibold">
                            {{ t('pageDealer.courierCash.settlements.title') }}
                        </p>
                    </div>
                    <span
                        class="rounded-full bg-background px-2 py-0.5 text-xs font-bold tabular-nums"
                    >
                        {{ settlements.data.length }}
                    </span>
                </div>
                <div
                    v-if="settlements.data.length === 0"
                    class="flex flex-col items-center gap-2 p-8 text-center"
                >
                    <ClipboardList class="h-7 w-7 text-muted-foreground/30" />
                    <p class="text-xs text-muted-foreground sm:text-sm">
                        {{ t('pageDealer.courierCash.settlements.empty') }}
                    </p>
                </div>
                <div
                    v-else
                    class="max-h-[480px] divide-y overflow-y-auto sm:max-h-[560px]"
                >
                    <div
                        v-for="s in settlements.data"
                        :key="s.id"
                        class="flex items-start justify-between gap-2 px-3 py-2 transition hover:bg-muted/30 sm:px-4 sm:py-2.5"
                    >
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-[10px] leading-tight text-muted-foreground"
                            >
                                {{ formatDateTime(s.settled_at) }}
                            </p>
                            <div
                                class="mt-0.5 flex flex-wrap items-center gap-x-2 text-[11px] sm:text-xs"
                            >
                                <span
                                    v-if="s.settled_by"
                                    class="flex items-center gap-0.5 text-muted-foreground"
                                >
                                    <UserIcon class="h-2.5 w-2.5" />{{
                                        s.settled_by.name
                                    }}
                                </span>
                                <span
                                    v-if="s.note"
                                    class="truncate text-muted-foreground/80 italic"
                                    >"{{ s.note }}"</span
                                >
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <p
                                class="font-mono text-sm leading-tight font-bold text-emerald-600 tabular-nums dark:text-emerald-400"
                            >
                                <span class="sm:hidden"
                                    >−{{ shortMoney(s.amount) }}</span
                                >
                                <span class="hidden sm:inline"
                                    >−{{ formatMoney(s.amount) }}</span
                                >
                            </p>
                            <p class="text-[9px] text-muted-foreground">
                                {{ symbol }}
                            </p>
                        </div>
                    </div>
                </div>
            </Card>
        </div>

        <Dialog v-model:open="settleOpen">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-300"
                        >
                            <HandCoins class="h-5 w-5" />
                        </div>
                        <div>
                            <DialogTitle>{{
                                t('pageDealer.courierCash.modal.title')
                            }}</DialogTitle>
                            <DialogDescription class="mt-0.5">
                                {{
                                    t('pageDealer.courierCash.modal.subtitle', {
                                        name: displayName,
                                    })
                                }}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <form @submit.prevent="submit" class="space-y-4 pt-2">
                    <div
                        class="flex items-center justify-between rounded-lg border border-rose-500/30 bg-rose-500/5 px-3 py-2.5 dark:bg-rose-500/10"
                    >
                        <span
                            class="flex items-center gap-1.5 text-sm text-muted-foreground"
                        >
                            <Wallet class="h-3.5 w-3.5" />
                            {{
                                t('pageDealer.courierCash.modal.currentBalance')
                            }}
                        </span>
                        <span
                            class="font-mono text-base font-bold text-rose-600 dark:text-rose-300"
                        >
                            {{ formatWithSymbol(totals.balance) }}
                        </span>
                    </div>

                    <div>
                        <Label class="mb-1.5 flex items-center gap-1.5">
                            <Banknote
                                class="h-3.5 w-3.5 text-muted-foreground"
                            />
                            {{ t('pageDealer.courierCash.modal.amountLabel') }}
                            <span class="text-destructive">*</span>
                        </Label>
                        <div class="relative">
                            <Input
                                type="number"
                                v-model.number="form.amount"
                                :max="totals.balance"
                                min="1"
                                placeholder="0"
                                class="h-11 pr-14 font-mono text-lg"
                            />
                            <span
                                class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground"
                            >
                                {{
                                    t(
                                        'pageDealer.courierCash.modal.amountSuffix',
                                    )
                                }}
                            </span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <button
                                type="button"
                                class="rounded-full border border-emerald-500/40 px-2.5 py-0.5 text-xs font-medium text-emerald-700 transition hover:bg-emerald-500/10 dark:text-emerald-300"
                                @click="setAll"
                            >
                                {{
                                    t('pageDealer.courierCash.modal.full', {
                                        amount: formatMoney(totals.balance),
                                    })
                                }}
                            </button>
                            <button
                                v-for="q in [100_000, 500_000, 1_000_000]"
                                :key="q"
                                type="button"
                                class="rounded-full border px-2.5 py-0.5 text-xs text-muted-foreground transition hover:border-primary/50 hover:text-foreground"
                                @click="setQuick(q)"
                            >
                                +{{ formatMoney(q) }}
                            </button>
                            <button
                                v-if="form.amount > 0"
                                type="button"
                                class="rounded-full border px-2.5 py-0.5 text-xs text-destructive transition hover:bg-destructive/10"
                                @click="clearAmount"
                            >
                                {{ t('pageDealer.courierCash.modal.clear') }}
                            </button>
                        </div>
                        <p
                            v-if="form.errors.amount"
                            class="mt-1 text-sm text-destructive"
                        >
                            {{ form.errors.amount }}
                        </p>

                        <div
                            v-if="form.amount > 0"
                            class="mt-2.5 flex items-center justify-between rounded-lg border bg-muted/30 px-3 py-2 text-sm"
                            :class="
                                remaining > 0
                                    ? 'border-rose-500/30 bg-rose-500/5 dark:bg-rose-500/10'
                                    : 'border-emerald-500/30 bg-emerald-500/5 dark:bg-emerald-500/10'
                            "
                        >
                            <span class="text-muted-foreground">{{
                                t('pageDealer.courierCash.modal.remainingAfter')
                            }}</span>
                            <span
                                class="font-mono font-bold"
                                :class="
                                    remaining > 0
                                        ? 'text-rose-600 dark:text-rose-300'
                                        : 'text-emerald-600 dark:text-emerald-300'
                                "
                            >
                                {{ formatWithSymbol(remaining) }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <Label class="mb-1.5 flex items-center gap-1.5">
                            <MessageSquare
                                class="h-3.5 w-3.5 text-muted-foreground"
                            />
                            {{ t('pageDealer.courierCash.modal.note') }}
                            <span
                                class="ml-1 text-xs font-normal text-muted-foreground"
                                >{{
                                    t(
                                        'pageDealer.courierCash.modal.noteOptional',
                                    )
                                }}</span
                            >
                        </Label>
                        <Input
                            v-model="form.note"
                            :placeholder="
                                t(
                                    'pageDealer.courierCash.modal.notePlaceholder',
                                )
                            "
                        />
                    </div>

                    <DialogFooter class="gap-2 pt-1">
                        <DialogClose as-child>
                            <Button variant="outline" type="button">{{
                                t('pageDealer.courierCash.modal.cancel')
                            }}</Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            class="min-w-[120px] bg-emerald-600 text-white hover:bg-emerald-700"
                            :disabled="
                                form.processing ||
                                !form.amount ||
                                form.amount > totals.balance
                            "
                        >
                            {{
                                form.processing
                                    ? t(
                                          'pageDealer.courierCash.modal.submitting',
                                      )
                                    : t('pageDealer.courierCash.modal.submit')
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
