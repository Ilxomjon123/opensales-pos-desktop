<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { AlertTriangle, Check, CheckCircle2, MessageSquareText, Minus, Plus, Receipt, ShoppingBag, Trash2, X, XCircle } from 'lucide-vue-next';
import ProductDetailModal from '../components/ProductDetailModal.vue';
import type {CartItemState, ProductInfo} from '../components/ProductDetailModal.vue';
import { useApi } from '../composables/useApi';
import { useCartStore  } from '../composables/useCartStore';
import type {CartItem} from '../composables/useCartStore';

const emit = defineEmits<{ orderPlaced: []; goToCatalog: [] }>();
const api = useApi();
const cartStore = useCartStore();
const { t } = useI18n();

const items = computed(() => cartStore.state.items);
const total = computed(() => cartStore.state.total);
const minOrderAmount = computed(() => cartStore.state.minOrderAmount);
const belowMinimum = computed(() => minOrderAmount.value > 0 && total.value < minOrderAmount.value);
const amountToReachMinimum = computed(() => Math.max(0, minOrderAmount.value - total.value));
const loading = computed(() => !cartStore.state.loaded);
const confirming = ref(false);
const note = ref('');
const noteInput = ref<HTMLTextAreaElement | null>(null);
const noteFocused = ref(false);
const NOTE_MAX = 500;

// Matn ko'paysa textarea bo'yi o'sadi (auto-grow) va caret
// sticky total bar ostida qolmasligi uchun ko'rinadigan joyga suriladi.
function autoGrow() {
    const el = noteInput.value;
    if (!el) {
        return;
    }
    el.style.height = 'auto';
    el.style.height = `${el.scrollHeight}px`;
    // scroll-mb hisobga olinadi — pastki qator total bar ustida qoladi.
    el.scrollIntoView({ block: 'nearest' });
}

// Mobil klaviatura ochilganda input ko'rinadigan joyga suriladi.
// Telegram WebView klaviaturasi ~300ms ochiladi — shuning uchun kechikish.
function onNoteFocus() {
    noteFocused.value = true;
    autoGrow();
    setTimeout(() => {
        noteInput.value?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }, 300);
}
const message = ref('');
const messageType = ref<'success' | 'error' | ''>('');

const selectedProduct = ref<ProductInfo | null>(null);
const selectedCartItem = ref<CartItemState | null>(null);
const modalOpen = ref(false);
const loadingProduct = ref(false);

type SuccessOrder = {
    id: number;
    total: number;
    itemsCount: number;
    status: string;
};

const successOrder = ref<SuccessOrder | null>(null);

async function removeItem(item: CartItem) {
    await cartStore.remove(item.product_id, item.product_type_id ?? null);
}

function packSizeOf(item: CartItem): number {
    return Math.max(1, item.pack_size ?? 1);
}

function looseQtyOf(item: CartItem): number {
    return Math.max(0, item.qty - (item.pack_qty ?? 0) * packSizeOf(item));
}

function isBulkOnly(item: CartItem): boolean {
    return Boolean(item.bulk_only) && packSizeOf(item) > 1;
}

function changePackQty(item: CartItem, delta: number) {
    const nextPack = Math.max(0, (item.pack_qty ?? 0) + delta);
    const nextTotal = nextPack * packSizeOf(item) + looseQtyOf(item);
    message.value = '';
    cartStore.updateQtyOptimistic(item.product_id, {
        qty: nextTotal,
        pack_qty: nextPack,
        product_type_id: item.product_type_id ?? null,
    });
}

function changeUnitQty(item: CartItem, delta: number) {
    const nextLoose = Math.max(0, looseQtyOf(item) + delta);
    const nextTotal = (item.pack_qty ?? 0) * packSizeOf(item) + nextLoose;
    message.value = '';
    cartStore.updateQtyOptimistic(item.product_id, {
        qty: nextTotal,
        pack_qty: item.pack_qty ?? 0,
        product_type_id: item.product_type_id ?? null,
    });
}

async function clearCart() {
    if (!confirm(t('miniappPages.cart.clearConfirm'))) {
        return;
    }

    await cartStore.clear();
}

async function placeOrder() {
    if (belowMinimum.value) {
        return;
    }

    confirming.value = true;
    message.value = '';
    messageType.value = '';

    try {
        const itemsCountSnapshot = items.value.length;
        const res: any = await api.post('/cart/confirm', {
            note: note.value || null,
        });

        successOrder.value = {
            id: Number(res.order_id),
            total: Number(res.total),
            itemsCount: itemsCountSnapshot,
            status: String(res.status ?? ''),
        };
        note.value = '';
        cartStore.clearLocal();
    } catch (e: any) {
        message.value = e.message;
        messageType.value = 'error';
    }

    confirming.value = false;
}

function viewOrders() {
    successOrder.value = null;
    emit('orderPlaced');
}

function continueShopping() {
    successOrder.value = null;
    emit('goToCatalog');
}

async function openProduct(item: CartItem) {
    selectedProduct.value = {
        id: item.product_id,
        name: item.product_name,
        code: null,
        description: null,
        price: item.price,
        original_price: null,
        discount_percent: 0,
        unit: item.unit ?? 'dona',
        image_url: null,
        images: [],
        pack_size: item.pack_size ?? 1,
        pack_price: item.price * (item.pack_size ?? 1),
        bulk_only: item.bulk_only ?? false,
        has_types: false,
        types: [],
    };
    selectedCartItem.value = {
        qty: item.qty,
        pack_qty: item.pack_qty ?? null,
        product_type_id: item.product_type_id ?? null,
    };
    modalOpen.value = true;

    // To'liq ma'lumotni foncha yuklaymiz
    if (loadingProduct.value) {
        return;
    }

    loadingProduct.value = true;

    try {
        const res: any = await api.get(`/products/${item.product_id}`);
        const fullProduct = res.data ?? res;

        if (selectedProduct.value?.id === item.product_id) {
            selectedProduct.value = fullProduct;
        }
    } catch { /* */ }

    loadingProduct.value = false;
}

function closeModal() {
    modalOpen.value = false;
    selectedCartItem.value = null;
}

function formatMoney(n: number): string {
    return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}
</script>

<template>
    <div class="mx-auto w-full max-w-3xl p-3 sm:p-4">
        <div class="mb-4 flex items-center justify-between gap-2">
            <div>
                <h2 class="text-xl font-bold tracking-tight">{{ t('miniappPages.cart.title') }}</h2>
                <p v-if="!loading && items.length > 0" class="text-xs text-muted-foreground">
                    {{ t('miniappPages.cart.itemsCount', { count: items.length }) }}
                </p>
            </div>
            <button
                v-if="!loading && items.length > 0"
                type="button"
                class="flex shrink-0 items-center gap-1.5 rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-600 transition-colors hover:bg-rose-50 dark:border-rose-900 dark:text-rose-400 dark:hover:bg-rose-950/30"
                @click="clearCart"
            >
                <Trash2 class="h-3.5 w-3.5" />
                {{ t('miniappPages.cart.clear') }}
            </button>
        </div>

        <div v-if="loading" class="space-y-2">
            <div
                v-for="n in 3"
                :key="n"
                class="flex items-center justify-between rounded-lg border p-3"
            >
                <div class="flex-1 space-y-2">
                    <div class="h-4 w-1/2 animate-pulse rounded bg-muted" />
                    <div class="h-3 w-1/3 animate-pulse rounded bg-muted" />
                </div>
                <div class="h-8 w-8 animate-pulse rounded bg-muted" />
            </div>
            <div class="mt-4 h-16 animate-pulse rounded-lg bg-muted" />
        </div>

        <template v-else-if="items.length > 0">
            <!-- Items -->
            <div class="space-y-2.5 pb-4">
                <div
                    v-for="item in items"
                    :key="`${item.product_id}:${item.product_type_id ?? 0}`"
                    class="rounded-2xl border bg-card p-3 transition-shadow hover:shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <button
                            type="button"
                            class="min-w-0 flex-1 text-left"
                            @click="openProduct(item)"
                        >
                            <p class="text-sm font-semibold leading-snug">
                                {{ item.product_name }}<span v-if="item.product_type_name" class="font-normal text-muted-foreground"> · {{ item.product_type_name }}</span>
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                <template v-if="item.pack_qty && item.pack_size && item.pack_size > 1 && looseQtyOf(item) > 0">
                                    {{ t('miniappPages.cart.blockPlusUnits', { packs: item.pack_qty, total: item.pack_qty * item.pack_size, loose: looseQtyOf(item), unit: item.unit, qty: item.qty }) }}
                                </template>
                                <template v-else-if="item.pack_qty && item.pack_size && item.pack_size > 1">
                                    {{ t('miniappPages.cart.blockTimes', { packs: item.pack_qty, size: item.pack_size, unit: item.unit, qty: item.qty }) }}
                                </template>
                                <template v-else>
                                    {{ t('miniappPages.cart.qtyOnly', { qty: item.qty, unit: item.unit ?? '' }) }}
                                </template>
                            </p>
                            <p class="mt-1.5 text-base font-bold tracking-tight">{{ formatMoney(item.price * item.qty) }}&nbsp;<span class="text-xs font-normal text-muted-foreground">so'm</span></p>
                        </button>
                        <button
                            type="button"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/30 dark:hover:text-rose-400"
                            :aria-label="t('miniappPages.cart.removeAria')"
                            @click.stop="removeItem(item)"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="mt-3 grid gap-2" :class="(item.pack_size ?? 1) > 1 && !isBulkOnly(item) ? 'grid-cols-2' : 'grid-cols-1'">
                        <!-- Blok controls -->
                        <div v-if="(item.pack_size ?? 1) > 1" class="flex items-center justify-between rounded-xl border bg-muted/40 px-2.5 py-1">
                            <span class="text-xs font-medium text-muted-foreground">{{ t('miniappPages.cart.blockLabel') }}</span>
                            <div class="flex items-center">
                                <button
                                    type="button"
                                    :disabled="(item.pack_qty ?? 0) <= 0"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg transition-opacity disabled:opacity-30"
                                    @click.stop="changePackQty(item, -1)"
                                >
                                    <Minus class="h-4 w-4" />
                                </button>
                                <span class="min-w-[32px] text-center text-sm font-bold tabular-nums">{{ item.pack_qty ?? 0 }}</span>
                                <button
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg transition-opacity"
                                    @click.stop="changePackQty(item, 1)"
                                >
                                    <Plus class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <!-- Unit controls — bulk_only mahsulotlarda yashiriladi -->
                        <div v-if="!isBulkOnly(item)" class="flex items-center justify-between rounded-xl border bg-muted/40 px-2.5 py-1">
                            <span class="text-xs font-medium text-muted-foreground">{{ item.unit ?? 'dona' }}</span>
                            <div class="flex items-center">
                                <button
                                    type="button"
                                    :disabled="looseQtyOf(item) <= 0"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg transition-opacity disabled:opacity-30"
                                    @click.stop="changeUnitQty(item, -1)"
                                >
                                    <Minus class="h-4 w-4" />
                                </button>
                                <span class="min-w-[32px] text-center text-sm font-bold tabular-nums">{{ looseQtyOf(item) }}</span>
                                <button
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg transition-opacity"
                                    @click.stop="changeUnitQty(item, 1)"
                                >
                                    <Plus class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Note -->
            <div
                class="mb-4 rounded-2xl border bg-card p-3 transition-shadow"
                :class="noteFocused ? 'border-primary shadow-sm ring-2 ring-primary/20' : ''"
            >
                <div class="mb-2 flex items-center justify-between">
                    <label
                        for="cart-note"
                        class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                    >
                        <MessageSquareText class="h-3.5 w-3.5" />
                        {{ t('miniappPages.cart.noteLabel') }}
                    </label>
                    <span v-if="note.length > 0" class="text-[11px] tabular-nums text-muted-foreground">
                        {{ note.length }}/{{ NOTE_MAX }}
                    </span>
                </div>
                <textarea
                    id="cart-note"
                    ref="noteInput"
                    v-model="note"
                    rows="2"
                    :maxlength="NOTE_MAX"
                    :placeholder="t('miniappPages.cart.notePlaceholder')"
                    class="max-h-48 min-h-[2.5rem] w-full scroll-mb-72 resize-none overflow-y-auto bg-transparent text-sm leading-relaxed outline-none placeholder:text-muted-foreground/60"
                    @focus="onNoteFocus"
                    @blur="noteFocused = false"
                    @input="autoGrow"
                />
            </div>

            <p
                v-if="message"
                class="mb-4 flex items-start gap-2 rounded-xl border px-3 py-2.5 text-sm"
                :class="messageType === 'success'
                    ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                    : 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300'"
            >
                <CheckCircle2 v-if="messageType === 'success'" class="h-4 w-4 shrink-0" />
                <XCircle v-else class="h-4 w-4 shrink-0" />
                <span>{{ message }}</span>
            </p>

            <!-- Sticky bottom action bar — floating nav ustida turadi -->
            <div class="sticky bottom-[calc(env(safe-area-inset-bottom)+76px)] -mx-3 rounded-2xl border bg-background/95 p-3 shadow-[0_-2px_16px_-4px_rgba(15,15,35,0.12)] backdrop-blur sm:-mx-4 sm:p-4">
                <div class="mb-3 flex items-center justify-between rounded-xl bg-muted/60 px-3 py-2.5">
                    <span class="text-sm font-medium text-muted-foreground">{{ t('miniappPages.cart.totalLabel') }}</span>
                    <span class="text-xl font-bold tracking-tight">{{ formatMoney(total) }}&nbsp;<span class="text-sm font-medium text-muted-foreground">so'm</span></span>
                </div>

                <div
                    v-if="minOrderAmount > 0"
                    class="mb-3 flex items-center justify-between gap-2 rounded-xl border px-3 py-2 text-xs"
                    :class="belowMinimum
                        ? 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                        : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'"
                >
                    <span class="flex items-center gap-1.5 font-medium">
                        <AlertTriangle v-if="belowMinimum" class="h-3.5 w-3.5" />
                        <Check v-else class="h-3.5 w-3.5" />
                        <span>{{ t('miniappPages.cart.minOrderLabel', { amount: formatMoney(minOrderAmount) }) }}</span>
                    </span>
                    <span v-if="belowMinimum" class="font-semibold">
                        {{ t('miniappPages.cart.moreNeeded', { amount: formatMoney(amountToReachMinimum) }) }}
                    </span>
                </div>

                <button
                    type="button"
                    :disabled="confirming || belowMinimum"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-3.5 text-sm font-semibold text-primary-foreground shadow-sm transition-all hover:shadow-md active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:shadow-sm"
                    @click="placeOrder"
                >
                    <template v-if="confirming">
                        <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-primary-foreground/40 border-t-primary-foreground" />
                        {{ t('miniappPages.cart.submitting') }}
                    </template>
                    <template v-else-if="belowMinimum">
                        <AlertTriangle class="h-4 w-4" />
                        {{ t('miniappPages.cart.belowMin') }}
                    </template>
                    <template v-else>
                        <Check class="h-4 w-4" />
                        {{ t('miniappPages.cart.confirmOrder') }}
                    </template>
                </button>
            </div>
        </template>

        <!-- Empty -->
        <div v-else class="flex flex-col items-center py-20 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                <ShoppingBag class="h-7 w-7" />
            </div>
            <p class="mt-4 text-base font-semibold">{{ t('miniappPages.cart.emptyTitle') }}</p>
            <p class="mt-1 text-sm text-muted-foreground">{{ t('miniappPages.cart.emptyText') }}</p>
            <p v-if="minOrderAmount > 0" class="mt-4 inline-flex items-center gap-1.5 rounded-full border border-amber-500/40 bg-amber-500/10 px-3 py-1.5 text-xs font-medium text-amber-700 dark:text-amber-300">
                <AlertTriangle class="h-3.5 w-3.5" />
                {{ t('miniappPages.cart.minOrderHint', { amount: formatMoney(minOrderAmount) }) }}
            </p>
        </div>

        <ProductDetailModal
            :product="selectedProduct"
            :open="modalOpen"
            :cart-item="selectedCartItem"
            :allow-go-to-cart="false"
            @close="closeModal"
        />

        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="successOrder"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                    @click.self="continueShopping"
                >
                    <Transition
                        appear
                        enter-active-class="transition duration-300 ease-out"
                        enter-from-class="opacity-0 scale-90 translate-y-4"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                    >
                        <div
                            class="w-full max-w-sm overflow-hidden rounded-3xl border bg-card shadow-2xl"
                        >
                            <div class="flex flex-col items-center px-6 pt-8 pb-4 text-center">
                                <div class="relative flex h-20 w-20 items-center justify-center">
                                    <span
                                        class="absolute inset-0 animate-ping rounded-full bg-emerald-500/30"
                                    />
                                    <span
                                        class="relative flex h-20 w-20 items-center justify-center rounded-full bg-emerald-500 text-white shadow-lg shadow-emerald-500/30"
                                    >
                                        <CheckCircle2 class="h-10 w-10" :stroke-width="2.5" />
                                    </span>
                                </div>
                                <h3 class="mt-5 text-lg font-bold tracking-tight">
                                    {{ t('miniappPages.cart.successTitle') }}
                                </h3>
                                <p class="mt-1.5 text-sm text-muted-foreground">
                                    {{ t('miniappPages.cart.successSubtitle') }}
                                </p>
                            </div>

                            <div class="mx-6 space-y-2 rounded-2xl border bg-muted/40 p-3.5">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">{{ t('miniappPages.cart.successOrderNumber') }}</span>
                                    <span class="font-bold tabular-nums">#{{ successOrder.id }}</span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">{{ t('miniappPages.cart.successItemsCount') }}</span>
                                    <span class="font-semibold">{{ t('miniappPages.cart.successItemsValue', { count: successOrder.itemsCount }) }}</span>
                                </div>
                                <div v-if="successOrder.status" class="flex items-center justify-between text-sm">
                                    <span class="text-muted-foreground">{{ t('miniappPages.cart.successStatus') }}</span>
                                    <span class="rounded-full bg-amber-500/15 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:text-amber-300">
                                        {{ successOrder.status }}
                                    </span>
                                </div>
                                <div class="border-t pt-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-muted-foreground">{{ t('miniappPages.cart.successTotal') }}</span>
                                        <span class="text-lg font-bold tracking-tight">
                                            {{ formatMoney(successOrder.total) }}&nbsp;<span class="text-xs font-normal text-muted-foreground">so'm</span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col gap-2 p-6 pt-4">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-3 text-sm font-semibold text-primary-foreground shadow-sm transition-all hover:shadow-md active:scale-[0.99]"
                                    @click="viewOrders"
                                >
                                    <Receipt class="h-4 w-4" />
                                    {{ t('miniappPages.cart.successViewOrders') }}
                                </button>
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl border bg-background py-3 text-sm font-semibold text-foreground transition-colors hover:bg-muted active:scale-[0.99]"
                                    @click="continueShopping"
                                >
                                    {{ t('miniappPages.cart.successContinue') }}
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
