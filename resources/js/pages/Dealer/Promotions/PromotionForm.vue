<script setup lang="ts">
import { Percent, Tag, Calendar, ToggleLeft } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';

const { t } = useI18n();

export type Scope = 'all' | 'category' | 'product';

export type FormShape = {
    name: string;
    scope: Scope;
    target_id: number | null;
    discount_percent: number | null;
    starts_at: string;
    ends_at: string;
    is_active: boolean;
};

type Option = { id: number; name: string };

const props = defineProps<{
    modelValue: FormShape;
    errors: Partial<Record<keyof FormShape, string>>;
    products: Option[];
    categories: Option[];
}>();

const emit = defineEmits<{ 'update:modelValue': [v: FormShape] }>();

const form = computed<FormShape>({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

function setField<K extends keyof FormShape>(key: K, value: FormShape[K]) {
    form.value = { ...form.value, [key]: value };
}

function onScopeChange(v: Scope) {
    setField('scope', v);
    setField('target_id', null);
}

const targetOptions = computed<Option[]>(() => {
    if (form.value.scope === 'category') {
return props.categories;
}

    if (form.value.scope === 'product') {
return props.products;
}

    return [];
});
</script>

<template>
    <div class="space-y-6">
        <!-- Asosiy -->
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-1">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-primary/10 p-2 text-primary">
                        <Tag class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold">{{ t('pageDealer.promotionsForm.basicTitle') }}</h3>
                        <p class="mt-1 text-sm text-muted-foreground">{{ t('pageDealer.promotionsForm.basicDesc') }}</p>
                    </div>
                </div>
            </div>

            <Card class="lg:col-span-2">
                <CardContent class="space-y-5 p-6">
                    <div>
                        <Label class="mb-1.5">{{ t('pageDealer.promotionsForm.name') }} <span class="text-destructive">*</span></Label>
                        <Input
                            :model-value="form.name"
                            :placeholder="t('pageDealer.promotionsForm.namePlaceholder')"
                            @update:model-value="(v: string | number) => setField('name', String(v))"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div>
                        <Label class="mb-1.5">{{ t('pageDealer.promotionsForm.scope') }} <span class="text-destructive">*</span></Label>
                        <div class="grid grid-cols-3 gap-2 rounded-lg border p-1">
                            <button
                                v-for="s in [
                                    { val: 'all' as Scope, label: t('pageDealer.promotionsForm.scopeAll') },
                                    { val: 'category' as Scope, label: t('pageDealer.promotionsForm.scopeCategory') },
                                    { val: 'product' as Scope, label: t('pageDealer.promotionsForm.scopeProduct') },
                                ]"
                                :key="s.val"
                                type="button"
                                class="rounded-md px-3 py-2 text-xs font-medium transition-colors"
                                :class="form.scope === s.val ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                                @click="onScopeChange(s.val)"
                            >
                                {{ s.label }}
                            </button>
                        </div>
                        <InputError :message="errors.scope" />
                    </div>

                    <div v-if="form.scope !== 'all'">
                        <Label class="mb-1.5">
                            <template v-if="form.scope === 'category'">{{ t('pageDealer.promotionsForm.category') }}</template>
                            <template v-else>{{ t('pageDealer.promotionsForm.product') }}</template>
                            <span class="text-destructive">*</span>
                        </Label>
                        <SearchableSelect
                            :model-value="form.target_id"
                            :items="targetOptions"
                            value-key="id"
                            label-key="name"
                            :placeholder="t('pageDealer.promotionsForm.selectPlaceholder')"
                            :search-placeholder="form.scope === 'category' ? t('pageDealer.promotionsForm.categorySearch') : t('pageDealer.promotionsForm.productSearch')"
                            :empty-text="t('pageDealer.promotionsForm.notFound')"
                            @update:model-value="(v) => setField('target_id', v !== null ? Number(v) : null)"
                        />
                        <InputError :message="errors.target_id" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Chegirma -->
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-1">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-primary/10 p-2 text-primary">
                        <Percent class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold">{{ t('pageDealer.promotionsForm.discountTitle') }}</h3>
                        <p class="mt-1 text-sm text-muted-foreground">{{ t('pageDealer.promotionsForm.discountDesc') }}</p>
                    </div>
                </div>
            </div>

            <Card class="lg:col-span-2">
                <CardContent class="space-y-4 p-6">
                    <div>
                        <Label class="mb-1.5">{{ t('pageDealer.promotionsForm.discountPercent') }} <span class="text-destructive">*</span></Label>
                        <div class="relative">
                            <Input
                                type="number"
                                :model-value="form.discount_percent ?? ''"
                                min="1"
                                max="99"
                                placeholder="10"
                                @update:model-value="(v: string | number) => setField('discount_percent', v === '' ? null : Number(v))"
                                required
                            />
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">%</span>
                        </div>
                        <InputError :message="errors.discount_percent" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Muddat -->
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-1">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-primary/10 p-2 text-primary">
                        <Calendar class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold">{{ t('pageDealer.promotionsForm.periodTitle') }}</h3>
                        <p class="mt-1 text-sm text-muted-foreground">{{ t('pageDealer.promotionsForm.periodDesc') }}</p>
                    </div>
                </div>
            </div>

            <Card class="lg:col-span-2">
                <CardContent class="grid gap-4 p-6 sm:grid-cols-2">
                    <div>
                        <Label class="mb-1.5">{{ t('pageDealer.promotionsForm.startsAt') }}</Label>
                        <Input
                            type="datetime-local"
                            :model-value="form.starts_at"
                            @update:model-value="(v: string | number) => setField('starts_at', String(v))"
                        />
                        <InputError :message="errors.starts_at" />
                    </div>
                    <div>
                        <Label class="mb-1.5">{{ t('pageDealer.promotionsForm.endsAt') }}</Label>
                        <Input
                            type="datetime-local"
                            :model-value="form.ends_at"
                            @update:model-value="(v: string | number) => setField('ends_at', String(v))"
                        />
                        <InputError :message="errors.ends_at" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Faol -->
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-1">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-primary/10 p-2 text-primary">
                        <ToggleLeft class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold">{{ t('pageDealer.promotionsForm.statusTitle') }}</h3>
                    </div>
                </div>
            </div>

            <Card class="lg:col-span-2">
                <CardContent class="p-6">
                    <label class="flex cursor-pointer items-center gap-3">
                        <input
                            type="checkbox"
                            :checked="form.is_active"
                            class="h-4 w-4 rounded border-input"
                            @change="(e) => setField('is_active', (e.target as HTMLInputElement).checked)"
                        />
                        <span class="text-sm">{{ t('pageDealer.promotionsForm.isActive') }}</span>
                    </label>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
