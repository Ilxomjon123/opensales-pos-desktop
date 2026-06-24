<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Minus, Plus, Send, ShoppingCart, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Card } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import {
    lineSubtotal,
    totalUnits,
    useMarketplaceCart,
} from '@/composables/useMarketplaceCart';
import { formatMoneySum } from '@/lib/format';

defineProps<{ showClose?: boolean }>();
const emit = defineEmits<{ close: [] }>();

const { t } = useI18n();
const cart = useMarketplaceCart();

const note = ref('');
const submitting = ref(false);

function submitOrder() {
    if (cart.isEmpty.value) {
        return;
    }

    submitting.value = true;
    const form = useForm({
        note: note.value || null,
        items: cart.orderItems(),
    });
    form.post('/dealer/marketplace/orders', {
        preserveScroll: true,
        onSuccess: () => {
            cart.clear();
            note.value = '';
            emit('close');
        },
        onError: (errors) => {
            const first = Object.values(errors)[0];

            import('vue-sonner').then(({ toast }) =>
                toast.error(first ?? t('pageDealer.marketplace.orderError')),
            );
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
}

function clearCart() {
    if (cart.isEmpty.value) {
        return;
    }

    if (window.confirm(t('pageDealer.marketplace.confirmClearCart'))) {
        cart.clear();
        note.value = '';
    }
}
</script>

<template>
    <Card class="flex min-h-0 flex-col overflow-hidden">
        <div class="flex shrink-0 items-center gap-2 border-b px-4 py-2.5">
            <ShoppingCart class="h-4 w-4 text-primary" />
            <h3 class="text-sm font-semibold">
                {{ t('pageDealer.marketplace.cart') }}
            </h3>
            <div class="ml-auto flex items-center gap-2">
                <span
                    v-if="cart.seller.value"
                    class="max-w-[120px] truncate text-[11px] text-muted-foreground"
                    >{{ cart.seller.value.name }}</span
                >
                <button
                    v-if="!cart.isEmpty.value"
                    type="button"
                    class="flex h-6 w-6 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                    :title="t('pageDealer.marketplace.clearCart')"
                    @click="clearCart"
                >
                    <Trash2 class="h-3.5 w-3.5" />
                </button>
                <button
                    v-if="showClose"
                    type="button"
                    class="flex h-6 w-6 items-center justify-center rounded text-muted-foreground hover:bg-muted"
                    @click="emit('close')"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>
        </div>

        <div class="flex min-h-0 flex-1 flex-col p-3">
            <div
                v-if="cart.isEmpty.value"
                class="py-8 text-center text-xs text-muted-foreground"
            >
                {{ t('pageDealer.marketplace.cartEmpty') }}
            </div>
            <div v-else class="flex min-h-0 flex-1 flex-col gap-2.5">
                <div class="min-h-0 flex-1 space-y-2.5 overflow-y-auto">
                    <div
                        v-for="l in cart.lines.value"
                        :key="l.product.id"
                        class="rounded-xl border p-2.5"
                    >
                        <div class="flex items-start gap-2">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-semibold">
                                    {{ l.product.name }}
                                </p>
                                <p class="text-[11px] text-muted-foreground">
                                    {{ totalUnits(l) }}
                                    {{ l.product.unit_label }}
                                </p>
                            </div>
                            <p
                                class="shrink-0 text-sm font-bold tracking-tight"
                            >
                                {{
                                    formatMoneySum(
                                        lineSubtotal(l),
                                        l.product.currency,
                                    )
                                }}
                            </p>
                            <button
                                type="button"
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded text-muted-foreground hover:text-destructive"
                                @click="cart.remove(l.product.id)"
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </div>
                        <div
                            class="mt-2 grid gap-1.5"
                            :class="
                                l.product.pack_price &&
                                l.product.pack_size > 1 &&
                                !l.product.bulk_only
                                    ? 'grid-cols-2'
                                    : 'grid-cols-1'
                            "
                        >
                            <div
                                v-if="
                                    l.product.pack_price &&
                                    l.product.pack_size > 1
                                "
                                class="flex items-center justify-between rounded-lg bg-muted/40 px-2 py-1"
                            >
                                <span
                                    class="text-[10px] font-medium text-muted-foreground"
                                    >{{
                                        t('pageDealer.marketplace.packShort')
                                    }}</span
                                >
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="flex h-7 w-7 items-center justify-center rounded hover:bg-muted"
                                        @click="
                                            cart.setQty(
                                                l.product,
                                                l.packQty - 1,
                                                l.looseQty,
                                            )
                                        "
                                    >
                                        <Minus class="h-3 w-3" />
                                    </button>
                                    <span
                                        class="min-w-[20px] text-center text-xs font-bold tabular-nums"
                                        >{{ l.packQty }}</span
                                    >
                                    <button
                                        type="button"
                                        class="flex h-7 w-7 items-center justify-center rounded hover:bg-muted"
                                        @click="
                                            cart.setQty(
                                                l.product,
                                                l.packQty + 1,
                                                l.looseQty,
                                            )
                                        "
                                    >
                                        <Plus class="h-3 w-3" />
                                    </button>
                                </div>
                            </div>
                            <div
                                v-if="!l.product.bulk_only"
                                class="flex items-center justify-between rounded-lg bg-muted/40 px-2 py-1"
                            >
                                <span
                                    class="text-[10px] font-medium text-muted-foreground"
                                    >{{
                                        t('pageDealer.marketplace.unitShort')
                                    }}</span
                                >
                                <div class="flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="flex h-7 w-7 items-center justify-center rounded hover:bg-muted"
                                        @click="
                                            cart.setQty(
                                                l.product,
                                                l.packQty,
                                                l.looseQty - 1,
                                            )
                                        "
                                    >
                                        <Minus class="h-3 w-3" />
                                    </button>
                                    <span
                                        class="min-w-[20px] text-center text-xs font-bold tabular-nums"
                                        >{{ l.looseQty }}</span
                                    >
                                    <button
                                        type="button"
                                        class="flex h-7 w-7 items-center justify-center rounded hover:bg-muted"
                                        @click="
                                            cart.setQty(
                                                l.product,
                                                l.packQty,
                                                l.looseQty + 1,
                                            )
                                        "
                                    >
                                        <Plus class="h-3 w-3" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="shrink-0 space-y-2.5">
                    <textarea
                        v-model="note"
                        rows="2"
                        :placeholder="
                            t('pageDealer.marketplace.notePlaceholder')
                        "
                        class="w-full rounded-md border px-2 py-1.5 text-xs"
                    />

                    <div
                        class="flex items-center justify-between border-t pt-2 text-sm font-bold"
                    >
                        <span>{{ t('pageDealer.marketplace.total') }}</span>
                        <span class="text-primary">{{
                            formatMoneySum(
                                cart.total.value,
                                cart.lines.value[0]?.product.currency,
                            )
                        }}</span>
                    </div>

                    <button
                        type="button"
                        class="flex w-full items-center justify-center gap-2 rounded-md bg-primary py-2.5 text-sm font-semibold text-primary-foreground transition-all active:scale-[0.99] disabled:opacity-50"
                        :disabled="submitting"
                        @click="submitOrder"
                    >
                        <Spinner v-if="submitting" class="mr-1" />
                        <Send v-else class="h-4 w-4" />
                        {{ t('pageDealer.marketplace.placeOrder') }}
                    </button>
                </div>
            </div>
        </div>
    </Card>
</template>
