<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Banknote, CreditCard, Loader2, Receipt, UserCircle2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDateTime } from '@/lib/date';
import { formatMoneySum } from '@/lib/format';

type SaleRow = {
    id: number;
    receipt_number: string | null;
    total: number;
    paid_amount: number;
    debt_amount: number;
    payment_status_label: string | null;
    created_at: string | null;
};

type Customer = {
    id: number;
    name: string;
    phone: string | null;
    address: string | null;
    type: string | null;
    type_label: string | null;
    balance: number;
    is_active: boolean;
};

const props = defineProps<{
    customer: Customer;
    sales: { data: SaleRow[] };
}>();

const dialogOpen = ref(false);
const form = useForm({
    amount: 0,
    method: 'cash' as 'cash' | 'card',
    cardholder_name: '',
    note: '',
});

function submit() {
    form.post(`/dealer/pos/customers/${props.customer.id}/payments`, {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            form.reset();
        },
    });
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="customer.name" />

    <div>
        <div class="space-y-4 p-4 sm:p-6">
            <div class="flex items-center gap-2">
                <Link href="/dealer/pos/customers" class="rounded-md p-2 hover:bg-muted">
                    <ArrowLeft class="h-4 w-4" />
                </Link>
                <h1 class="flex-1 text-2xl font-semibold">{{ customer.name }}</h1>
                <Button v-if="customer.balance < 0" @click="dialogOpen = true">
                    <Banknote class="mr-1 h-4 w-4" /> Qarz to'lovi
                </Button>
            </div>

            <div class="grid gap-3 lg:grid-cols-3">
                <Card class="lg:col-span-1">
                    <CardContent class="space-y-3 p-6">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <UserCircle2 class="h-6 w-6" />
                            </div>
                            <div>
                                <div class="font-semibold">{{ customer.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ customer.type_label }}</div>
                            </div>
                        </div>
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between"><span class="text-muted-foreground">Telefon</span><span>{{ customer.phone ?? '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-muted-foreground">Manzil</span><span>{{ customer.address ?? '—' }}</span></div>
                            <div class="flex justify-between border-t pt-2 text-base">
                                <span>Saldo</span>
                                <strong :class="customer.balance < 0 ? 'text-rose-500' : customer.balance > 0 ? 'text-emerald-500' : ''">
                                    {{ formatMoneySum(customer.balance) }}
                                </strong>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="lg:col-span-2">
                    <CardContent class="p-0">
                        <div class="border-b p-4">
                            <h3 class="text-base font-semibold">Sotuvlar tarixi</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="border-b bg-muted/30 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                    <tr>
                                        <th class="px-4 py-3">Chek</th>
                                        <th class="px-4 py-3">Vaqt</th>
                                        <th class="px-4 py-3 text-right">Summa</th>
                                        <th class="px-4 py-3 text-right">To'langan</th>
                                        <th class="px-4 py-3 text-right">Qarz</th>
                                        <th class="px-4 py-3">Holat</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="sale in sales.data" :key="sale.id" class="hover:bg-muted/40">
                                        <td class="px-4 py-3">
                                            <Link :href="`/dealer/pos/sales/${sale.id}`" class="inline-flex items-center gap-1 font-medium text-primary hover:underline">
                                                <Receipt class="h-3 w-3" />{{ sale.receipt_number }}
                                            </Link>
                                        </td>
                                        <td class="px-4 py-3 text-xs">{{ sale.created_at ? formatDateTime(sale.created_at) : '—' }}</td>
                                        <td class="px-4 py-3 text-right">{{ formatMoneySum(sale.total) }}</td>
                                        <td class="px-4 py-3 text-right text-emerald-600 dark:text-emerald-400">{{ formatMoneySum(sale.paid_amount) }}</td>
                                        <td class="px-4 py-3 text-right" :class="sale.debt_amount > 0 ? 'text-rose-500' : ''">
                                            {{ sale.debt_amount > 0 ? formatMoneySum(sale.debt_amount) : '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-xs">{{ sale.payment_status_label ?? '—' }}</td>
                                    </tr>
                                    <tr v-if="sales.data.length === 0">
                                        <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">Sotuv yo'q</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Payment dialog -->
        <div v-if="dialogOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="dialogOpen = false">
            <Card class="w-full max-w-md">
                <CardContent class="space-y-3 p-6">
                    <h3 class="text-lg font-semibold">Qarz to'lovi</h3>
                    <div class="rounded-md bg-rose-500/10 p-3 text-sm">
                        Joriy qarz: <strong class="text-rose-600">{{ formatMoneySum(Math.abs(customer.balance)) }}</strong>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Summa *</label>
                        <Input v-model.number="form.amount" type="number" min="1" />
                        <div v-if="form.errors.amount" class="mt-1 text-sm text-destructive">{{ form.errors.amount }}</div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">To'lov turi *</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                class="flex items-center justify-center gap-2 rounded-md border px-3 py-2 text-sm transition"
                                :class="form.method === 'cash' ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted'"
                                @click="form.method = 'cash'"
                            >
                                <Banknote class="h-4 w-4" /> Naqd
                            </button>
                            <button
                                type="button"
                                class="flex items-center justify-center gap-2 rounded-md border px-3 py-2 text-sm transition"
                                :class="form.method === 'card' ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted'"
                                @click="form.method = 'card'"
                            >
                                <CreditCard class="h-4 w-4" /> Karta
                            </button>
                        </div>
                    </div>
                    <div v-if="form.method === 'card'">
                        <label class="mb-1 block text-sm font-medium">Karta egasi (F.I.O.)</label>
                        <Input v-model="form.cardholder_name" placeholder="Ism Familiya" />
                        <div v-if="form.errors.cardholder_name" class="mt-1 text-sm text-destructive">{{ form.errors.cardholder_name }}</div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Izoh</label>
                        <Textarea v-model="form.note" rows="2" />
                    </div>
                    <div class="flex gap-2 pt-2">
                        <Button variant="outline" class="flex-1" @click="dialogOpen = false">Bekor</Button>
                        <Button class="flex-1" :disabled="form.processing" @click="submit">
                            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                            Saqlash
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
