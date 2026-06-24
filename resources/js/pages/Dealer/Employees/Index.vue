<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AtSign, Briefcase, Pencil, Phone, Plus, Trash2, Truck, Warehouse } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';

const { t } = useI18n();
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { confirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

type Role = 'dealer' | 'warehouse' | 'deliveryman';

type Employee = {
    id: number;
    name: string;
    username: string;
    phone: string | null;
    role: Role;
    role_label: string;
    shops_count?: number;
    active_orders_count?: number;
    created_at?: string;
};

type RoleOption = { value: Role; label: string };

const props = defineProps<{
    employees: { data: Employee[] };
    roles: RoleOption[];
}>();

const filter = ref<Role | 'all'>('all');

const filtered = computed(() =>
    filter.value === 'all'
        ? props.employees.data
        : props.employees.data.filter((e) => e.role === filter.value),
);

const roleIcons: Record<Role, typeof Briefcase> = {
    dealer: Briefcase,
    warehouse: Warehouse,
    deliveryman: Truck,
};

const roleStyles: Record<Role, string> = {
    dealer: 'bg-purple-500/10 text-purple-600 dark:text-purple-300',
    warehouse: 'bg-amber-500/10 text-amber-600 dark:text-amber-300',
    deliveryman: 'bg-sky-500/10 text-sky-600 dark:text-sky-300',
};

async function del(e: Employee) {
    const ok = await confirm({
        title: t('pageDealer.employees.deleteTitle'),
        description: t('pageDealer.employees.deleteDesc', { name: e.name }),
        confirmText: t('pageDealer.employees.deleteConfirm'),
        variant: 'destructive',
    });

    if (!ok) {
return;
}

    router.delete(`/dealer/employees/${e.id}`);
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.employees.indexHead')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageDealer.employees.indexTitle') }}</h1>
                <p class="text-sm text-muted-foreground">{{ props.employees.data.length }} {{ t('pageDealer.employees.countSuffix') }}</p>
            </div>
            <Button class="w-full sm:w-auto" @click="router.get('/dealer/employees/create')">
                <Plus class="mr-2 h-4 w-4" />
                {{ t('pageDealer.employees.newOne') }}
            </Button>
        </div>

        <!-- Rol filter chip'lari -->
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                :class="filter === 'all' ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background hover:bg-muted'"
                @click="filter = 'all'"
            >
                {{ t('pageDealer.employees.filterAll') }} ({{ props.employees.data.length }})
            </button>
            <button
                v-for="r in roles"
                :key="r.value"
                type="button"
                class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                :class="filter === r.value ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background hover:bg-muted'"
                @click="filter = r.value as Role"
            >
                {{ r.label }} ({{ props.employees.data.filter((e) => e.role === r.value).length }})
            </button>
        </div>

        <div v-if="filtered.length > 0" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <Card v-for="e in filtered" :key="e.id" class="transition-shadow hover:shadow-md">
                <CardContent class="p-4">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start gap-3">
                            <div class="rounded-lg p-2" :class="roleStyles[e.role]">
                                <component :is="roleIcons[e.role]" class="h-5 w-5" />
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="truncate font-semibold">{{ e.name }}</p>
                                    <Badge variant="outline" class="text-[10px]">{{ e.role_label }}</Badge>
                                </div>
                                <p class="flex items-center gap-1 truncate text-xs text-muted-foreground">
                                    <AtSign class="h-3 w-3 shrink-0" />
                                    {{ e.username }}
                                </p>
                                <p v-if="e.phone" class="flex items-center gap-1 truncate text-xs text-muted-foreground">
                                    <Phone class="h-3 w-3 shrink-0" />
                                    {{ e.phone }}
                                </p>
                                <div v-if="e.role === 'deliveryman'" class="mt-1 flex flex-wrap gap-2 text-[11px] text-muted-foreground">
                                    <span>{{ e.shops_count ?? 0 }} {{ t('pageDealer.employees.shopsCount') }}</span>
                                    <span v-if="(e.active_orders_count ?? 0) > 0" class="text-amber-600">
                                        · {{ e.active_orders_count }} {{ t('pageDealer.employees.activeOrders') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8"
                                @click="router.get(`/dealer/employees/${e.id}/edit`)"
                            >
                                <Pencil class="h-3.5 w-3.5" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8 text-destructive"
                                @click="del(e)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div v-else class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed py-16">
            <Briefcase class="h-12 w-12 text-muted-foreground/40" />
            <div class="text-center">
                <p class="font-medium">{{ t('pageDealer.employees.emptyTitle') }}</p>
                <p class="text-sm text-muted-foreground">{{ t('pageDealer.employees.emptyDesc') }}</p>
            </div>
            <Button @click="router.get('/dealer/employees/create')">
                <Plus class="mr-2 h-4 w-4" />
                {{ t('pageDealer.employees.newOne') }}
            </Button>
        </div>
    </div>
</template>
