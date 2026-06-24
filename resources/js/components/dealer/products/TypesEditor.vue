<script setup lang="ts">
import { ImagePlus, Plus, Star, Trash2, X } from 'lucide-vue-next';
import { reactive, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCurrency } from '@/composables/useCurrency';
import { useTypeRowPackPrice } from '@/composables/useTypeRowPackPrice';

type ExistingImage = { id: number; url: string; sort_order: number };

type LocalImage = { file?: File; url: string; existing_id?: number };

export type TypeRow = {
    id?: number;
    name: string;
    price: number | null;
    pack_price: number | null;
    cost_price: number | null;
    pack_cost_price: number | null;
    stock: number | null;
    min_stock: number | null;
    pack_size: number;
    bulk_only: boolean;
    sort_order: number;
    is_active: boolean;
    images: LocalImage[];
    remove_image_ids: number[];
    image_order: string[];
    new_files: File[];
};

const props = defineProps<{
    modelValue: TypeRow[];
    removedTypeIds: number[];
    initial?: Array<{
        id: number;
        name: string;
        price: number;
        pack_price: number | null;
        cost_price?: number | null;
        pack_cost_price?: number | null;
        stock: number;
        min_stock: number | null;
        pack_size: number;
        bulk_only: boolean;
        sort_order: number;
        is_active: boolean;
        images: ExistingImage[];
    }>;
    errors?: Record<string, string | undefined>;
    unit?: string;
    defaults?: {
        price?: number | null;
        pack_price?: number | null;
        cost_price?: number | null;
        pack_cost_price?: number | null;
        stock?: number | null;
        min_stock?: number | null;
        pack_size?: number | null;
        bulk_only?: boolean;
    };
}>();

const emit = defineEmits<{
    'update:modelValue': [rows: TypeRow[]];
    'update:removedTypeIds': [ids: number[]];
}>();

const { t } = useI18n();
const { symbol } = useCurrency();

const rows = reactive<TypeRow[]>([]);
const removed = reactive<number[]>([]);

function pushFromInitial() {
    rows.splice(0, rows.length);
    removed.splice(0, removed.length);

    (props.initial ?? []).forEach((t, idx) => {
        rows.push({
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
            sort_order: t.sort_order ?? idx,
            is_active: t.is_active,
            images: (t.images ?? []).map((i) => ({
                url: i.url,
                existing_id: i.id,
            })),
            remove_image_ids: [],
            image_order: (t.images ?? []).map((i) => `ex:${i.id}`),
            new_files: [],
        });
    });
}

watch(() => props.initial, pushFromInitial, { immediate: true });

watch(rows, (v) => emit('update:modelValue', v as TypeRow[]), { deep: true });
watch(removed, (v) => emit('update:removedTypeIds', v as number[]), {
    deep: true,
});

function addRow() {
    const d = props.defaults ?? {};
    rows.push({
        name: '',
        price: d.price ?? null,
        pack_price: d.pack_price ?? null,
        cost_price: d.cost_price ?? null,
        pack_cost_price: d.pack_cost_price ?? null,
        stock: d.stock ?? 0,
        min_stock: d.min_stock ?? null,
        pack_size: Math.max(1, d.pack_size ?? 1),
        bulk_only: Boolean(d.bulk_only ?? false),
        sort_order: rows.length,
        is_active: true,
        images: [],
        remove_image_ids: [],
        image_order: [],
        new_files: [],
    });
}

const { onPackPriceInput } = useTypeRowPackPrice(rows);

function removeRow(idx: number) {
    const r = rows[idx];

    if (r?.id) {
        removed.push(r.id);
    }

    rows.splice(idx, 1);
}

function onAddImages(idx: number, e: Event) {
    const files = (e.target as HTMLInputElement).files;

    if (!files) {
        return;
    }

    const row = rows[idx];

    for (const file of Array.from(files)) {
        if (row.images.length >= 10) {
            break;
        }

        const url = URL.createObjectURL(file);
        row.images.push({ file, url });
        row.new_files.push(file);
        row.image_order.push(`new:${row.new_files.length - 1}`);
    }

    (e.target as HTMLInputElement).value = '';
}

function removeImage(rowIdx: number, imgIdx: number) {
    const row = rows[rowIdx];
    const img = row.images[imgIdx];

    if (!img) {
        return;
    }

    if (img.existing_id) {
        row.remove_image_ids.push(img.existing_id);
        row.image_order = row.image_order.filter(
            (t) => t !== `ex:${img.existing_id}`,
        );
    } else {
        const fileIdx = row.new_files.findIndex((f) => f === img.file);

        if (fileIdx >= 0) {
            row.new_files.splice(fileIdx, 1);
        }

        row.image_order = row.image_order.filter((t) => t !== `new:${fileIdx}`);
        // Reindex new tokens
        row.image_order = row.image_order.map((t) => {
            if (!t.startsWith('new:')) {
                return t;
            }

            const i = Number(t.slice(4));

            return i > fileIdx ? `new:${i - 1}` : t;
        });
    }

    URL.revokeObjectURL(img.url);
    row.images.splice(imgIdx, 1);
}

function makePrimary(rowIdx: number, imgIdx: number) {
    if (imgIdx <= 0) {
        return;
    }

    const row = rows[rowIdx];
    const [picked] = row.images.splice(imgIdx, 1);

    if (picked) {
        row.images.unshift(picked);
    }

    const tokenAt = (i: number): string => {
        const im = row.images[i];

        return im.existing_id
            ? `ex:${im.existing_id}`
            : `new:${row.new_files.findIndex((f) => f === im.file)}`;
    };

    row.image_order = row.images.map((_, i) => tokenAt(i));
}

function err(field: string, idx: number): string | undefined {
    return props.errors?.[`types.${idx}.${field}`];
}
</script>

<template>
    <div class="space-y-4">
        <div
            v-for="(r, idx) in rows"
            :key="r.id ?? `new-${idx}`"
            class="rounded-xl border bg-muted/10 p-4"
        >
            <div class="mb-3 flex items-center justify-between gap-2">
                <p class="text-sm font-semibold">
                    {{
                        t('pageDealer.products.typesEditor.assortmentNo', {
                            n: idx + 1,
                        })
                    }}
                </p>
                <div class="flex items-center gap-2">
                    <label class="flex items-center gap-1.5 text-xs">
                        <input
                            type="checkbox"
                            v-model="r.is_active"
                            class="rounded border-input"
                        />
                        <span>{{
                            t('pageDealer.products.typesEditor.active')
                        }}</span>
                    </label>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-7 w-7 text-destructive"
                        @click="removeRow(idx)"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </Button>
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <Label class="mb-1.5 text-xs"
                        >{{ t('pageDealer.products.typesEditor.nameLabel') }}
                        <span class="text-destructive">*</span></Label
                    >
                    <Input
                        v-model="r.name"
                        :placeholder="
                            t('pageDealer.products.typesEditor.namePlaceholder')
                        "
                    />
                    <InputError :message="err('name', idx)" />
                </div>
                <div>
                    <Label class="mb-1.5 text-xs"
                        >{{
                            t('pageDealer.products.typesEditor.priceLabel', {
                                unit: unit ?? 'dona',
                                currency: symbol,
                            })
                        }}
                        <span class="text-destructive">*</span></Label
                    >
                    <Input
                        type="number"
                        step="any"
                        v-model.number="r.price"
                        min="0"
                    />
                    <InputError :message="err('price', idx)" />
                </div>
                <div>
                    <Label class="mb-1.5 text-xs"
                        >{{
                            t('pageDealer.products.typesEditor.packSizeLabel', {
                                unit: unit ?? 'dona',
                            })
                        }}
                        <span class="text-destructive">*</span></Label
                    >
                    <Input
                        type="number"
                        step="0.001"
                        v-model.number="r.pack_size"
                        min="0.001"
                    />
                    <InputError :message="err('pack_size', idx)" />
                </div>
                <div>
                    <Label class="mb-1.5 text-xs">{{
                        t('pageDealer.products.typesEditor.packPriceLabel', {
                            currency: symbol,
                        })
                    }}</Label>
                    <Input
                        type="number"
                        step="0.01"
                        :model-value="r.pack_price ?? ''"
                        @update:model-value="
                            (v) =>
                                onPackPriceInput(
                                    idx,
                                    v === '' ? null : Number(v),
                                )
                        "
                        min="0"
                        :disabled="r.pack_size <= 1"
                    />
                    <InputError :message="err('pack_price', idx)" />
                </div>
                <div>
                    <Label class="mb-1.5 text-xs">{{
                        t('pageDealer.products.typesEditor.costPriceLabel', {
                            unit: unit ?? 'dona',
                            currency: symbol,
                        })
                    }}</Label>
                    <Input
                        type="number"
                        step="any"
                        :model-value="r.cost_price ?? ''"
                        @update:model-value="
                            (v) => (r.cost_price = v === '' ? null : Number(v))
                        "
                        min="0"
                        :placeholder="
                            t(
                                'pageDealer.products.typesEditor.optionalPlaceholder',
                            )
                        "
                    />
                    <InputError :message="err('cost_price', idx)" />
                </div>
                <div>
                    <Label class="mb-1.5 text-xs">{{
                        t(
                            'pageDealer.products.typesEditor.packCostPriceLabel',
                            { currency: symbol },
                        )
                    }}</Label>
                    <Input
                        type="number"
                        step="0.01"
                        :model-value="r.pack_cost_price ?? ''"
                        @update:model-value="
                            (v) =>
                                (r.pack_cost_price =
                                    v === '' ? null : Number(v))
                        "
                        min="0"
                        :disabled="r.pack_size <= 1"
                    />
                    <InputError :message="err('pack_cost_price', idx)" />
                </div>
                <div>
                    <Label class="mb-1.5 text-xs"
                        >{{ t('pageDealer.products.typesEditor.stockLabel') }}
                        <span class="text-destructive">*</span></Label
                    >
                    <Input
                        type="number"
                        step="0.001"
                        v-model.number="r.stock"
                    />
                    <InputError :message="err('stock', idx)" />
                </div>
                <div>
                    <Label class="mb-1.5 text-xs">{{
                        t('pageDealer.products.typesEditor.minStockLabel')
                    }}</Label>
                    <Input
                        type="number"
                        step="0.001"
                        v-model.number="r.min_stock"
                        min="0"
                        placeholder="0"
                    />
                    <InputError :message="err('min_stock', idx)" />
                </div>
                <div class="sm:col-span-2">
                    <label
                        class="flex items-start gap-2 rounded-md border bg-background p-2.5"
                    >
                        <input
                            type="checkbox"
                            v-model="r.bulk_only"
                            :disabled="r.pack_size <= 1"
                            class="mt-0.5 rounded border-input"
                        />
                        <span class="text-xs">
                            <span class="block font-medium">{{
                                t(
                                    'pageDealer.products.typesEditor.bulkOnlyLabel',
                                )
                            }}</span>
                            <span class="text-muted-foreground">{{
                                t(
                                    'pageDealer.products.typesEditor.bulkOnlyHint',
                                )
                            }}</span>
                        </span>
                    </label>
                    <InputError :message="err('bulk_only', idx)" />
                </div>
            </div>

            <div class="mt-4">
                <Label class="mb-1.5 text-xs">{{
                    t('pageDealer.products.typesEditor.imagesLabel')
                }}</Label>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                    <div
                        v-for="(img, i) in r.images"
                        :key="i"
                        class="group relative aspect-square overflow-hidden rounded border"
                    >
                        <img
                            :src="img.url"
                            class="h-full w-full object-cover"
                        />
                        <div
                            v-if="i === 0"
                            class="absolute top-1 left-1 rounded bg-primary px-1.5 py-0.5 text-[10px] font-medium text-primary-foreground"
                        >
                            {{
                                t(
                                    'pageDealer.products.typesEditor.primaryBadge',
                                )
                            }}
                        </div>
                        <div
                            class="absolute inset-x-0 bottom-0 flex items-center justify-center gap-1 bg-gradient-to-t from-black/60 to-transparent p-1"
                        >
                            <button
                                v-if="i > 0"
                                type="button"
                                class="flex h-6 w-6 items-center justify-center rounded-full bg-white text-amber-500 shadow"
                                @click="makePrimary(idx, i)"
                            >
                                <Star class="h-3 w-3" />
                            </button>
                            <button
                                type="button"
                                class="flex h-6 w-6 items-center justify-center rounded-full bg-destructive text-white shadow"
                                @click="removeImage(idx, i)"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                    </div>
                    <label
                        v-if="r.images.length < 10"
                        class="flex aspect-square cursor-pointer flex-col items-center justify-center rounded border-2 border-dashed transition-colors hover:border-primary hover:bg-muted/50"
                    >
                        <ImagePlus
                            class="mb-1 h-5 w-5 text-muted-foreground/50"
                        />
                        <span class="text-[10px] text-muted-foreground">{{
                            t('pageDealer.products.typesEditor.addImageLabel')
                        }}</span>
                        <input
                            type="file"
                            accept="image/*"
                            multiple
                            class="hidden"
                            @change="(e) => onAddImages(idx, e)"
                        />
                    </label>
                </div>
            </div>
        </div>

        <Button type="button" variant="outline" class="w-full" @click="addRow">
            <Plus class="mr-1 h-4 w-4" />
            {{ t('pageDealer.products.typesEditor.addRow') }}
        </Button>
    </div>
</template>
