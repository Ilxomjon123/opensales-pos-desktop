<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCurrency } from '@/composables/useCurrency';
import { useUnitLabel } from '@/composables/useUnitLabel';
import type { Product } from '@/types';

const props = defineProps<{
    open: boolean;
    productId: number | null;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const { t } = useI18n();
const unitLabel = useUnitLabel();
const { formatWithSymbol } = useCurrency();

const product = ref<Product | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);
const activeImage = ref<string | null>(null);

watch(
    () => [props.open, props.productId] as const,
    async ([open, id]) => {
        if (!open || !id) {
            return;
        }

        product.value = null;
        error.value = null;
        activeImage.value = null;
        loading.value = true;

        try {
            const res = await fetch(`/dealer/products/${id}/json`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }

            const json = await res.json();
            product.value = json.data ?? json;
            activeImage.value = product.value?.image_url ?? null;
        } catch (e) {
            error.value =
                e instanceof Error
                    ? e.message
                    : t('pageDealer.orders.loadError');
        } finally {
            loading.value = false;
        }
    },
    { immediate: true },
);
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent
            class="flex max-h-[calc(100dvh-2rem)] flex-col gap-3 p-4 sm:max-w-2xl sm:gap-4 sm:p-6"
            @open-auto-focus="(e: Event) => e.preventDefault()"
        >
            <DialogHeader>
                <DialogTitle class="pr-8 text-base sm:text-lg">
                    {{ t('pageDealer.orders.productDetails') }}
                </DialogTitle>
            </DialogHeader>

            <div class="-mx-4 flex-1 overflow-y-auto px-4 sm:-mx-6 sm:px-6">
                <div
                    v-if="loading"
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    {{ t('pageDealer.orders.loading') }}
                </div>

                <p
                    v-else-if="error"
                    class="rounded bg-destructive/10 px-3 py-2 text-sm text-destructive"
                >
                    {{ error }}
                </p>

                <div v-else-if="product" class="space-y-4">
                    <div v-if="product.images.length > 0" class="space-y-2">
                        <div
                            class="overflow-hidden rounded-lg border bg-muted/20"
                        >
                            <img
                                :src="activeImage ?? product.image_url ?? ''"
                                :alt="product.name"
                                class="h-64 w-full object-contain"
                            />
                        </div>
                        <div
                            v-if="product.images.length > 1"
                            class="flex gap-2 overflow-x-auto"
                        >
                            <button
                                v-for="img in product.images"
                                :key="img.id"
                                type="button"
                                class="h-16 w-16 shrink-0 overflow-hidden rounded border-2"
                                :class="
                                    activeImage === img.url
                                        ? 'border-primary'
                                        : 'border-transparent'
                                "
                                @click="activeImage = img.url"
                            >
                                <img
                                    :src="img.url"
                                    :alt="product.name"
                                    class="h-full w-full object-cover"
                                />
                            </button>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-lg font-semibold">
                                {{ product.name }}
                            </h3>
                            <Badge
                                v-if="!product.is_active"
                                variant="secondary"
                                >{{ t('pageDealer.orders.inactive') }}</Badge
                            >
                            <Badge v-if="product.bulk_only" variant="outline">{{
                                t('pageDealer.orders.bulkOnly')
                            }}</Badge>
                            <Badge
                                v-if="product.is_low_stock"
                                variant="destructive"
                                >{{ t('pageDealer.orders.lowStock') }}</Badge
                            >
                        </div>
                        <p
                            v-if="product.category"
                            class="text-sm text-muted-foreground"
                        >
                            {{ product.category.name }}
                        </p>
                    </div>

                    <p
                        v-if="product.description"
                        class="text-sm whitespace-pre-line text-muted-foreground"
                    >
                        {{ product.description }}
                    </p>

                    <div
                        class="grid grid-cols-2 gap-3 rounded-lg border bg-muted/20 p-3 text-sm sm:grid-cols-3"
                    >
                        <div>
                            <p class="text-xs text-muted-foreground">
                                {{ t('pageDealer.orders.price') }}
                            </p>
                            <p class="font-mono font-medium">
                                {{ formatWithSymbol(product.price) }}
                            </p>
                            <p
                                v-if="product.original_price"
                                class="font-mono text-xs text-muted-foreground line-through"
                            >
                                {{ formatWithSymbol(product.original_price) }}
                            </p>
                        </div>
                        <div v-if="product.pack_size > 1">
                            <p class="text-xs text-muted-foreground">
                                {{ t('pageDealer.orders.pack') }}
                            </p>
                            <p class="font-medium">
                                {{ product.pack_size }}
                                {{ unitLabel(product.unit) }}
                            </p>
                            <p class="font-mono text-xs text-muted-foreground">
                                {{
                                    t('pageDealer.orders.perBlock', {
                                        amount: formatWithSymbol(
                                            product.pack_price,
                                        ),
                                    })
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                {{ t('pageDealer.orders.unit') }}
                            </p>
                            <p class="font-medium">{{ product.unit_label }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                {{ t('pageDealer.orders.stock') }}
                            </p>
                            <p class="font-medium">
                                {{
                                    product.has_types
                                        ? (product.total_stock ?? 0)
                                        : product.stock
                                }}
                                <span class="text-xs text-muted-foreground">{{
                                    unitLabel(product.unit)
                                }}</span>
                            </p>
                            <p
                                v-if="product.pack_size > 1"
                                class="text-xs text-muted-foreground"
                            >
                                {{
                                    t('pageDealer.orders.approxPacks', {
                                        packs: product.stock_packs,
                                    })
                                }}
                            </p>
                        </div>
                        <div
                            v-if="
                                product.min_stock !== null &&
                                product.min_stock > 0
                            "
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ t('pageDealer.orders.minStock') }}
                            </p>
                            <p class="font-medium">{{ product.min_stock }}</p>
                        </div>
                        <div
                            v-if="
                                product.discount_percent &&
                                product.discount_percent > 0
                            "
                        >
                            <p class="text-xs text-muted-foreground">
                                {{ t('pageDealer.orders.discount') }}
                            </p>
                            <p class="font-medium text-rose-600">
                                {{ product.discount_percent }}%
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="
                            product.has_types &&
                            (product.types?.length ?? 0) > 0
                        "
                        class="space-y-2"
                    >
                        <h4 class="text-sm font-medium">
                            {{
                                t('pageDealer.orders.typesCount', {
                                    count: product.types?.length,
                                })
                            }}
                        </h4>
                        <div class="overflow-hidden rounded-lg border">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b bg-muted/40 text-xs">
                                    <tr>
                                        <th class="px-3 py-2 font-medium">
                                            {{
                                                t('pageDealer.orders.typeName')
                                            }}
                                        </th>
                                        <th
                                            class="px-3 py-2 text-right font-medium"
                                        >
                                            {{ t('pageDealer.orders.price') }}
                                        </th>
                                        <th
                                            class="px-3 py-2 text-right font-medium"
                                        >
                                            {{ t('pageDealer.orders.stock') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr
                                        v-for="tx in product.types"
                                        :key="tx.id"
                                        :class="
                                            !tx.is_active ? 'opacity-50' : ''
                                        "
                                    >
                                        <td class="px-3 py-2">
                                            {{ tx.name }}
                                            <span
                                                v-if="tx.code"
                                                class="text-xs text-muted-foreground"
                                                >— {{ tx.code }}</span
                                            >
                                        </td>
                                        <td
                                            class="px-3 py-2 text-right font-mono"
                                        >
                                            {{ formatWithSymbol(tx.price) }}
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            {{ tx.stock }}
                                            <span
                                                class="text-xs text-muted-foreground"
                                                >{{
                                                    unitLabel(product.unit)
                                                }}</span
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
