<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Loader2, Search, UserPlus, Users } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { PaginationBar } from '@/components/ui/pagination-bar';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoneySum } from '@/lib/format';
import type { Paginated } from '@/types';

type Customer = {
    id: number;
    name: string;
    phone: string | null;
    type: string | null;
    type_label: string | null;
    balance: number;
    orders_count: number;
    is_active: boolean;
};

const props = defineProps<{
    customers: Paginated<Customer>;
    filters: { search: string | null; only_debt: boolean };
}>();

const search = ref(props.filters.search ?? '');
const onlyDebt = ref(props.filters.only_debt);

let timer: ReturnType<typeof setTimeout> | null = null;

watch([search, onlyDebt], () => {
    if (timer) {
        clearTimeout(timer);
    }
    timer = setTimeout(() => {
        router.get(
            '/dealer/pos/customers',
            {
                search: search.value || undefined,
                only_debt: onlyDebt.value ? 1 : undefined,
            },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }, 300);
});

onBeforeUnmount(() => {
    if (timer) {
        clearTimeout(timer);
    }
});

const dialogOpen = ref(false);
const form = useForm({ name: '', phone: '', address: '' });

function submit() {
    form.post('/dealer/pos/customers', {
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
    <Head title="POS mijozlar" />

    <div>
        <div class="space-y-4 p-4 sm:p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-semibold">
                        <Users class="h-5 w-5" /> POS mijozlar
                    </h1>
                    <p class="text-sm text-muted-foreground">Chakana mijozlar va qarzdor xaridorlar</p>
                </div>
                <Button @click="dialogOpen = true">
                    <UserPlus class="mr-1 h-4 w-4" /> Mijoz qo'shish
                </Button>
            </div>

            <Card>
                <CardContent class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                    <div class="relative flex-1">
                        <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" placeholder="Ism yoki telefon…" class="pl-9" />
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="onlyDebt" type="checkbox" class="h-4 w-4" />
                        Faqat qarzdorlar
                    </label>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/30 text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <tr>
                                    <th class="px-4 py-3">Ism</th>
                                    <th class="px-4 py-3">Telefon</th>
                                    <th class="px-4 py-3">Turi</th>
                                    <th class="px-4 py-3 text-right">Saldo</th>
                                    <th class="px-4 py-3 text-right">Sotuvlar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="row in customers.data" :key="row.id" class="hover:bg-muted/40">
                                    <td class="px-4 py-3">
                                        <Link :href="`/dealer/pos/customers/${row.id}`" class="font-medium text-primary hover:underline">{{ row.name }}</Link>
                                    </td>
                                    <td class="px-4 py-3">{{ row.phone ?? '—' }}</td>
                                    <td class="px-4 py-3 text-xs">{{ row.type_label ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <span :class="row.balance < 0 ? 'text-rose-500 font-medium' : row.balance > 0 ? 'text-emerald-600' : ''">
                                            {{ formatMoneySum(row.balance) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ row.orders_count }}</td>
                                </tr>
                                <tr v-if="customers.data.length === 0">
                                    <td colspan="5" class="px-4 py-12 text-center text-muted-foreground">Mijoz topilmadi</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <PaginationBar :links="customers.links" :meta="customers.meta" />
        </div>

        <!-- Add customer dialog -->
        <div v-if="dialogOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="dialogOpen = false">
            <Card class="w-full max-w-md">
                <CardContent class="space-y-3 p-6">
                    <h3 class="text-lg font-semibold">Yangi mijoz</h3>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Ism *</label>
                        <Input v-model="form.name" />
                        <div v-if="form.errors.name" class="mt-1 text-sm text-destructive">{{ form.errors.name }}</div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Telefon</label>
                        <Input v-model="form.phone" placeholder="+998" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Manzil</label>
                        <Input v-model="form.address" />
                    </div>
                    <div class="flex gap-2 pt-2">
                        <Button variant="outline" class="flex-1" @click="dialogOpen = false">Bekor</Button>
                        <Button class="flex-1" :disabled="form.processing" @click="submit">
                            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                            Qo'shish
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
