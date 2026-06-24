<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Search, Truck, Phone, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useCurrency } from '@/composables/useCurrency';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Paginated, Supplier } from '@/types';

const { t } = useI18n();
const { formatWithSymbol } = useCurrency();

type Filters = { search?: string };

const props = defineProps<{
    suppliers: Paginated<Supplier>;
    filters: Filters;
}>();

const search = ref(props.filters.search ?? '');

const hasFilters = computed(() => Boolean(search.value));

function applyFilters() {
    router.get(
        '/dealer/suppliers',
        { search: search.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function clearFilters() {
    search.value = '';
    applyFilters();
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.suppliers.indexHead')" />

    <div class="flex flex-col gap-4 p-4 sm:gap-6 sm:p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">
                    {{ t('pageDealer.suppliers.indexTitle') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ suppliers.meta.total }}
                    {{ t('pageDealer.suppliers.countSuffix') }}
                </p>
            </div>
            <Button @click="router.get('/dealer/suppliers/create')">
                <Plus class="mr-2 h-4 w-4" />
                {{ t('pageDealer.suppliers.newOne') }}
            </Button>
        </div>

        <div class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
            <div class="relative">
                <Search
                    class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    :placeholder="t('pageDealer.suppliers.searchPlaceholder')"
                    class="pl-10"
                    @keyup.enter="applyFilters"
                />
            </div>
            <Button v-if="hasFilters" variant="outline" @click="clearFilters">
                <X class="mr-1.5 h-4 w-4" />
                {{ t('pageDealer.suppliers.clear') }}
            </Button>
        </div>

        <div
            v-if="suppliers.data.length > 0"
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
        >
            <Card
                v-for="s in suppliers.data"
                :key="s.id"
                class="cursor-pointer transition-shadow hover:shadow-md"
                @click="router.get(`/dealer/suppliers/${s.id}`)"
            >
                <CardContent class="p-4">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start gap-3">
                            <div
                                class="rounded-lg bg-primary/10 p-2 text-primary"
                            >
                                <Truck class="h-5 w-5" />
                            </div>
                            <div class="min-w-0">
                                <p class="truncate font-semibold">
                                    {{ s.name }}
                                </p>
                                <p
                                    v-if="s.phone"
                                    class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground"
                                >
                                    <Phone class="h-3 w-3" />
                                    {{ s.phone }}
                                </p>
                                <p
                                    v-if="s.contact_person"
                                    class="mt-0.5 truncate text-xs text-muted-foreground"
                                >
                                    {{ s.contact_person }}
                                </p>
                            </div>
                        </div>
                        <Badge
                            :variant="s.is_active ? 'default' : 'outline'"
                            class="text-xs"
                        >
                            {{
                                s.is_active
                                    ? t('pageDealer.suppliers.active')
                                    : t('pageDealer.suppliers.inactive')
                            }}
                        </Badge>
                    </div>

                    <div
                        class="mt-3 flex items-center justify-between border-t pt-3 text-xs"
                    >
                        <span class="text-muted-foreground">{{
                            t('pageDealer.suppliers.balance')
                        }}</span>
                        <span
                            :class="
                                s.balance < 0
                                    ? 'font-semibold text-rose-600'
                                    : s.balance > 0
                                      ? 'font-semibold text-emerald-600'
                                      : 'text-muted-foreground'
                            "
                        >
                            {{ formatWithSymbol(s.balance) }}
                        </span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div
            v-else
            class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed py-16"
        >
            <Truck class="h-12 w-12 text-muted-foreground/40" />
            <div class="text-center">
                <p class="font-medium">
                    {{
                        hasFilters
                            ? t('pageDealer.suppliers.emptyFilteredTitle')
                            : t('pageDealer.suppliers.emptyTitle')
                    }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{
                        hasFilters
                            ? t('pageDealer.suppliers.emptyFilteredDesc')
                            : t('pageDealer.suppliers.emptyDesc')
                    }}
                </p>
            </div>
            <Button
                v-if="!hasFilters"
                @click="router.get('/dealer/suppliers/create')"
            >
                <Plus class="mr-2 h-4 w-4" />
                {{ t('pageDealer.suppliers.newOne') }}
            </Button>
            <Button v-else variant="outline" @click="clearFilters">
                <X class="mr-2 h-4 w-4" />
                {{ t('pageDealer.suppliers.clear') }}
            </Button>
        </div>
    </div>
</template>
