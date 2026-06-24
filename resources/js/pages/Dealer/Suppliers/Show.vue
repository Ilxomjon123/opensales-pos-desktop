<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDownLeft,
    ArrowLeft,
    ArrowUpRight,
    Banknote,
    CreditCard,
    Edit,
    Phone,
    RotateCcw,
    Trash2,
    Truck,
    User,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import SupplierReturnModal from '@/components/dealer/suppliers/SupplierReturnModal.vue';
import InputError from '@/components/InputError.vue';

const { t } = useI18n();
const { formatWithSymbol } = useCurrency();
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCurrency } from '@/composables/useCurrency';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/format';
import type { Supplier, SupplierPayment, TransactionDetail } from '@/types';

type TxLite = {
    id: number;
    type: string;
    type_label: string;
    note: string | null;
    actor_name: string | null;
    created_at: string;
    items_count?: number;
    total_qty?: number;
    total_cost?: number | null;
    details?: TransactionDetail[];
};

type ProductOpt = {
    id: number;
    name: string;
    unit: string;
    stock: number;
    has_types: boolean;
    price: number;
    pack_price: number | null;
    pack_size: number;
    types: {
        id: number;
        name: string;
        stock: number;
        price: number;
        pack_price: number | null;
    }[];
};

const props = defineProps<{
    supplier: { data: Supplier };
    payments: { data: SupplierPayment[] };
    transactions: { data: TxLite[] };
    products: ProductOpt[];
    paymentTypes: { value: string; label: string }[];
    canPay: boolean;
    canEdit: boolean;
    canReturn: boolean;
}>();

const supplier = computed(() => props.supplier.data);

const dialogOpen = ref(false);
const returnOpen = ref(false);

const form = useForm({
    supplier_id: supplier.value.id,
    amount: 0,
    type: 'credit' as 'credit' | 'debit',
    method: 'cash' as 'cash' | 'card',
    cardholder_name: '',
    note: '',
});

const newBalance = computed<number | null>(() => {
    if (!form.amount) {
        return null;
    }

    const delta = form.type === 'credit' ? form.amount : -form.amount;

    return supplier.value.balance + delta;
});

function setQuickAmount(delta: number) {
    form.amount = Math.max(0, (form.amount || 0) + delta);
}

function submitPayment() {
    if (form.type !== 'credit') {
        form.method = 'cash';
        form.cardholder_name = '';
    } else if (form.method !== 'card') {
        form.cardholder_name = '';
    }

    form.post('/dealer/suppliers-balance/payments', {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            form.reset();
            form.supplier_id = supplier.value.id;
        },
    });
}

function destroy() {
    if (!confirm(t('pageDealer.suppliers.deactivateConfirm'))) {
        return;
    }

    router.delete(`/dealer/suppliers/${supplier.value.id}`);
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="supplier.name" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex items-start gap-2">
            <Button
                variant="ghost"
                size="icon"
                @click="router.get('/dealer/suppliers')"
            >
                <ArrowLeft class="h-4 w-4" />
            </Button>
            <div class="flex-1">
                <h1
                    class="flex items-center gap-2 text-xl font-bold tracking-tight sm:text-2xl"
                >
                    <Truck class="h-5 w-5 text-primary" />
                    {{ supplier.name }}
                    <Badge
                        :variant="supplier.is_active ? 'default' : 'outline'"
                    >
                        {{
                            supplier.is_active
                                ? t('pageDealer.suppliers.active')
                                : t('pageDealer.suppliers.inactive')
                        }}
                    </Badge>
                </h1>
                <p
                    v-if="supplier.contact_person"
                    class="text-sm text-muted-foreground"
                >
                    {{ supplier.contact_person }}
                </p>
            </div>
            <div class="flex gap-2">
                <Button
                    v-if="canEdit"
                    variant="outline"
                    size="sm"
                    @click="router.get(`/dealer/suppliers/${supplier.id}/edit`)"
                >
                    <Edit class="mr-1.5 h-4 w-4" />
                    {{ t('pageDealer.common.edit') }}
                </Button>
                <Button
                    v-if="canEdit && supplier.is_active"
                    variant="outline"
                    size="sm"
                    class="text-destructive"
                    @click="destroy"
                >
                    <Trash2 class="h-4 w-4" />
                </Button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        {{ t('pageDealer.suppliers.showCurrentBalance') }}
                    </p>
                    <p
                        class="text-xl font-bold sm:text-2xl"
                        :class="
                            supplier.balance < 0
                                ? 'text-rose-600'
                                : supplier.balance > 0
                                  ? 'text-emerald-600'
                                  : ''
                        "
                    >
                        {{ formatWithSymbol(supplier.balance) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        <span v-if="supplier.balance < 0">{{
                            t('pageDealer.suppliers.balanceDealerOwes')
                        }}</span>
                        <span v-else-if="supplier.balance > 0">{{
                            t('pageDealer.suppliers.balanceOverpaid')
                        }}</span>
                        <span v-else>{{
                            t('pageDealer.suppliers.balanceZero')
                        }}</span>
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        {{ t('pageDealer.suppliers.phone') }}
                    </p>
                    <p class="mt-1 flex items-center gap-1.5">
                        <Phone class="h-4 w-4 text-muted-foreground" />
                        {{ supplier.phone || '—' }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <p class="text-sm text-muted-foreground">
                        {{ t('pageDealer.suppliers.address') }}
                    </p>
                    <p class="mt-1 line-clamp-2">
                        {{ supplier.address || '—' }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <p
            v-if="supplier.note"
            class="rounded-lg border bg-muted/30 p-3 text-sm"
        >
            {{ supplier.note }}
        </p>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-lg font-semibold">
                {{ t('pageDealer.suppliers.history') }}
            </h2>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="canReturn"
                    variant="outline"
                    @click="returnOpen = true"
                >
                    <RotateCcw class="mr-2 h-4 w-4" />
                    Vozvrat
                </Button>
                <Button v-if="canPay" @click="dialogOpen = true">
                    <Wallet class="mr-2 h-4 w-4" />
                    {{ t('pageDealer.suppliers.addPayment') }}
                </Button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{
                        t('pageDealer.suppliers.paymentsTitle')
                    }}</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="payments.data.length > 0" class="divide-y">
                        <div
                            v-for="p in payments.data"
                            :key="p.id"
                            class="flex items-center justify-between gap-3 p-3"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="flex items-center gap-1.5 text-sm">
                                    <ArrowDownLeft
                                        v-if="p.type === 'credit'"
                                        class="h-3.5 w-3.5 text-emerald-600"
                                    />
                                    <ArrowUpRight
                                        v-else
                                        class="h-3.5 w-3.5 text-rose-600"
                                    />
                                    {{ p.type_label }}
                                    <Badge variant="outline" class="text-xs">{{
                                        p.method_label
                                    }}</Badge>
                                </p>
                                <p
                                    v-if="p.note"
                                    class="mt-0.5 truncate text-xs text-muted-foreground"
                                >
                                    {{ p.note }}
                                </p>
                                <p
                                    class="mt-0.5 text-[11px] text-muted-foreground"
                                >
                                    {{
                                        new Date(p.created_at).toLocaleString(
                                            'uz-UZ',
                                        )
                                    }}
                                </p>
                            </div>
                            <span
                                class="font-mono text-sm font-semibold"
                                :class="
                                    p.type === 'credit'
                                        ? 'text-emerald-600'
                                        : 'text-rose-600'
                                "
                            >
                                {{ p.type === 'credit' ? '+' : '-'
                                }}{{ formatMoney(p.amount) }}
                            </span>
                        </div>
                    </div>
                    <p
                        v-else
                        class="p-6 text-center text-sm text-muted-foreground"
                    >
                        {{ t('pageDealer.suppliers.noPayments') }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{
                        t('pageDealer.suppliers.stockTransactions')
                    }}</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="transactions.data.length > 0" class="divide-y">
                        <div
                            v-for="tx in transactions.data"
                            :key="tx.id"
                            class="p-3"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <p class="text-sm font-medium">
                                    #{{ tx.id }} — {{ tx.items_count }}
                                    {{
                                        t(
                                            'pageDealer.suppliers.itemsCountSuffix',
                                        )
                                    }}
                                </p>
                                <span
                                    v-if="
                                        tx.total_cost !== null &&
                                        tx.total_cost !== undefined
                                    "
                                    class="font-mono text-sm font-semibold text-rose-600"
                                >
                                    {{ formatMoney(tx.total_cost) }}
                                </span>
                            </div>
                            <p
                                v-if="tx.note"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ tx.note }}
                            </p>
                            <p class="mt-0.5 text-[11px] text-muted-foreground">
                                {{
                                    new Date(tx.created_at).toLocaleString(
                                        'uz-UZ',
                                    )
                                }}
                            </p>
                            <div
                                v-if="tx.details && tx.details.length > 0"
                                class="mt-2 space-y-1 rounded-md bg-muted/40 p-2 text-xs"
                            >
                                <div
                                    v-for="d in tx.details"
                                    :key="d.id"
                                    class="flex justify-between"
                                >
                                    <span class="truncate">
                                        {{ d.product_name
                                        }}<span v-if="d.product_type_name">
                                            — {{ d.product_type_name }}</span
                                        >
                                    </span>
                                    <span class="ml-2 shrink-0 font-mono">
                                        {{ d.qty }} ×
                                        {{
                                            d.unit_cost
                                                ? formatMoney(d.unit_cost)
                                                : '—'
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p
                        v-else
                        class="p-6 text-center text-sm text-muted-foreground"
                    >
                        {{ t('pageDealer.suppliers.noStockTransactions') }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <Dialog :open="dialogOpen" @update:open="(v) => (dialogOpen = v)">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Wallet class="h-5 w-5" />
                        {{ t('pageDealer.suppliers.addPaymentTitle') }}
                    </DialogTitle>
                </DialogHeader>

                <form @submit.prevent="submitPayment" class="space-y-5 pt-2">
                    <div>
                        <Label class="mb-2 block">{{
                            t('pageDealer.suppliers.operationType')
                        }}</Label>
                        <div
                            class="grid grid-cols-2 gap-2 rounded-lg border p-1"
                        >
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
                                <ArrowUpRight class="h-4 w-4" />
                                {{ t('pageDealer.suppliers.sendPayment') }}
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
                                <ArrowDownLeft class="h-4 w-4" />
                                {{ t('pageDealer.suppliers.addDebt') }}
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            <span v-if="form.type === 'credit'">{{
                                t('pageDealer.suppliers.creditHint')
                            }}</span>
                            <span v-else>{{
                                t('pageDealer.suppliers.debitHint')
                            }}</span>
                        </p>
                    </div>

                    <div>
                        <Label class="mb-2 flex items-center gap-1.5">
                            <Banknote
                                class="h-3.5 w-3.5 text-muted-foreground"
                            />
                            {{ t('pageDealer.suppliers.amount') }}
                            <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            type="number"
                            v-model.number="form.amount"
                            placeholder="0"
                            class="font-mono text-lg"
                        />
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
                                {{ t('pageDealer.common.clear') }}
                            </button>
                        </div>
                        <InputError :message="form.errors.amount" />

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
                                t('pageDealer.suppliers.newBalance')
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

                    <div v-if="form.type === 'credit'">
                        <Label class="mb-2 block">{{
                            t('pageDealer.suppliers.paymentMethod')
                        }}</Label>
                        <div
                            class="grid grid-cols-2 gap-2 rounded-lg border p-1"
                        >
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
                                {{ t('pageDealer.suppliers.cash') }}
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
                                {{ t('pageDealer.suppliers.card') }}
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="form.type === 'credit' && form.method === 'card'"
                    >
                        <Label class="mb-2 flex items-center gap-1.5">
                            <User class="h-3.5 w-3.5 text-muted-foreground" />
                            {{ t('pageDealer.suppliers.cardholder') }}
                            <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            v-model="form.cardholder_name"
                            :placeholder="
                                t('pageDealer.suppliers.cardholderPlaceholder')
                            "
                        />
                        <InputError :message="form.errors.cardholder_name" />
                    </div>

                    <div>
                        <Label for="note" class="mb-2 block">{{
                            t('pageDealer.common.note')
                        }}</Label>
                        <Input
                            id="note"
                            v-model="form.note"
                            :placeholder="t('pageDealer.common.noteOptional')"
                        />
                        <InputError :message="form.errors.note" />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            variant="outline"
                            type="button"
                            @click="dialogOpen = false"
                            >{{ t('pageDealer.suppliers.cancel') }}</Button
                        >
                        <Button
                            type="submit"
                            :disabled="form.processing || !form.amount"
                        >
                            {{
                                form.processing
                                    ? t('pageDealer.suppliers.saving')
                                    : t('pageDealer.suppliers.save')
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <SupplierReturnModal
            v-model:open="returnOpen"
            :supplier-id="supplier.id"
            :supplier-name="supplier.name"
            :supplier-balance="supplier.balance"
            :products="products"
        />
    </div>
</template>
