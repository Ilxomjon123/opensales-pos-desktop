<script setup lang="ts">
import { Check, Minus, Plus, ShoppingCart, Store, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import ImageLightbox from '@/components/ImageLightbox.vue';
import { useMarketplaceCart } from '@/composables/useMarketplaceCart';
import type { MarketplaceProduct } from '@/composables/useMarketplaceCart';
import { formatMoneySum } from '@/lib/format';

const props = defineProps<{
    product: MarketplaceProduct | null;
    open: boolean;
}>();

const emit = defineEmits<{ close: []; goToCart: [] }>();

const { t } = useI18n();
const cart = useMarketplaceCart();

// SSR: Teleport mount'gacha o'chiq.
const isMounted = ref(false);
onMounted(() => (isMounted.value = true));

const imageIdx = ref(0);
const lightboxOpen = ref(false);

watch(
    () => props.open,
    (v) => {
        if (v) {
            imageIdx.value = 0;
        }
    },
);

const images = computed(() => props.product?.images.map((i) => i.url) ?? []);
const packSize = computed(() => Math.max(1, props.product?.pack_size ?? 1));
const hasPacks = computed(
    () =>
        !!props.product &&
        props.product.pack_price !== null &&
        packSize.value > 1,
);
const bulkOnly = computed(
    () => !!props.product?.bulk_only && packSize.value > 1,
);
const outOfStock = computed(() => (props.product?.stock ?? 0) <= 0);

const line = computed(() =>
    props.product ? cart.lineOf(props.product.id) : null,
);
const packQty = computed(() => line.value?.packQty ?? 0);
const looseQty = computed(() => line.value?.looseQty ?? 0);
const inCart = computed(() => !!line.value);

// Tanlangan miqdor bo'yicha jami (paket narxi + dona narxi).
const subtotal = computed(() => {
    const p = props.product;

    if (!p) {
        return 0;
    }

    const ps = Math.max(1, p.pack_size);

    if (packQty.value > 0 && p.pack_price !== null && ps > 1) {
        return packQty.value * p.pack_price + looseQty.value * p.price;
    }

    return (looseQty.value + packQty.value * ps) * p.price;
});

function commit(nextPack: number, nextLoose: number): void {
    if (!props.product) {
        return;
    }

    // Boshqa sotuvchidan birinchi qo'shilganda savatni tozalash.
    if (
        (nextPack > 0 || nextLoose > 0) &&
        cart.wouldSwitchSeller(props.product)
    ) {
        if (!window.confirm(t('pageDealer.marketplace.confirmSwitchSeller'))) {
            return;
        }

        cart.clear();
    }

    cart.setQty(props.product, nextPack, nextLoose);
}

function changePack(delta: number): void {
    commit(packQty.value + delta, looseQty.value);
}

function changeLoose(delta: number): void {
    commit(packQty.value, looseQty.value + delta);
}

function setPackInput(e: Event): void {
    commit(Number((e.target as HTMLInputElement).value) || 0, looseQty.value);
}

function setLooseInput(e: Event): void {
    commit(packQty.value, Number((e.target as HTMLInputElement).value) || 0);
}
</script>

<template>
    <Teleport to="body" :disabled="!isMounted">
        <Transition
            enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open && product"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center"
                @click.self="emit('close')"
            >
                <Transition
                    enter-active-class="transition-transform duration-300 ease-out"
                    leave-active-class="transition-transform duration-200 ease-in"
                    enter-from-class="translate-y-full sm:translate-y-4 sm:opacity-0"
                    leave-to-class="translate-y-full sm:translate-y-4 sm:opacity-0"
                    appear
                >
                    <div
                        class="flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-3xl bg-background sm:max-w-lg sm:rounded-3xl"
                    >
                        <!-- Image -->
                        <div class="relative shrink-0">
                            <button
                                type="button"
                                class="absolute top-3 right-3 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-black/40 text-white backdrop-blur"
                                @click="emit('close')"
                            >
                                <X class="h-5 w-5" />
                            </button>

                            <div
                                class="h-[32vh] max-h-[320px] min-h-[200px] w-full bg-muted sm:h-[38vh]"
                            >
                                <img
                                    v-if="images.length"
                                    :src="images[imageIdx]"
                                    class="h-full w-full cursor-zoom-in object-cover"
                                    @click="lightboxOpen = true"
                                />
                                <div
                                    v-else
                                    class="flex h-full items-center justify-center text-muted-foreground/40"
                                >
                                    <Store class="h-12 w-12" />
                                </div>
                            </div>

                            <div
                                v-if="images.length > 1"
                                class="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-1.5"
                            >
                                <button
                                    v-for="(img, idx) in images"
                                    :key="idx"
                                    type="button"
                                    class="h-1.5 rounded-full transition-all"
                                    :class="
                                        idx === imageIdx
                                            ? 'w-6 bg-white'
                                            : 'w-1.5 bg-white/60'
                                    "
                                    @click="imageIdx = idx"
                                />
                            </div>
                        </div>

                        <!-- Body — min-h-0 flex-1: kontent uzun bo'lsa shu yer
                             scroll bo'ladi, stepperlar siqilib/clip bo'lmaydi -->
                        <div
                            class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto px-3 py-3 sm:px-4"
                        >
                            <!-- Title + price -->
                            <div class="rounded-2xl bg-muted/50 px-4 py-3.5">
                                <h2
                                    class="text-xl leading-tight font-bold tracking-tight"
                                >
                                    {{ product.name }}
                                </h2>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ product.seller.name }}
                                </p>
                                <div class="mt-2 flex items-baseline gap-1.5">
                                    <span
                                        class="text-2xl font-bold text-primary"
                                        >{{
                                            formatMoneySum(
                                                product.price,
                                                product.currency,
                                            )
                                        }}</span
                                    >
                                    <span class="text-xs text-muted-foreground"
                                        >/ {{ product.unit_label }}</span
                                    >
                                </div>
                                <p
                                    v-if="hasPacks"
                                    class="mt-0.5 text-xs text-foreground"
                                >
                                    {{
                                        t('pageDealer.marketplace.blockPrice', {
                                            price: formatMoneySum(
                                                product.pack_price ?? 0,
                                                product.currency,
                                            ),
                                        })
                                    }}
                                </p>
                                <p
                                    class="mt-1 text-[11px]"
                                    :class="
                                        outOfStock
                                            ? 'font-medium text-rose-500'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{
                                        outOfStock
                                            ? t(
                                                  'pageDealer.marketplace.outOfStock',
                                              )
                                            : t(
                                                  'pageDealer.marketplace.stockLabel',
                                                  {
                                                      stock: product.stock,
                                                      unit: product.unit_label,
                                                  },
                                              )
                                    }}
                                </p>
                            </div>

                            <!-- Description -->
                            <div
                                v-if="product.description"
                                class="rounded-2xl bg-muted/50 px-4 py-3.5"
                            >
                                <p
                                    class="text-[11px] font-medium tracking-wider text-muted-foreground uppercase"
                                >
                                    {{
                                        t(
                                            'pageDealer.marketplace.descriptionLabel',
                                        )
                                    }}
                                </p>
                                <p
                                    class="mt-1.5 text-[15px] leading-relaxed whitespace-pre-line"
                                >
                                    {{ product.description }}
                                </p>
                            </div>
                        </div>

                        <!-- CTA — jami + har doim 2 tugma (balandlik o'zgarmaydi) -->
                        <div
                            class="shrink-0 space-y-2.5 border-t bg-background p-3"
                        >
                            <!-- Bulk-only notice -->
                            <div
                                v-if="bulkOnly"
                                class="rounded-2xl bg-amber-500/15 px-4 py-2.5 text-sm font-medium text-amber-700 dark:text-amber-300"
                            >
                                {{ t('pageDealer.marketplace.bulkOnlyNotice') }}
                            </div>

                            <!-- Steppers -->
                            <div
                                v-if="!outOfStock"
                                class="overflow-hidden rounded-2xl bg-muted/50"
                            >
                                <!-- Pack -->
                                <div
                                    v-if="hasPacks"
                                    class="flex items-center justify-between gap-2 px-4 py-3"
                                    :class="!bulkOnly ? 'border-b' : ''"
                                >
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium">
                                            {{
                                                t(
                                                    'pageDealer.marketplace.packLabel',
                                                    {
                                                        size: packSize,
                                                        unit: product.unit_label,
                                                    },
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            {{
                                                formatMoneySum(
                                                    product.pack_price ?? 0,
                                                    product.currency,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div
                                        class="flex shrink-0 items-center gap-2"
                                    >
                                        <button
                                            type="button"
                                            class="flex h-9 w-9 items-center justify-center rounded-full bg-secondary disabled:opacity-40"
                                            :disabled="packQty <= 0"
                                            @click="changePack(-1)"
                                        >
                                            <Minus class="h-4 w-4" />
                                        </button>
                                        <input
                                            :value="packQty"
                                            type="number"
                                            inputmode="numeric"
                                            pattern="[0-9]*"
                                            class="h-9 w-12 rounded-lg border bg-background text-center text-sm font-bold"
                                            @input="setPackInput"
                                        />
                                        <button
                                            type="button"
                                            class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-primary-foreground"
                                            @click="changePack(1)"
                                        >
                                            <Plus class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Unit -->
                                <div
                                    v-if="!bulkOnly"
                                    class="flex items-center justify-between gap-2 px-4 py-3"
                                >
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium">
                                            {{
                                                t(
                                                    'pageDealer.marketplace.unitLabel',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-[11px] text-muted-foreground"
                                        >
                                            {{
                                                formatMoneySum(
                                                    product.price,
                                                    product.currency,
                                                )
                                            }}
                                            /
                                            {{ product.unit_label }}
                                        </p>
                                    </div>
                                    <div
                                        class="flex shrink-0 items-center gap-2"
                                    >
                                        <button
                                            type="button"
                                            class="flex h-9 w-9 items-center justify-center rounded-full bg-secondary disabled:opacity-40"
                                            :disabled="looseQty <= 0"
                                            @click="changeLoose(-1)"
                                        >
                                            <Minus class="h-4 w-4" />
                                        </button>
                                        <input
                                            :value="looseQty"
                                            type="number"
                                            inputmode="numeric"
                                            pattern="[0-9]*"
                                            class="h-9 w-12 rounded-lg border bg-background text-center text-sm font-bold"
                                            @input="setLooseInput"
                                        />
                                        <button
                                            type="button"
                                            class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-primary-foreground"
                                            @click="changeLoose(1)"
                                        >
                                            <Plus class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-muted-foreground">{{
                                    t('pageDealer.marketplace.total')
                                }}</span>
                                <span
                                    class="text-base font-bold text-primary"
                                    >{{
                                        formatMoneySum(
                                            subtotal,
                                            product.currency,
                                        )
                                    }}</span
                                >
                            </div>
                            <div class="grid grid-cols-2 gap-2 lg:grid-cols-1">
                                <button
                                    v-if="inCart"
                                    type="button"
                                    class="rounded-2xl bg-secondary py-3.5 text-sm font-semibold transition-all active:scale-[0.99]"
                                    @click="emit('close')"
                                >
                                    {{
                                        t(
                                            'pageDealer.marketplace.continueShopping',
                                        )
                                    }}
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="flex items-center justify-center gap-2 rounded-2xl bg-secondary py-3.5 text-sm font-semibold transition-all active:scale-[0.99] disabled:opacity-50"
                                    :disabled="outOfStock"
                                    @click="
                                        bulkOnly
                                            ? changePack(1)
                                            : changeLoose(1)
                                    "
                                >
                                    <Check class="h-4 w-4" />
                                    {{ t('pageDealer.marketplace.addToCart') }}
                                </button>
                                <!-- Savatga o'tish faqat mobil: desktopda savat
                                     yon tomonda doim ko'rinadi -->
                                <button
                                    type="button"
                                    class="flex items-center justify-center gap-2 rounded-2xl bg-primary py-3.5 text-sm font-semibold text-primary-foreground transition-all active:scale-[0.99] lg:hidden"
                                    @click="emit('goToCart')"
                                >
                                    <ShoppingCart class="h-4 w-4" />
                                    {{ t('pageDealer.marketplace.goToCart') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>

        <ImageLightbox
            :images="images"
            :initial-idx="imageIdx"
            :open="lightboxOpen"
            @close="lightboxOpen = false"
        />
    </Teleport>
</template>
