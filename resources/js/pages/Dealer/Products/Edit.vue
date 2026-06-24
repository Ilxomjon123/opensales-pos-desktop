<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ImagePlus,
    Package,
    DollarSign,
    Palette,
    X,
    ZoomIn,
    Star,
    Eye,
} from 'lucide-vue-next';
import { computed, reactive, ref, toRef } from 'vue';
import { useI18n } from 'vue-i18n';
import draggable from 'vuedraggable';
import TypesEditor from '@/components/dealer/products/TypesEditor.vue';
import type { TypeRow } from '@/components/dealer/products/TypesEditor.vue';
import ImageLightbox from '@/components/ImageLightbox.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { Spinner } from '@/components/ui/spinner';
import { useCurrency } from '@/composables/useCurrency';
import { usePackPrice } from '@/composables/usePackPrice';
import { useUnitLabel } from '@/composables/useUnitLabel';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Product } from '@/types';

const props = defineProps<{
    product: { data: Product };
    categories: { id: number; name: string }[];
}>();

const p = props.product.data;

const { t } = useI18n();
const { symbol } = useCurrency();
const unitLabel = useUnitLabel();

const formData = reactive({
    name: p.name,
    category_id: p.category_id ?? null,
    description: p.description ?? '',
    price: p.price as number | null,
    pack_price: (p.pack_price ?? null) as number | null,
    cost_price: (p.cost_price ?? null) as number | null,
    pack_cost_price: (p.pack_cost_price ?? null) as number | null,
    stock: p.stock,
    min_stock: p.min_stock ?? 0,
    pack_size: p.pack_size ?? 1,
    bulk_only: p.bulk_only ?? false,
    has_types: p.has_types ?? false,
    unit: p.unit,
    visibility: p.visibility ?? 'bot_only',
    is_active: p.is_active,
});

const typeRows = ref<TypeRow[]>([]);
const removedTypeIds = ref<number[]>([]);

const initialTypes = computed(() =>
    (p.types ?? []).map((t) => ({
        id: t.id,
        name: t.name,
        price: t.price,
        pack_price: t.pack_price ?? null,
        cost_price: t.cost_price ?? null,
        pack_cost_price: t.pack_cost_price ?? null,
        stock: t.stock,
        min_stock: t.min_stock,
        pack_size: t.pack_size,
        bulk_only: t.bulk_only,
        sort_order: t.sort_order,
        is_active: t.is_active,
        images: t.images,
    })),
);

type ExistingItem = { kind: 'existing'; id: number; url: string };
type NewItem = { kind: 'new'; file: File; url: string };
type ImageItem = ExistingItem | NewItem;

const images = ref<ImageItem[]>(
    (p.images ?? []).map((i) => ({ kind: 'existing', id: i.id, url: i.url })),
);
const removedImageIds = ref<number[]>([]);

const totalImages = computed(() => images.value.length);

const lightboxOpen = ref(false);
const lightboxIdx = ref(0);
const lightboxImages = computed(() => images.value.map((i) => i.url));
function openZoom(idx: number) {
    lightboxIdx.value = idx;
    lightboxOpen.value = true;
}

const unitOptions = computed(() => [
    { value: 'dona', label: unitLabel('dona') },
    { value: 'kg', label: unitLabel('kg') },
]);

const visibilityOptions = [
    {
        value: 'bot_only',
        label: t('pageDealer.products.edit.visibilityBotOnlyLabel'),
        hint: t('pageDealer.products.edit.visibilityBotOnlyHint'),
    },
    {
        value: 'marketplace_only',
        label: t('pageDealer.products.edit.visibilityMarketplaceOnlyLabel'),
        hint: t('pageDealer.products.edit.visibilityMarketplaceOnlyHint'),
    },
    {
        value: 'both',
        label: t('pageDealer.products.edit.visibilityBothLabel'),
        hint: t('pageDealer.products.edit.visibilityBothHint'),
    },
];

const { onPackPriceInput } = usePackPrice({
    price: toRef(formData, 'price'),
    packSize: toRef(formData, 'pack_size'),
    packPrice: toRef(formData, 'pack_price'),
});

const packHint = computed(() => {
    const s = Number(formData.pack_size) || 1;

    return s <= 1
        ? null
        : t('pageDealer.products.edit.packHint', {
              size: s,
              unit: unitLabel(formData.unit),
          });
});

const processing = ref(false);
const errors = ref<Record<string, string>>({});

function onImagesChange(e: Event) {
    const files = (e.target as HTMLInputElement).files;

    if (!files) {
        return;
    }

    for (const file of Array.from(files)) {
        if (images.value.length >= 10) {
            break;
        }

        images.value.push({
            kind: 'new',
            file,
            url: URL.createObjectURL(file),
        });
    }

    (e.target as HTMLInputElement).value = '';
}

function removeImage(idx: number) {
    const item = images.value[idx];

    if (item.kind === 'new') {
        URL.revokeObjectURL(item.url);
    } else {
        removedImageIds.value.push(item.id);
    }

    images.value.splice(idx, 1);
}

function makePrimary(idx: number) {
    if (idx <= 0) {
        return;
    }

    const [picked] = images.value.splice(idx, 1);
    images.value.unshift(picked);
}

function submit() {
    processing.value = true;
    const data = new FormData();
    data.append('_method', 'PUT');
    data.append('name', formData.name);

    if (formData.category_id !== null) {
        data.append('category_id', String(formData.category_id));
    }

    data.append('description', formData.description);
    data.append('price', String(formData.price));

    if (formData.pack_price !== null && formData.pack_price !== undefined) {
        data.append('pack_price', String(formData.pack_price));
    }

    if (formData.cost_price !== null && formData.cost_price !== undefined) {
        data.append('cost_price', String(formData.cost_price));
    }

    if (
        formData.pack_cost_price !== null &&
        formData.pack_cost_price !== undefined
    ) {
        data.append('pack_cost_price', String(formData.pack_cost_price));
    }

    data.append('stock', String(formData.stock));
    data.append('min_stock', String(formData.min_stock ?? 0));
    data.append('pack_size', String(formData.pack_size));
    data.append('bulk_only', formData.bulk_only ? '1' : '0');
    data.append('has_types', formData.has_types ? '1' : '0');
    data.append('unit', formData.unit);
    data.append('visibility', formData.visibility);
    data.append('is_active', formData.is_active ? '1' : '0');

    let newIdx = 0;

    for (const item of images.value) {
        if (item.kind === 'new') {
            data.append('images[]', item.file);
            data.append('image_order[]', `new:${newIdx}`);
            newIdx++;
        } else {
            data.append('image_order[]', `ex:${item.id}`);
        }
    }

    removedImageIds.value.forEach((id) =>
        data.append('remove_image_ids[]', String(id)),
    );

    if (formData.has_types) {
        typeRows.value.forEach((r, idx) => {
            const prefix = `types[${idx}]`;

            if (r.id) {
                data.append(`${prefix}[id]`, String(r.id));
            }

            data.append(`${prefix}[name]`, r.name);
            data.append(`${prefix}[price]`, String(r.price ?? 0));

            if (r.pack_price !== null && r.pack_price !== undefined) {
                data.append(`${prefix}[pack_price]`, String(r.pack_price));
            }

            if (r.cost_price !== null && r.cost_price !== undefined) {
                data.append(`${prefix}[cost_price]`, String(r.cost_price));
            }

            if (r.pack_cost_price !== null && r.pack_cost_price !== undefined) {
                data.append(
                    `${prefix}[pack_cost_price]`,
                    String(r.pack_cost_price),
                );
            }

            data.append(`${prefix}[stock]`, String(r.stock ?? 0));
            data.append(`${prefix}[min_stock]`, String(r.min_stock ?? 0));
            data.append(`${prefix}[pack_size]`, String(r.pack_size));
            data.append(`${prefix}[bulk_only]`, r.bulk_only ? '1' : '0');
            data.append(`${prefix}[sort_order]`, String(idx));
            data.append(`${prefix}[is_active]`, r.is_active ? '1' : '0');

            r.new_files.forEach((f, i) => {
                data.append(`${prefix}[images][${i}]`, f);
            });

            r.image_order.forEach((t) => {
                data.append(`${prefix}[image_order][]`, t);
            });

            r.remove_image_ids.forEach((id) => {
                data.append(`${prefix}[remove_image_ids][]`, String(id));
            });
        });
    }

    removedTypeIds.value.forEach((id) =>
        data.append('removed_type_ids[]', String(id)),
    );

    router.post(`/dealer/products/${p.id}`, data, {
        onFinish: () => {
            processing.value = false;
        },
        onError: (e) => {
            errors.value = e;
        },
    });
}

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head :title="t('pageDealer.products.edit.headTitle', { name: p.name })" />

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
            <div class="min-w-0 flex-1">
                <h1
                    class="truncate text-lg font-bold tracking-tight sm:text-xl"
                >
                    {{ p.name }}
                </h1>
                <p class="text-xs text-muted-foreground">
                    {{ t('pageDealer.products.edit.subtitle') }}
                </p>
            </div>
            <Badge
                :variant="p.is_active ? 'default' : 'destructive'"
                class="shrink-0"
            >
                {{
                    p.is_active
                        ? t('pageDealer.products.edit.active')
                        : t('pageDealer.products.edit.inactive')
                }}
            </Badge>
        </div>

        <form @submit.prevent="submit" class="space-y-3">
            <!-- Asosiy -->
            <Card>
                <div
                    class="flex items-center justify-between gap-2 border-b px-4 py-2.5"
                >
                    <div class="flex items-center gap-2">
                        <div class="rounded-md bg-primary/10 p-1 text-primary">
                            <Package class="h-3.5 w-3.5" />
                        </div>
                        <h3 class="text-sm font-semibold">
                            {{ t('pageDealer.products.edit.basicInfo') }}
                        </h3>
                    </div>
                    <label class="flex items-center gap-1.5 text-xs">
                        <input
                            id="is_active"
                            type="checkbox"
                            v-model="formData.is_active"
                            class="rounded border-input"
                        />
                        <span>{{ t('pageDealer.products.edit.active') }}</span>
                    </label>
                </div>
                <CardContent class="grid gap-3 p-4 sm:grid-cols-2">
                    <div>
                        <Label for="name" class="mb-1 text-xs"
                            >{{ t('pageDealer.products.edit.nameLabel') }}
                            <span class="text-destructive">*</span></Label
                        >
                        <Input id="name" v-model="formData.name" required />
                        <InputError :message="errors.name" />
                    </div>
                    <div>
                        <Label class="mb-1 text-xs">{{
                            t('pageDealer.products.edit.categoryLabel')
                        }}</Label>
                        <SearchableSelect
                            v-model="formData.category_id"
                            :items="categories"
                            value-key="id"
                            label-key="name"
                            :placeholder="
                                t(
                                    'pageDealer.products.edit.categoryPlaceholder',
                                )
                            "
                            :search-placeholder="
                                t(
                                    'pageDealer.products.edit.categorySearchPlaceholder',
                                )
                            "
                            :empty-text="
                                t('pageDealer.products.edit.categoryEmptyText')
                            "
                        />
                        <InputError :message="errors.category_id" />
                    </div>
                    <div class="sm:col-span-2">
                        <Label for="description" class="mb-1 text-xs">{{
                            t('pageDealer.products.edit.descriptionLabel')
                        }}</Label>
                        <textarea
                            id="description"
                            v-model="formData.description"
                            rows="2"
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
                            {{ t('pageDealer.products.edit.images') }}
                        </h3>
                    </div>
                    <span class="text-xs text-muted-foreground"
                        >{{ totalImages }} / 10</span
                    >
                </div>
                <CardContent class="space-y-2.5 p-4">
                    <draggable
                        v-model="images"
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
                        <template #item="{ element: item, index: idx }">
                            <div
                                class="group relative aspect-square cursor-grab overflow-hidden rounded-md border select-none active:cursor-grabbing"
                                :class="
                                    item.kind === 'new'
                                        ? 'border-emerald-500/50 ring-1 ring-emerald-500/30'
                                        : ''
                                "
                            >
                                <img
                                    :src="item.url"
                                    class="pointer-events-none h-full w-full object-cover"
                                    draggable="false"
                                />
                                <div
                                    v-if="idx === 0"
                                    class="pointer-events-none absolute top-1 left-1 rounded bg-primary px-1.5 py-0.5 text-[10px] font-medium text-primary-foreground"
                                >
                                    {{
                                        t(
                                            'pageDealer.products.edit.primaryBadge',
                                        )
                                    }}
                                </div>
                                <div
                                    v-else-if="item.kind === 'new'"
                                    class="pointer-events-none absolute top-1 left-1 rounded bg-emerald-500 px-1.5 py-0.5 text-[10px] font-medium text-white"
                                >
                                    {{ t('pageDealer.products.edit.newBadge') }}
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
                                                'pageDealer.products.edit.makePrimaryTitle',
                                            )
                                        "
                                        @click.stop="makePrimary(idx)"
                                    >
                                        <Star class="h-3 w-3" />
                                    </button>
                                    <button
                                        type="button"
                                        class="no-drag flex h-6 w-6 items-center justify-center rounded-full bg-white text-neutral-800 shadow"
                                        :title="
                                            t(
                                                'pageDealer.products.edit.zoomTitle',
                                            )
                                        "
                                        @click.stop="openZoom(idx)"
                                    >
                                        <ZoomIn class="h-3 w-3" />
                                    </button>
                                    <button
                                        type="button"
                                        class="no-drag flex h-6 w-6 items-center justify-center rounded-full bg-destructive text-white shadow"
                                        :title="
                                            t(
                                                'pageDealer.products.edit.deleteTitle',
                                            )
                                        "
                                        @click.stop="removeImage(idx)"
                                    >
                                        <X class="h-3 w-3" />
                                    </button>
                                </div>
                            </div>
                        </template>
                        <template #footer>
                            <label
                                v-if="totalImages < 10"
                                class="no-drag group flex aspect-square cursor-pointer flex-col items-center justify-center rounded-md border-2 border-dashed transition-colors hover:border-primary hover:bg-muted/50"
                            >
                                <ImagePlus
                                    class="mb-0.5 h-5 w-5 text-muted-foreground/50 group-hover:text-primary"
                                />
                                <span
                                    class="text-[10px] text-muted-foreground"
                                    >{{
                                        t('pageDealer.products.edit.addImage')
                                    }}</span
                                >
                                <input
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    class="hidden"
                                    @change="onImagesChange"
                                />
                            </label>
                        </template>
                    </draggable>
                    <p class="text-[11px] text-muted-foreground">
                        {{ t('pageDealer.products.edit.imagesHint') }}
                    </p>
                    <InputError :message="errors.images" />
                </CardContent>
            </Card>

            <!-- Narx, ombor, blok -->
            <Card>
                <div class="flex items-center gap-2 border-b px-4 py-2.5">
                    <div class="rounded-md bg-primary/10 p-1 text-primary">
                        <DollarSign class="h-3.5 w-3.5" />
                    </div>
                    <h3 class="text-sm font-semibold">
                        {{
                            t('pageDealer.products.edit.priceStockPackSection')
                        }}
                    </h3>
                </div>
                <CardContent class="space-y-3 p-4">
                    <label
                        class="flex items-start gap-2 rounded-md border bg-muted/30 px-3 py-2"
                    >
                        <input
                            id="bulk_only"
                            type="checkbox"
                            v-model="formData.bulk_only"
                            :disabled="formData.pack_size <= 1"
                            class="mt-0.5 rounded border-input"
                        />
                        <span class="flex-1">
                            <span class="block text-xs font-medium">{{
                                t('pageDealer.products.edit.bulkOnlyLabel')
                            }}</span>
                            <span
                                class="mt-0.5 block text-[11px] text-muted-foreground"
                            >
                                {{ t('pageDealer.products.edit.bulkOnlyHint') }}
                            </span>
                        </span>
                    </label>
                    <InputError :message="errors.bulk_only" />
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <Label class="mb-1 text-xs"
                                >{{ t('pageDealer.products.edit.unitLabel') }}
                                <span class="text-destructive">*</span></Label
                            >
                            <div class="flex h-10 gap-2">
                                <label
                                    v-for="opt in unitOptions"
                                    :key="opt.value"
                                    class="flex flex-1 cursor-pointer items-center justify-center rounded-md border px-3 text-sm transition-colors"
                                    :class="
                                        formData.unit === opt.value
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'hover:bg-muted/30'
                                    "
                                >
                                    <input
                                        type="radio"
                                        :value="opt.value"
                                        v-model="formData.unit"
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
                                        'pageDealer.products.edit.packSizeLabel',
                                        { unit: unitLabel(formData.unit) },
                                    )
                                }}
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="pack_size"
                                type="number"
                                step="0.001"
                                v-model.number="formData.pack_size"
                                min="0.001"
                                required
                            />
                            <InputError :message="errors.pack_size" />
                        </div>
                        <div>
                            <Label for="price" class="mb-1 text-xs"
                                >{{
                                    t('pageDealer.products.edit.priceLabel', {
                                        unit: unitLabel(formData.unit),
                                        currency: symbol,
                                    })
                                }}
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="price"
                                type="number"
                                step="any"
                                v-model.number="formData.price"
                                min="0"
                                required
                            />
                            <InputError :message="errors.price" />
                        </div>
                        <div>
                            <Label for="pack_price" class="mb-1 text-xs">{{
                                t('pageDealer.products.edit.packPriceLabel', {
                                    currency: symbol,
                                })
                            }}</Label>
                            <Input
                                id="pack_price"
                                type="number"
                                step="0.01"
                                :model-value="formData.pack_price ?? ''"
                                @update:model-value="
                                    (v) =>
                                        onPackPriceInput(
                                            v === '' ? null : Number(v),
                                        )
                                "
                                min="0"
                                :disabled="formData.pack_size <= 1"
                            />
                        </div>
                        <div>
                            <Label for="cost_price" class="mb-1 text-xs">{{
                                t('pageDealer.products.edit.costPriceLabel', {
                                    unit: unitLabel(formData.unit),
                                    currency: symbol,
                                })
                            }}</Label>
                            <Input
                                id="cost_price"
                                type="number"
                                step="any"
                                :model-value="formData.cost_price ?? ''"
                                @update:model-value="
                                    (v) =>
                                        (formData.cost_price =
                                            v === '' ? null : Number(v))
                                "
                                min="0"
                                :placeholder="
                                    t(
                                        'pageDealer.products.edit.costPricePlaceholder',
                                    )
                                "
                            />
                            <InputError :message="errors.cost_price" />
                        </div>
                        <div>
                            <Label for="pack_cost_price" class="mb-1 text-xs">{{
                                t(
                                    'pageDealer.products.edit.packCostPriceLabel',
                                    { currency: symbol },
                                )
                            }}</Label>
                            <Input
                                id="pack_cost_price"
                                type="number"
                                step="0.01"
                                :model-value="formData.pack_cost_price ?? ''"
                                @update:model-value="
                                    (v) =>
                                        (formData.pack_cost_price =
                                            v === '' ? null : Number(v))
                                "
                                min="0"
                                :disabled="formData.pack_size <= 1"
                            />
                        </div>
                        <div>
                            <Label for="stock" class="mb-1 text-xs"
                                >{{
                                    t('pageDealer.products.edit.stockLabel', {
                                        unit: unitLabel(formData.unit),
                                    })
                                }}
                                <span class="text-destructive">*</span></Label
                            >
                            <Input
                                id="stock"
                                type="number"
                                step="0.001"
                                v-model.number="formData.stock"
                                required
                            />
                            <InputError :message="errors.stock" />
                        </div>
                        <div>
                            <Label for="min_stock" class="mb-1 text-xs">{{
                                t('pageDealer.products.edit.minStockLabel', {
                                    unit: unitLabel(formData.unit),
                                })
                            }}</Label>
                            <Input
                                id="min_stock"
                                type="number"
                                step="0.001"
                                v-model.number="formData.min_stock"
                                min="0"
                                :placeholder="
                                    t(
                                        'pageDealer.products.edit.minStockPlaceholder',
                                    )
                                "
                            />
                            <InputError :message="errors.min_stock" />
                        </div>
                    </div>
                    <p
                        v-if="packHint || formData.min_stock"
                        class="text-[11px] text-muted-foreground"
                    >
                        <span v-if="packHint">{{ packHint }}</span>
                        <span v-if="packHint && formData.min_stock"> · </span>
                        <span v-if="formData.min_stock">{{
                            t('pageDealer.products.edit.minStockNote')
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
                        {{ t('pageDealer.products.edit.assortmentSection') }}
                    </h3>
                </div>
                <CardContent class="space-y-3 p-4">
                    <label
                        class="flex items-start gap-2 rounded-md border bg-muted/30 px-3 py-2"
                    >
                        <input
                            type="checkbox"
                            v-model="formData.has_types"
                            class="mt-0.5 rounded border-input"
                        />
                        <span class="flex-1">
                            <span class="block text-xs font-medium">{{
                                t(
                                    'pageDealer.products.edit.assortmentToggleLabel',
                                )
                            }}</span>
                            <span
                                class="mt-0.5 block text-[11px] text-muted-foreground"
                            >
                                {{
                                    t(
                                        'pageDealer.products.edit.assortmentToggleHint',
                                    )
                                }}
                            </span>
                        </span>
                    </label>

                    <TypesEditor
                        v-if="formData.has_types"
                        v-model="typeRows"
                        v-model:removed-type-ids="removedTypeIds"
                        :initial="initialTypes"
                        :unit="formData.unit"
                        :errors="errors"
                        :defaults="{
                            price: formData.price,
                            pack_price: formData.pack_price,
                            stock: formData.stock,
                            min_stock: formData.min_stock,
                            pack_size: formData.pack_size,
                            bulk_only: formData.bulk_only,
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
                        {{ t('pageDealer.products.edit.visibilitySection') }}
                    </h3>
                </div>
                <CardContent class="p-4">
                    <div class="grid gap-2 sm:grid-cols-3">
                        <label
                            v-for="opt in visibilityOptions"
                            :key="opt.value"
                            class="flex cursor-pointer flex-col gap-0.5 rounded-md border px-3 py-2.5 transition-colors"
                            :class="
                                formData.visibility === opt.value
                                    ? 'border-primary bg-primary/10'
                                    : 'hover:bg-muted/30'
                            "
                        >
                            <span class="flex items-center gap-2">
                                <input
                                    type="radio"
                                    :value="opt.value"
                                    v-model="formData.visibility"
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
                    <InputError :message="errors.visibility" />
                </CardContent>
            </Card>

            <div
                class="sticky bottom-0 z-10 -mx-3 flex items-center justify-end gap-2.5 border-t bg-background/85 px-3 py-3 backdrop-blur md:-mx-6 md:px-6"
            >
                <Button
                    variant="outline"
                    type="button"
                    @click="router.get('/dealer/products')"
                    >{{ t('pageDealer.products.edit.cancel') }}</Button
                >
                <Button
                    type="submit"
                    :disabled="processing"
                    class="min-w-[120px]"
                >
                    <Spinner v-if="processing" class="mr-2" />
                    {{
                        processing
                            ? t('pageDealer.products.edit.saving')
                            : t('pageDealer.products.edit.save')
                    }}
                </Button>
            </div>
        </form>

        <ImageLightbox
            :images="lightboxImages"
            :initial-idx="lightboxIdx"
            :open="lightboxOpen"
            @close="lightboxOpen = false"
        />
    </div>
</template>
