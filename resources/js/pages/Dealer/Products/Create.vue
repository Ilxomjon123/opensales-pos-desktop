<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ImagePlus,
    Package,
    DollarSign,
    Palette,
    X,
    Star,
    Eye,
} from 'lucide-vue-next';
import { computed, ref, toRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import draggable from 'vuedraggable';
import TypesEditor from '@/components/dealer/products/TypesEditor.vue';
import type { TypeRow } from '@/components/dealer/products/TypesEditor.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Spinner } from '@/components/ui/spinner';
import { useCurrency } from '@/composables/useCurrency';
import { usePackPrice } from '@/composables/usePackPrice';
import { useProductImages } from '@/composables/useProductImages';
import { useUnitLabel } from '@/composables/useUnitLabel';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{ categories: { id: number; name: string }[] }>();

const { t } = useI18n();
const { symbol } = useCurrency();
const unitLabel = useUnitLabel();

const form = useForm({
    name: '',
    category_id: null as number | null,
    description: '',
    price: null as number | null,
    pack_price: null as number | null,
    cost_price: null as number | null,
    pack_cost_price: null as number | null,
    stock: null as number | null,
    min_stock: null as number | null,
    pack_size: 1,
    bulk_only: false,
    has_types: false,
    unit: 'dona',
    visibility: 'bot_only',
    images: [] as File[],
    is_active: true,
});

const typeRows = ref<TypeRow[]>([]);
const removedTypeIds = ref<number[]>([]);
const submitErrors = ref<Record<string, string>>({});

const unitOptions = computed(() => [
    { value: 'dona', label: unitLabel('dona') },
    { value: 'kg', label: unitLabel('kg') },
]);

const visibilityOptions = [
    {
        value: 'bot_only',
        label: t('pageDealer.products.create.visibilityBotOnlyLabel'),
        hint: t('pageDealer.products.create.visibilityBotOnlyHint'),
    },
    {
        value: 'marketplace_only',
        label: t('pageDealer.products.create.visibilityMarketplaceOnlyLabel'),
        hint: t('pageDealer.products.create.visibilityMarketplaceOnlyHint'),
    },
    {
        value: 'both',
        label: t('pageDealer.products.create.visibilityBothLabel'),
        hint: t('pageDealer.products.create.visibilityBothHint'),
    },
];

const { onPackPriceInput } = usePackPrice({
    price: toRef(form, 'price'),
    packSize: toRef(form, 'pack_size'),
    packPrice: toRef(form, 'pack_price'),
});

const packHint = computed(() => {
    const s = Number(form.pack_size) || 1;

    return s <= 1
        ? null
        : t('pageDealer.products.create.packHint', {
              size: s,
              unit: unitLabel(form.unit),
          });
});

const {
    imagePreviews,
    add: addImages,
    remove: removeImage,
    makePrimary,
    files,
} = useProductImages(10);

watch(
    imagePreviews,
    () => {
        form.images = files();
    },
    { deep: true },
);

function buildTypesPayload(): Array<Record<string, unknown>> {
    return typeRows.value.map((r, idx) => ({
        name: r.name,
        price: r.price ?? 0,
        pack_price: r.pack_price ?? null,
        cost_price: r.cost_price ?? null,
        pack_cost_price: r.pack_cost_price ?? null,
        stock: r.stock ?? 0,
        min_stock: r.min_stock ?? 0,
        pack_size: r.pack_size,
        bulk_only: r.bulk_only ? 1 : 0,
        sort_order: idx,
        is_active: r.is_active ? 1 : 0,
        images: r.new_files,
        image_order: r.image_order,
    }));
}

function submit() {
    submitErrors.value = {};

    form.transform((data) => ({
        ...data,
        bulk_only: data.bulk_only ? 1 : 0,
        has_types: data.has_types ? 1 : 0,
        is_active: data.is_active ? 1 : 0,
        min_stock: data.min_stock ?? 0,
        ...(form.has_types ? { types: buildTypesPayload() } : {}),
    })).post('/dealer/products', {
        forceFormData: true,
        onError: (e) => {
            submitErrors.value = e as Record<string, string>;
        },
    });
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.products.create.headTitle')" />

    <div class="mx-auto w-full max-w-4xl p-3 md:p-6">
        <div class="mb-4 flex items-center gap-2.5">
            <Button
                variant="ghost"
                size="icon"
                class="h-9 w-9 shrink-0"
                @click="router.get('/dealer/products')"
            >
                <ArrowLeft class="h-4.5 w-4.5" />
            </Button>
            <div class="min-w-0">
                <h1 class="text-lg font-bold tracking-tight sm:text-xl">
                    {{ t('pageDealer.products.create.headTitle') }}
                </h1>
                <p class="text-xs text-muted-foreground">
                    {{ t('pageDealer.products.create.subtitle') }}
                </p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-3">
            <!-- Asosiy ma'lumotlar -->
            <Card>
                <div class="flex items-center gap-2 border-b px-4 py-2.5">
                    <div class="rounded-md bg-primary/10 p-1 text-primary">
                        <Package class="h-3.5 w-3.5" />
                    </div>
                    <h3 class="text-sm font-semibold">
                        {{ t('pageDealer.products.create.basicInfo') }}
                    </h3>
                </div>
                <CardContent class="grid gap-3 p-4 sm:grid-cols-2">
                    <div>
                        <Label for="name" class="mb-1 text-xs"
                            >{{ t('pageDealer.products.create.nameLabel') }}
                            <span class="text-destructive">*</span></Label
                        >
                        <Input
                            id="name"
                            v-model="form.name"
                            :placeholder="
                                t('pageDealer.products.create.namePlaceholder')
                            "
                            required
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <Label class="mb-1 text-xs">{{
                            t('pageDealer.products.create.categoryLabel')
                        }}</Label>
                        <SearchableSelect
                            v-model="form.category_id"
                            :items="categories"
                            value-key="id"
                            label-key="name"
                            :placeholder="
                                t(
                                    'pageDealer.products.create.categoryPlaceholder',
                                )
                            "
                            :search-placeholder="
                                t(
                                    'pageDealer.products.create.categorySearchPlaceholder',
                                )
                            "
                            :empty-text="
                                t(
                                    'pageDealer.products.create.categoryEmptyText',
                                )
                            "
                        />
                        <InputError :message="form.errors.category_id" />
                    </div>
                    <div class="sm:col-span-2">
                        <Label for="description" class="mb-1 text-xs">{{
                            t('pageDealer.products.create.descriptionLabel')
                        }}</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="2"
                            :placeholder="
                                t(
                                    'pageDealer.products.create.descriptionPlaceholder',
                                )
                            "
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        />
                    </div>
                </CardContent>
            </Card>

            <!-- Rasmlar -->
            <Card>
                <div
                    class="flex items-center justify-between gap-2 border-b px-4 py-2.5"
                >
                    <div class="flex items-center gap-2">
                        <div class="rounded-md bg-primary/10 p-1 text-primary">
                            <ImagePlus class="h-3.5 w-3.5" />
                        </div>
                        <h3 class="text-sm font-semibold">
                            {{ t('pageDealer.products.create.images') }}
                        </h3>
                    </div>
                    <span class="text-xs text-muted-foreground"
                        >{{ imagePreviews.length }} / 10</span
                    >
                </div>
                <CardContent class="space-y-2.5 p-4">
                    <draggable
                        v-model="imagePreviews"
                        item-key="url"
                        class="grid grid-cols-4 gap-2 sm:grid-cols-5 md:grid-cols-6"
                        :animation="180"
                        :delay="200"
                        :delay-on-touch-only="true"
                        :touch-start-threshold="5"
                        filter=".no-drag"
                        :prevent-on-filter="false"
                        ghost-class="opacity-40"
                    >
                        <template #item="{ element: p, index: idx }">
                            <div
                                class="group relative aspect-square cursor-grab overflow-hidden rounded-md border select-none active:cursor-grabbing"
                            >
                                <img
                                    :src="p.url"
                                    class="pointer-events-none h-full w-full object-cover"
                                    draggable="false"
                                />
                                <div
                                    v-if="idx === 0"
                                    class="pointer-events-none absolute top-1 left-1 rounded bg-primary px-1.5 py-0.5 text-[10px] font-medium text-primary-foreground"
                                >
                                    {{
                                        t(
                                            'pageDealer.products.create.primaryBadge',
                                        )
                                    }}
                                </div>
                                <div
                                    class="absolute inset-x-0 bottom-0 flex items-center justify-center gap-1 bg-gradient-to-t from-black/60 to-transparent p-1"
                                >
                                    <button
                                        v-if="idx > 0"
                                        type="button"
                                        class="no-drag flex h-6 w-6 items-center justify-center rounded-full bg-white text-amber-500 shadow"
                                        :title="
                                            t(
                                                'pageDealer.products.create.makePrimary',
                                            )
                                        "
                                        @click="makePrimary(idx)"
                                    >
                                        <Star class="h-3 w-3" />
                                    </button>
                                    <button
                                        type="button"
                                        class="no-drag flex h-6 w-6 items-center justify-center rounded-full bg-destructive text-white shadow"
                                        :title="
                                            t(
                                                'pageDealer.products.create.remove',
                                            )
                                        "
                                        @click="removeImage(idx)"
                                    >
                                        <X class="h-3 w-3" />
                                    </button>
                                </div>
                            </div>
                        </template>
                        <template #footer>
                            <label
                                v-if="imagePreviews.length < 10"
                                class="no-drag group flex aspect-square cursor-pointer flex-col items-center justify-center rounded-md border-2 border-dashed transition-colors hover:border-primary hover:bg-muted/50"
                            >
                                <ImagePlus
                                    class="mb-0.5 h-5 w-5 text-muted-foreground/50 group-hover:text-primary"
                                />
                                <span
                                    class="text-[10px] text-muted-foreground"
                                    >{{
                                        t('pageDealer.products.create.addImage')
                                    }}</span
                                >
                                <input
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    class="hidden"
                                    @change="addImages"
                                />
                            </label>
                        </template>
                    </draggable>
                    <p class="text-[11px] text-muted-foreground">
                        {{ t('pageDealer.products.create.imagesHint') }}
                    </p>
                    <InputError
                        :message="form.errors.images as unknown as string"
                    />
                </CardContent>
            </Card>

            <!-- Narx, ombor, blok -->
            <Card>
                <div class="flex items-center gap-2 border-b px-4 py-2.5">
                    <div class="rounded-md bg-primary/10 p-1 text-primary">
                        <DollarSign class="h-3.5 w-3.5" />
                    </div>
                    <h3 class="text-sm font-semibold">
                        {{ t('pageDealer.products.create.priceStockSection') }}
                    </h3>
                </div>
                <CardContent class="space-y-3 p-4">
                    <label
                        class="flex items-start gap-2 rounded-md border bg-muted/30 px-3 py-2"
                    >
                        <input
                            id="bulk_only"
                            type="checkbox"
                            v-model="form.bulk_only"
                            :disabled="form.pack_size <= 1"
                            class="mt-0.5 rounded border-input"
                        />
                        <span class="flex-1">
                            <span class="block text-xs font-medium">{{
                                t('pageDealer.products.create.bulkOnlyLabel')
                            }}</span>
                            <span
                                class="mt-0.5 block text-[11px] text-muted-foreground"
                            >
                                {{
                                    t('pageDealer.products.create.bulkOnlyHint')
                                }}
                            </span>
                        </span>
                    </label>
                    <InputError :message="form.errors.bulk_only" />
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <Label class="mb-1 text-xs"
                                >{{ t('pageDealer.products.create.unitLabel') }}
                                <span class="text-destructive">*</span></Label
                            >
                            <div class="flex h-10 gap-2">
                                <label
                                    v-for="opt in unitOptions"
                                    :key="opt.value"
                                    class="flex flex-1 cursor-pointer items-center justify-center rounded-md border px-3 text-sm transition-colors"
                                    :class="
                                        form.unit === opt.value
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'hover:bg-muted/30'
                                    "
                                >
                                    <input
                                        type="radio"
                                        :value="opt.value"
                                        v-model="form.unit"
                                        class="sr-only"
                                    />
                                    {{ opt.label }}
                                </label>
                            </div>
                        </div>
                        <div>
                            <Label for="pack_size" class="mb-1 text-xs"
                                >{{
                                    t(
                                        'pageDealer.products.create.packSizeLabel',
                                        { unit: unitLabel(form.unit) },
                                    )
                                }}
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="pack_size"
                                type="number"
                                step="0.001"
                                v-model.number="form.pack_size"
                                min="0.001"
                                required
                            />
                            <InputError :message="form.errors.pack_size" />
                        </div>
                        <div>
                            <Label for="price" class="mb-1 text-xs"
                                >{{
                                    t('pageDealer.products.create.priceLabel', {
                                        unit: unitLabel(form.unit),
                                        currency: symbol,
                                    })
                                }}
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="price"
                                type="number"
                                step="any"
                                v-model.number="form.price"
                                min="0"
                                required
                            />
                            <InputError :message="form.errors.price" />
                        </div>
                        <div>
                            <Label for="pack_price" class="mb-1 text-xs">{{
                                t('pageDealer.products.create.packPriceLabel', {
                                    currency: symbol,
                                })
                            }}</Label>
                            <Input
                                id="pack_price"
                                type="number"
                                step="0.01"
                                :model-value="form.pack_price ?? ''"
                                @update:model-value="
                                    (v) =>
                                        onPackPriceInput(
                                            v === '' ? null : Number(v),
                                        )
                                "
                                min="0"
                                :disabled="form.pack_size <= 1"
                            />
                        </div>
                        <div>
                            <Label for="cost_price" class="mb-1 text-xs">{{
                                t('pageDealer.products.create.costPriceLabel', {
                                    unit: unitLabel(form.unit),
                                    currency: symbol,
                                })
                            }}</Label>
                            <Input
                                id="cost_price"
                                type="number"
                                step="any"
                                :model-value="form.cost_price ?? ''"
                                @update:model-value="
                                    (v) =>
                                        (form.cost_price =
                                            v === '' ? null : Number(v))
                                "
                                min="0"
                                :placeholder="
                                    t(
                                        'pageDealer.products.create.costPricePlaceholder',
                                    )
                                "
                            />
                            <InputError :message="form.errors.cost_price" />
                        </div>
                        <div>
                            <Label for="pack_cost_price" class="mb-1 text-xs">{{
                                t(
                                    'pageDealer.products.create.packCostPriceLabel',
                                    { currency: symbol },
                                )
                            }}</Label>
                            <Input
                                id="pack_cost_price"
                                type="number"
                                step="0.01"
                                :model-value="form.pack_cost_price ?? ''"
                                @update:model-value="
                                    (v) =>
                                        (form.pack_cost_price =
                                            v === '' ? null : Number(v))
                                "
                                min="0"
                                :disabled="form.pack_size <= 1"
                            />
                        </div>
                        <div>
                            <Label for="stock" class="mb-1 text-xs"
                                >{{
                                    t('pageDealer.products.create.stockLabel', {
                                        unit: unitLabel(form.unit),
                                    })
                                }}
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="stock"
                                type="number"
                                step="0.001"
                                v-model.number="form.stock"
                                required
                            />
                            <InputError :message="form.errors.stock" />
                        </div>
                        <div>
                            <Label for="min_stock" class="mb-1 text-xs">{{
                                t('pageDealer.products.create.minStockLabel', {
                                    unit: unitLabel(form.unit),
                                })
                            }}</Label>
                            <Input
                                id="min_stock"
                                type="number"
                                step="0.001"
                                v-model.number="form.min_stock"
                                min="0"
                                :placeholder="
                                    t(
                                        'pageDealer.products.create.minStockPlaceholder',
                                    )
                                "
                            />
                            <InputError :message="form.errors.min_stock" />
                        </div>
                    </div>
                    <p
                        v-if="packHint || form.min_stock"
                        class="text-[11px] text-muted-foreground"
                    >
                        <span v-if="packHint">{{ packHint }}</span>
                        <span v-if="packHint && form.min_stock"> · </span>
                        <span v-if="form.min_stock">{{
                            t('pageDealer.products.create.minStockNotice')
                        }}</span>
                    </p>
                </CardContent>
            </Card>

            <!-- Assortiment -->
            <Card>
                <div class="flex items-center gap-2 border-b px-4 py-2.5">
                    <div class="rounded-md bg-primary/10 p-1 text-primary">
                        <Palette class="h-3.5 w-3.5" />
                    </div>
                    <h3 class="text-sm font-semibold">
                        {{ t('pageDealer.products.create.assortmentSection') }}
                    </h3>
                </div>
                <CardContent class="space-y-3 p-4">
                    <label
                        class="flex items-start gap-2 rounded-md border bg-muted/30 px-3 py-2"
                    >
                        <input
                            type="checkbox"
                            v-model="form.has_types"
                            class="mt-0.5 rounded border-input"
                        />
                        <span class="flex-1">
                            <span class="block text-xs font-medium">{{
                                t('pageDealer.products.create.hasTypesLabel')
                            }}</span>
                            <span
                                class="mt-0.5 block text-[11px] text-muted-foreground"
                            >
                                {{
                                    t('pageDealer.products.create.hasTypesHint')
                                }}
                            </span>
                        </span>
                    </label>

                    <TypesEditor
                        v-if="form.has_types"
                        v-model="typeRows"
                        v-model:removed-type-ids="removedTypeIds"
                        :unit="form.unit"
                        :errors="submitErrors"
                        :defaults="{
                            price: form.price,
                            pack_price: form.pack_price,
                            stock: form.stock,
                            min_stock: form.min_stock,
                            pack_size: form.pack_size,
                            bulk_only: form.bulk_only,
                        }"
                    />
                </CardContent>
            </Card>

            <!-- Ko'rinish kanali -->
            <Card>
                <div class="flex items-center gap-2 border-b px-4 py-2.5">
                    <div class="rounded-md bg-primary/10 p-1 text-primary">
                        <Eye class="h-3.5 w-3.5" />
                    </div>
                    <h3 class="text-sm font-semibold">
                        {{ t('pageDealer.products.create.visibilitySection') }}
                    </h3>
                </div>
                <CardContent class="p-4">
                    <div class="grid gap-2 sm:grid-cols-3">
                        <label
                            v-for="opt in visibilityOptions"
                            :key="opt.value"
                            class="flex cursor-pointer flex-col gap-0.5 rounded-md border px-3 py-2.5 transition-colors"
                            :class="
                                form.visibility === opt.value
                                    ? 'border-primary bg-primary/10'
                                    : 'hover:bg-muted/30'
                            "
                        >
                            <span class="flex items-center gap-2">
                                <input
                                    type="radio"
                                    :value="opt.value"
                                    v-model="form.visibility"
                                    class="border-input"
                                />
                                <span class="text-xs font-medium">{{
                                    opt.label
                                }}</span>
                            </span>
                            <span
                                class="pl-6 text-[11px] text-muted-foreground"
                                >{{ opt.hint }}</span
                            >
                        </label>
                    </div>
                    <InputError :message="form.errors.visibility" />
                </CardContent>
            </Card>

            <!-- Action bar -->
            <div
                class="sticky bottom-0 z-10 -mx-3 flex items-center justify-end gap-2.5 border-t bg-background/85 px-3 py-3 backdrop-blur md:-mx-6 md:px-6"
            >
                <Button
                    variant="outline"
                    type="button"
                    @click="router.get('/dealer/products')"
                    >{{ t('pageDealer.products.create.cancel') }}</Button
                >
                <Button
                    type="submit"
                    :disabled="form.processing"
                    class="min-w-[120px]"
                >
                    <Spinner v-if="form.processing" class="mr-2" />
                    {{
                        form.processing
                            ? t('pageDealer.products.create.creating')
                            : t('pageDealer.products.create.create')
                    }}
                </Button>
            </div>
        </form>
    </div>
</template>
