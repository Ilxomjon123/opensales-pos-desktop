<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, Banknote, Clock, CreditCard, Receipt, TrendingUp, Wallet } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoneySum } from '@/lib/format';

const props = defineProps<{
    range: { from: string; to: string };
    totals: { count: number; total: number; cash: number; card: number; debt: number };
    byDay: { day: string; count: number; total: number; cash: number; card: number }[];
    byCashier: { id: number; name: string; count: number; total: number }[];
    byPaymentStatus: { status: string; label: string; count: number; total: number }[];
    topProducts: { product_id: number; name: string; qty: number; revenue: number }[];
    openShifts: number;
}>();

const from = ref(props.range.from);
const to = ref(props.range.to);

function applyRange() {
    router.get('/dealer/pos/reports', { from: from.value, to: to.value }, { preserveScroll: true });
}

// Spread argument limit'dan saqlash uchun reduce (10k+ kun bucket xavfsiz).
const maxDayTotal = props.byDay.reduce((m, d) => Math.max(m, d.total), 1);

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="POS hisobotlar" />

    <div>
        <div class="space-y-4 p-4 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-semibold">
                        <BarChart3 class="h-5 w-5" /> POS hisobotlar
                    </h1>
                    <p class="text-sm text-muted-foreground">{{ range.from }} — {{ range.to }} oraliqdagi statistika</p>
                </div>
                <div class="flex items-center gap-2">
                    <Input v-model="from" type="date" class="text-sm" />
                    <span class="text-muted-foreground">—</span>
                    <Input v-model="to" type="date" class="text-sm" />
                    <Button variant="outline" @click="applyRange">Yangilash</Button>
                </div>
            </div>

            <!-- KPI -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent class="flex items-center gap-3 p-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Receipt class="h-5 w-5" />
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">Sotuvlar</div>
                            <div class="text-xl font-semibold">{{ totals.count }}</div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-3 p-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <TrendingUp class="h-5 w-5" />
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">Aylanma</div>
                            <div class="text-xl font-semibold">{{ formatMoneySum(totals.total) }}</div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-3 p-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <Banknote class="h-5 w-5" />
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">Naqd / Karta</div>
                            <div class="text-sm font-semibold">
                                {{ formatMoneySum(totals.cash) }}
                                <span class="text-xs text-muted-foreground">/ {{ formatMoneySum(totals.card) }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="flex items-center gap-3 p-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400">
                            <Wallet class="h-5 w-5" />
                        </div>
                        <div>
                            <div class="text-xs text-muted-foreground">Qarzga</div>
                            <div class="text-xl font-semibold">{{ formatMoneySum(totals.debt) }}</div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="openShifts > 0" class="border-amber-500/40 bg-amber-500/5">
                <CardContent class="flex items-center gap-3 p-4 text-sm">
                    <Clock class="h-5 w-5 text-amber-600" />
                    <div>Hozir <strong>{{ openShifts }}</strong> ta ochiq smena bor.</div>
                </CardContent>
            </Card>

            <!-- Daily chart (simple bars) -->
            <Card>
                <CardContent class="space-y-3 p-6">
                    <h3 class="text-base font-semibold">Kunlik aylanma</h3>
                    <div v-if="byDay.length === 0" class="py-8 text-center text-muted-foreground">Maʼlumot yo'q</div>
                    <div v-else class="space-y-2">
                        <div v-for="d in byDay" :key="d.day" class="flex items-center gap-3 text-sm">
                            <div class="w-20 shrink-0 text-xs text-muted-foreground">{{ d.day }}</div>
                            <div class="flex-1 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-2 bg-primary"
                                    :style="{ width: `${Math.max(2, (d.total / maxDayTotal) * 100)}%` }"
                                />
                            </div>
                            <div class="w-32 shrink-0 text-right font-medium">{{ formatMoneySum(d.total) }}</div>
                            <div class="w-16 shrink-0 text-right text-xs text-muted-foreground">{{ d.count }} ta</div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-3 lg:grid-cols-2">
                <Card>
                    <CardContent class="space-y-3 p-6">
                        <h3 class="text-base font-semibold">Kassirlar bo'yicha</h3>
                        <div v-if="byCashier.length === 0" class="py-6 text-center text-muted-foreground">Maʼlumot yo'q</div>
                        <ul v-else class="divide-y text-sm">
                            <li v-for="c in byCashier" :key="c.id" class="flex items-center justify-between py-2">
                                <div>
                                    <div class="font-medium">{{ c.name }}</div>
                                    <div class="text-xs text-muted-foreground">{{ c.count }} ta sotuv</div>
                                </div>
                                <div class="font-semibold">{{ formatMoneySum(c.total) }}</div>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="space-y-3 p-6">
                        <h3 class="text-base font-semibold">To'lov holati</h3>
                        <div v-if="byPaymentStatus.length === 0" class="py-6 text-center text-muted-foreground">Maʼlumot yo'q</div>
                        <ul v-else class="space-y-2 text-sm">
                            <li v-for="row in byPaymentStatus" :key="row.status" class="flex items-center justify-between rounded-md border p-3">
                                <div>
                                    <div class="font-medium">{{ row.label }}</div>
                                    <div class="text-xs text-muted-foreground">{{ row.count }} ta</div>
                                </div>
                                <div class="font-semibold">{{ formatMoneySum(row.total) }}</div>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardContent class="space-y-3 p-6">
                    <h3 class="text-base font-semibold">Top mahsulotlar</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b text-left text-xs uppercase text-muted-foreground">
                                <tr>
                                    <th class="py-2">#</th>
                                    <th class="py-2">Mahsulot</th>
                                    <th class="py-2 text-right">Miqdor</th>
                                    <th class="py-2 text-right">Aylanma</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="(p, idx) in topProducts" :key="p.product_id">
                                    <td class="py-2 text-muted-foreground">{{ idx + 1 }}</td>
                                    <td class="py-2">{{ p.name }}</td>
                                    <td class="py-2 text-right">{{ Number(p.qty).toFixed(p.qty % 1 === 0 ? 0 : 2) }}</td>
                                    <td class="py-2 text-right font-medium">{{ formatMoneySum(p.revenue) }}</td>
                                </tr>
                                <tr v-if="topProducts.length === 0">
                                    <td colspan="4" class="py-6 text-center text-muted-foreground">Maʼlumot yo'q</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
