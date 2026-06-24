<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Calculator, FileUp } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Spinner } from '@/components/ui/spinner';
import { confirm } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

type Category = { id: number; name: string };

defineProps<{ categories: Category[] }>();

const { t } = useI18n();

const page = usePage();
const flashStatus = computed(() => (page.props as any).flash?.status ?? null);

type PreviewRow = { id: number; name: string; old_price: number; new_price: number };

const adjustForm = useForm({
    scope: 'all' as 'all' | 'category',
    category_id: null as number | null,
    mode: 'percent' as 'percent' | 'amount',
    direction: 'up' as 'up' | 'down',
    value: 10,
    dry_run: false,
});

const preview = ref<PreviewRow[] | null>(null);
const previewLoading = ref(false);

async function loadPreview() {
    previewLoading.value = true;

    try {
        const res = await fetch('/dealer/products/bulk/adjust', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ ...adjustForm.data(), dry_run: true }),
        });
        const data = await res.json();
        preview.value = data.preview ?? [];
    } catch {
        preview.value = null;
    }

    previewLoading.value = false;
}

async function submitAdjust() {
    const rows = preview.value?.length ?? 0;
    const ok = await confirm({
        title: t('pageDealer.products.bulkPrice.confirmTitle'),
        description: rows > 0
            ? t('pageDealer.products.bulkPrice.confirmDescCount', { n: rows })
            : t('pageDealer.products.bulkPrice.confirmDescAll'),
        confirmText: t('pageDealer.products.bulkPrice.confirmUpdate'),
    });

    if (!ok) {
return;
}

    adjustForm.dry_run = false;
    adjustForm.post('/dealer/products/bulk/adjust', {
        preserveScroll: true,
        onSuccess: () => {
 preview.value = null; 
},
    });
}

const importForm = useForm<{ file: File | null }>({ file: null });

function onFile(e: Event) {
    const f = (e.target as HTMLInputElement).files?.[0] ?? null;
    importForm.file = f;
}

function submitImport() {
    if (!importForm.file) {
return;
}

    importForm.post('/dealer/products/bulk/import', {
        forceFormData: true,
        preserveScroll: true,
    });
}

function formatMoney(n: number): string {
    return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.products.bulkPrice.headTitle')" />

    <div class="mx-auto flex max-w-5xl flex-col gap-4 p-4 sm:gap-6 md:p-8">
        <div class="flex items-center gap-3">
            <Button variant="ghost" size="icon" class="shrink-0" @click="router.get('/dealer/products')">
                <ArrowLeft class="h-5 w-5" />
            </Button>
            <div class="min-w-0 flex-1">
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('pageDealer.products.bulkPrice.headTitle') }}</h1>
                <p class="text-sm text-muted-foreground">{{ t('pageDealer.products.bulkPrice.subtitle') }}</p>
            </div>
        </div>

        <div
            v-if="flashStatus"
            class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
        >
            ✅ {{ flashStatus }}
        </div>

        <!-- Adjust -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <Calculator class="h-4 w-4 text-primary" />
                    {{ t('pageDealer.products.bulkPrice.adjustCardTitle') }}
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label class="mb-1.5">{{ t('pageDealer.products.bulkPrice.scopeLabel') }}</Label>
                        <div class="grid grid-cols-2 gap-2 rounded-lg border p-1">
                            <button
                                v-for="s in [{ v: 'all', l: t('pageDealer.products.bulkPrice.scopeAll') }, { v: 'category', l: t('pageDealer.products.bulkPrice.scopeCategory') }]"
                                :key="s.v"
                                type="button"
                                class="rounded-md py-2 text-xs font-medium transition-colors"
                                :class="adjustForm.scope === s.v ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                                @click="adjustForm.scope = s.v as 'all'|'category'"
                            >
                                {{ s.l }}
                            </button>
                        </div>
                    </div>

                    <div v-if="adjustForm.scope === 'category'">
                        <Label class="mb-1.5">{{ t('pageDealer.products.bulkPrice.categoryLabel') }} <span class="text-destructive">*</span></Label>
                        <SearchableSelect
                            v-model="adjustForm.category_id"
                            :items="categories"
                            value-key="id"
                            label-key="name"
                            :placeholder="t('pageDealer.products.bulkPrice.categoryPlaceholder')"
                            :search-placeholder="t('pageDealer.products.bulkPrice.categorySearchPlaceholder')"
                            :empty-text="t('pageDealer.products.bulkPrice.categoryEmptyText')"
                        />
                        <InputError :message="adjustForm.errors.category_id" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <Label class="mb-1.5">{{ t('pageDealer.products.bulkPrice.directionLabel') }}</Label>
                        <div class="grid grid-cols-2 gap-2 rounded-lg border p-1">
                            <button
                                type="button"
                                class="rounded-md py-2 text-xs font-medium transition-colors"
                                :class="adjustForm.direction === 'up' ? 'bg-emerald-600 text-white' : 'text-muted-foreground hover:bg-muted'"
                                @click="adjustForm.direction = 'up'"
                            >
                                {{ t('pageDealer.products.bulkPrice.directionUp') }}
                            </button>
                            <button
                                type="button"
                                class="rounded-md py-2 text-xs font-medium transition-colors"
                                :class="adjustForm.direction === 'down' ? 'bg-rose-600 text-white' : 'text-muted-foreground hover:bg-muted'"
                                @click="adjustForm.direction = 'down'"
                            >
                                {{ t('pageDealer.products.bulkPrice.directionDown') }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <Label class="mb-1.5">{{ t('pageDealer.products.bulkPrice.modeLabel') }}</Label>
                        <div class="grid grid-cols-2 gap-2 rounded-lg border p-1">
                            <button
                                v-for="m in [{ v: 'percent', l: '%' }, { v: 'amount', l: t('pageDealer.products.bulkPrice.modeSum') }]"
                                :key="m.v"
                                type="button"
                                class="rounded-md py-2 text-xs font-medium transition-colors"
                                :class="adjustForm.mode === m.v ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                                @click="adjustForm.mode = m.v as 'percent'|'amount'"
                            >
                                {{ m.l }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <Label class="mb-1.5">{{ t('pageDealer.products.bulkPrice.valueLabel') }}</Label>
                        <Input
                            type="number"
                            v-model.number="adjustForm.value"
                            min="1"
                            :placeholder="adjustForm.mode === 'percent' ? '10' : '1000'"
                        />
                        <InputError :message="adjustForm.errors.value" />
                    </div>
                </div>

                <div class="flex flex-col gap-2 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <Button type="button" variant="outline" class="w-full sm:w-auto" :disabled="previewLoading" @click="loadPreview">
                        <Spinner v-if="previewLoading" class="mr-2" />
                        {{ t('pageDealer.products.bulkPrice.previewButton') }}
                    </Button>
                    <Button type="button" class="w-full sm:w-auto" :disabled="adjustForm.processing" @click="submitAdjust">
                        <Spinner v-if="adjustForm.processing" class="mr-2" />
                        {{ t('pageDealer.products.bulkPrice.applyButton') }}
                    </Button>
                </div>

                <div v-if="preview && preview.length" class="overflow-x-auto rounded-lg border">
                    <p class="border-b bg-muted/40 px-3 py-2 text-xs font-medium">
                        {{ t('pageDealer.products.bulkPrice.previewHeader') }}
                    </p>
                    <table class="w-full min-w-[420px] text-sm">
                        <tbody class="divide-y">
                            <tr v-for="p in preview" :key="p.id">
                                <td class="px-3 py-2 truncate">{{ p.name }}</td>
                                <td class="px-3 py-2 text-right font-mono text-muted-foreground line-through">
                                    {{ formatMoney(p.old_price) }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono font-bold text-emerald-600">
                                    {{ formatMoney(p.new_price) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Import -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <FileUp class="h-4 w-4 text-primary" />
                    {{ t('pageDealer.products.bulkPrice.importCardTitle') }}
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p class="text-sm text-muted-foreground">
                    {{ t('pageDealer.products.bulkPrice.fileHeaderLabel') }} <code class="rounded bg-muted px-1 py-0.5 font-mono text-xs">id,price</code>
                </p>

                <form @submit.prevent="submitImport">
                    <Input type="file" accept=".csv,text/csv" @change="onFile" />
                    <InputError :message="importForm.errors.file" />
                    <div class="mt-4 flex justify-end">
                        <Button type="submit" :disabled="!importForm.file || importForm.processing">
                            <Spinner v-if="importForm.processing" class="mr-2" />
                            {{ t('pageDealer.products.bulkPrice.importButton') }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
