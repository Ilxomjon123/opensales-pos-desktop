<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowUpRight,
    Banknote,
    CreditCard,
    MessageSquare,
    Store,
    User,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogTrigger,
    DialogFooter,
    DialogClose,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useCurrency } from '@/composables/useCurrency';
import { formatMoney } from '@/lib/format';
import type { Shop } from '@/types';

const { t } = useI18n();
const { formatWithSymbol, symbol } = useCurrency();

const props = defineProps<{
    shops: Shop[];
    open: boolean;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const form = useForm({
    shop_id: '' as string,
    amount: 0,
    type: 'credit' as string,
    method: 'cash' as 'cash' | 'card',
    cardholder_name: '',
    note: '',
});

const shopsSortedByDebt = computed<Shop[]>(() =>
    [...props.shops].sort((a, b) => Math.abs(b.balance) - Math.abs(a.balance)),
);

const selectedShop = computed<Shop | null>(() => {
    if (!form.shop_id) {
        return null;
    }

    return props.shops.find((s) => String(s.id) === form.shop_id) ?? null;
});

const newBalance = computed<number | null>(() => {
    if (!selectedShop.value || !form.amount) {
        return null;
    }

    const delta = form.type === 'credit' ? form.amount : -form.amount;

    return selectedShop.value.balance + delta;
});

function setQuickAmount(delta: number) {
    form.amount = Math.max(0, (form.amount || 0) + delta);
}

function submit() {
    if (form.type !== 'credit') {
        form.method = 'cash';
        form.cardholder_name = '';
    } else if (form.method !== 'card') {
        form.cardholder_name = '';
    }

    form.post('/dealer/finance/payments', {
        onSuccess: () => {
            emit('update:open', false);
            form.reset();
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogTrigger as-child>
            <Button>{{ t('pageDealer.finance.addPayment') }}</Button>
        </DialogTrigger>
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Wallet class="h-5 w-5" />
                    </div>
                    <div>
                        <DialogTitle>{{
                            t('pageDealer.finance.addPaymentTitle')
                        }}</DialogTitle>
                        <DialogDescription class="mt-0.5">
                            {{ t('pageDealer.finance.addPaymentDesc') }}
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-5 pt-2">
                <!-- Tur — segmented toggle -->
                <div>
                    <Label class="mb-2 block">{{
                        t('pageDealer.finance.operationType')
                    }}</Label>
                    <div class="grid grid-cols-2 gap-2 rounded-lg border p-1">
                        <button
                            type="button"
                            class="flex items-center justify-center gap-2 rounded-md py-2.5 text-sm font-medium transition-colors"
                            :class="
                                form.type === 'credit'
                                    ? 'bg-emerald-500/10 text-emerald-600 ring-1 ring-emerald-500/30'
                                    : 'text-muted-foreground hover:bg-muted/50'
                            "
                            @click="form.type = 'credit'"
                        >
                            <ArrowDownLeft class="h-4 w-4" />
                            {{ t('pageDealer.finance.receivePayment') }}
                        </button>
                        <button
                            type="button"
                            class="flex items-center justify-center gap-2 rounded-md py-2.5 text-sm font-medium transition-colors"
                            :class="
                                form.type === 'debit'
                                    ? 'bg-rose-500/10 text-rose-600 ring-1 ring-rose-500/30'
                                    : 'text-muted-foreground hover:bg-muted/50'
                            "
                            @click="form.type = 'debit'"
                        >
                            <ArrowUpRight class="h-4 w-4" />
                            {{ t('pageDealer.finance.writeDebt') }}
                        </button>
                    </div>
                </div>

                <!-- Mijoz -->
                <div>
                    <Label class="mb-2 flex items-center gap-1.5">
                        <Store class="h-3.5 w-3.5 text-muted-foreground" />
                        {{ t('pageDealer.finance.customerRequired') }}
                        <span class="text-destructive">*</span>
                    </Label>
                    <SearchableSelect
                        v-model="form.shop_id"
                        :items="shopsSortedByDebt"
                        value-key="id"
                        label-key="name"
                        :placeholder="t('pageDealer.finance.selectCustomer')"
                        :search-placeholder="
                            t('pageDealer.finance.customerNamePlaceholder')
                        "
                        :empty-text="t('pageDealer.finance.customerNotFound')"
                    >
                        <template #selected="{ item }">
                            <span class="flex min-w-0 items-center gap-2">
                                <span class="truncate font-medium">{{
                                    item.name
                                }}</span>
                                <span
                                    class="font-mono text-xs"
                                    :class="
                                        item.balance < 0
                                            ? 'text-rose-500'
                                            : 'text-emerald-600'
                                    "
                                >
                                    {{ formatWithSymbol(item.balance) }}
                                </span>
                            </span>
                        </template>
                        <template #item-suffix="{ item }">
                            <span
                                class="shrink-0 font-mono text-xs"
                                :class="
                                    item.balance < 0
                                        ? 'text-rose-500'
                                        : 'text-emerald-600'
                                "
                            >
                                {{ formatMoney(item.balance) }}
                            </span>
                        </template>
                    </SearchableSelect>
                    <p
                        v-if="form.errors.shop_id"
                        class="mt-1 text-sm text-destructive"
                    >
                        {{ form.errors.shop_id }}
                    </p>
                </div>

                <!-- Summa -->
                <div>
                    <Label class="mb-2 flex items-center gap-1.5">
                        <Banknote class="h-3.5 w-3.5 text-muted-foreground" />
                        {{ t('pageDealer.finance.amountRequired') }}
                        <span class="text-destructive">*</span>
                    </Label>
                    <div class="relative">
                        <Input
                            type="number"
                            v-model.number="form.amount"
                            placeholder="0"
                            class="pr-14 font-mono text-lg"
                        />
                        <span
                            class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground"
                        >
                            {{ symbol }}
                        </span>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <button
                            v-for="q in [
                                100_000, 500_000, 1_000_000, 5_000_000,
                            ]"
                            :key="q"
                            type="button"
                            class="rounded-full border px-2.5 py-0.5 text-xs text-muted-foreground transition hover:border-primary/50 hover:text-foreground"
                            @click="setQuickAmount(q)"
                        >
                            +{{ formatMoney(q) }}
                        </button>
                        <button
                            v-if="form.amount > 0"
                            type="button"
                            class="rounded-full border px-2.5 py-0.5 text-xs text-destructive transition hover:bg-destructive/10"
                            @click="form.amount = 0"
                        >
                            {{ t('pageDealer.finance.clear') }}
                        </button>
                    </div>
                    <p
                        v-if="form.errors.amount"
                        class="mt-1 text-sm text-destructive"
                    >
                        {{ form.errors.amount }}
                    </p>

                    <div
                        v-if="newBalance !== null"
                        class="mt-3 flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                        :class="
                            newBalance < 0
                                ? 'border-rose-500/30 bg-rose-500/5'
                                : 'border-emerald-500/30 bg-emerald-500/5'
                        "
                    >
                        <span class="text-muted-foreground">{{
                            t('pageDealer.finance.newBalance')
                        }}</span>
                        <span
                            class="font-mono font-bold"
                            :class="
                                newBalance < 0
                                    ? 'text-rose-500'
                                    : 'text-emerald-600'
                            "
                        >
                            {{ formatWithSymbol(newBalance) }}
                        </span>
                    </div>
                </div>

                <!-- To'lov turi (faqat To'lov qabul qilish uchun) -->
                <div v-if="form.type === 'credit'">
                    <Label class="mb-2 block">{{
                        t('pageDealer.finance.method')
                    }}</Label>
                    <div class="grid grid-cols-2 gap-2 rounded-lg border p-1">
                        <button
                            type="button"
                            class="flex items-center justify-center gap-2 rounded-md py-2.5 text-sm font-medium transition-colors"
                            :class="
                                form.method === 'cash'
                                    ? 'bg-primary/10 text-primary ring-1 ring-primary/30'
                                    : 'text-muted-foreground hover:bg-muted/50'
                            "
                            @click="form.method = 'cash'"
                        >
                            <Banknote class="h-4 w-4" />
                            {{ t('pageDealer.finance.cash') }}
                        </button>
                        <button
                            type="button"
                            class="flex items-center justify-center gap-2 rounded-md py-2.5 text-sm font-medium transition-colors"
                            :class="
                                form.method === 'card'
                                    ? 'bg-primary/10 text-primary ring-1 ring-primary/30'
                                    : 'text-muted-foreground hover:bg-muted/50'
                            "
                            @click="form.method = 'card'"
                        >
                            <CreditCard class="h-4 w-4" />
                            {{ t('pageDealer.finance.card') }}
                        </button>
                    </div>
                </div>

                <!-- Karta egasi -->
                <div v-if="form.type === 'credit' && form.method === 'card'">
                    <Label class="mb-2 flex items-center gap-1.5">
                        <User class="h-3.5 w-3.5 text-muted-foreground" />
                        {{ t('pageDealer.finance.cardholderFull') }}
                        <span class="text-destructive">*</span>
                    </Label>
                    <Input
                        v-model="form.cardholder_name"
                        :placeholder="
                            t('pageDealer.finance.cardholderPlaceholder')
                        "
                    />
                    <p
                        v-if="form.errors.cardholder_name"
                        class="mt-1 text-sm text-destructive"
                    >
                        {{ form.errors.cardholder_name }}
                    </p>
                </div>

                <!-- Izoh -->
                <div>
                    <Label class="mb-2 flex items-center gap-1.5">
                        <MessageSquare
                            class="h-3.5 w-3.5 text-muted-foreground"
                        />
                        {{ t('pageDealer.finance.note') }}
                        <span
                            class="ml-1 text-xs font-normal text-muted-foreground"
                            >{{ t('pageDealer.finance.noteOptional') }}</span
                        >
                    </Label>
                    <Input
                        v-model="form.note"
                        :placeholder="t('pageDealer.finance.notePlaceholder')"
                    />
                </div>

                <DialogFooter class="gap-2 pt-2">
                    <DialogClose as-child>
                        <Button variant="outline" type="button">{{
                            t('pageDealer.finance.cancel')
                        }}</Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        :disabled="
                            form.processing || !form.shop_id || !form.amount
                        "
                        class="min-w-[120px]"
                    >
                        {{
                            form.processing
                                ? t('pageDealer.finance.saving')
                                : t('pageDealer.finance.save')
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
