<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Wallet, HandCoins } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCurrency } from '@/composables/useCurrency';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney } from '@/lib/format';

type Balance = {
    partner_id: number;
    partner_name: string | null;
    partner_phone: string | null;
    balance: number;
};

const { t } = useI18n();
const { symbol } = useCurrency();

defineProps<{
    balances: Balance[];
    totals: { receivable: number; payable: number };
    paymentMethods: { value: string; label: string }[];
}>();

const payingPartner = ref<Balance | null>(null);

const form = useForm({
    partner_dealer_id: null as number | null,
    amount: null as number | null,
    method: 'cash',
    cardholder_name: '',
    note: '',
});

function openPayment(b: Balance) {
    payingPartner.value = b;
    form.partner_dealer_id = b.partner_id;
    form.amount = b.balance > 0 ? b.balance : null;
    form.reset('cardholder_name', 'note');
    form.method = 'cash';
}

function submitPayment() {
    form.post('/dealer/marketplace/finance/payment', {
        preserveScroll: true,
        onSuccess: () => {
            payingPartner.value = null;
            form.reset();
        },
    });
}

const isCard = computed(() => form.method === 'card');

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.marketplaceFinance.title')" />

    <div class="mx-auto w-full max-w-3xl p-3 md:p-6">
        <div class="mb-4 flex items-center gap-2.5">
            <div class="rounded-md bg-primary/10 p-1.5 text-primary">
                <Wallet class="h-5 w-5" />
            </div>
            <div>
                <h1 class="text-lg font-bold tracking-tight sm:text-xl">
                    {{ t('pageDealer.marketplaceFinance.title') }}
                </h1>
                <p class="text-xs text-muted-foreground">
                    {{ t('pageDealer.marketplaceFinance.subtitle') }}
                </p>
            </div>
        </div>

        <!-- Qarzdorlik kanali tablari -->
        <div class="mb-4 flex gap-1 border-b">
            <Link
                href="/dealer/shops-balance"
                class="border-b-2 border-transparent px-3 py-2 text-sm font-medium text-muted-foreground hover:text-foreground"
            >
                {{ t('pageDealer.marketplaceFinance.botCustomers') }}
            </Link>
            <span
                class="border-b-2 border-primary px-3 py-2 text-sm font-medium text-primary"
                >{{ t('pageDealer.marketplaceFinance.marketplaceTab') }}</span
            >
        </div>

        <div class="mb-4 grid grid-cols-2 gap-2.5">
            <Card>
                <CardContent class="p-3.5">
                    <p class="text-[11px] text-muted-foreground">
                        {{ t('pageDealer.marketplaceFinance.receivable') }}
                    </p>
                    <p class="mt-1 text-lg font-bold text-emerald-600">
                        {{ formatMoney(totals.receivable) }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="p-3.5">
                    <p class="text-[11px] text-muted-foreground">
                        {{ t('pageDealer.marketplaceFinance.payable') }}
                    </p>
                    <p class="mt-1 text-lg font-bold text-red-600">
                        {{ formatMoney(totals.payable) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div
            v-if="balances.length === 0"
            class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            {{ t('pageDealer.marketplaceFinance.noBalance') }}
        </div>

        <div v-else class="space-y-2">
            <Card v-for="b in balances" :key="b.partner_id">
                <CardContent class="flex items-center justify-between p-3.5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            {{ b.partner_name }}
                        </p>
                        <p
                            v-if="b.partner_phone"
                            class="text-[11px] text-muted-foreground"
                        >
                            {{ b.partner_phone }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-right">
                            <p
                                class="text-sm font-bold"
                                :class="
                                    b.balance > 0
                                        ? 'text-emerald-600'
                                        : 'text-red-600'
                                "
                            >
                                {{ formatMoney(Math.abs(b.balance)) }}
                            </p>
                            <p class="text-[10px] text-muted-foreground">
                                {{
                                    b.balance > 0
                                        ? t(
                                              'pageDealer.marketplaceFinance.owesMe',
                                          )
                                        : t(
                                              'pageDealer.marketplaceFinance.iOwe',
                                          )
                                }}
                            </p>
                        </div>
                        <Button
                            v-if="b.balance > 0"
                            size="sm"
                            variant="outline"
                            class="h-7 text-xs"
                            @click="openPayment(b)"
                        >
                            <HandCoins class="mr-1 h-3 w-3" />
                            {{ t('pageDealer.marketplaceFinance.pay') }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- To'lov modali -->
        <div
            v-if="payingPartner"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="payingPartner = null"
        >
            <Card class="w-full max-w-sm">
                <div class="border-b px-4 py-2.5">
                    <h3 class="text-sm font-semibold">
                        {{
                            t('pageDealer.marketplaceFinance.paymentTitle', {
                                name: payingPartner.partner_name,
                            })
                        }}
                    </h3>
                </div>
                <CardContent class="space-y-3 p-4">
                    <div>
                        <Label class="mb-1 text-xs">{{
                            t('pageDealer.marketplaceFinance.amountLabel', {
                                currency: symbol,
                            })
                        }}</Label>
                        <Input
                            v-model.number="form.amount"
                            type="number"
                            min="1"
                        />
                        <p
                            v-if="form.errors.amount"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.amount }}
                        </p>
                    </div>
                    <div>
                        <Label class="mb-1 text-xs">{{
                            t('pageDealer.marketplaceFinance.methodLabel')
                        }}</Label>
                        <div class="flex gap-2">
                            <label
                                v-for="m in paymentMethods"
                                :key="m.value"
                                class="flex flex-1 cursor-pointer items-center justify-center rounded-md border px-3 py-1.5 text-xs"
                                :class="
                                    form.method === m.value
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : ''
                                "
                            >
                                <input
                                    v-model="form.method"
                                    type="radio"
                                    :value="m.value"
                                    class="sr-only"
                                />
                                {{ m.label }}
                            </label>
                        </div>
                    </div>
                    <div v-if="isCard">
                        <Label class="mb-1 text-xs">{{
                            t('pageDealer.marketplaceFinance.cardholderLabel')
                        }}</Label>
                        <Input v-model="form.cardholder_name" />
                        <p
                            v-if="form.errors.cardholder_name"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.cardholder_name }}
                        </p>
                    </div>
                    <div>
                        <Label class="mb-1 text-xs">{{
                            t('pageDealer.marketplaceFinance.noteLabel')
                        }}</Label>
                        <Input v-model="form.note" />
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="payingPartner = null"
                            >{{
                                t('pageDealer.marketplaceFinance.cancel')
                            }}</Button
                        >
                        <Button
                            size="sm"
                            :disabled="form.processing"
                            @click="submitPayment"
                            >{{
                                t('pageDealer.marketplaceFinance.save')
                            }}</Button
                        >
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
