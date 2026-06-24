import { nextTick, watch } from 'vue';
import type { Ref } from 'vue';

type NumRef = Ref<number> | Ref<number | null>;

type Options = {
    price: NumRef;
    packSize: NumRef;
    packPrice: NumRef;
};

/**
 * Two-way sync between per-unit price and per-pack price.
 *
 * - User edits price/packSize → packPrice = round(price × packSize, 2).
 * - User edits packPrice (via onPackPriceInput) → price = round(packPrice / packSize, 6).
 *
 * Both refs are caller-owned (e.g. form fields) and the values reach the backend
 * verbatim, so the user-typed amount is preserved end-to-end.
 */
export function usePackPrice({ price, packSize, packPrice }: Options) {
    let suppress: 'price' | 'pack' | null = null;

    watch(
        [price, packSize],
        ([p, s]) => {
            if (suppress === 'pack') {
                return;
            }

            if (p === null) {
                packPrice.value = null;
                return;
            }

            const size = Math.max(1, Number(s) || 1);
            suppress = 'price';
            packPrice.value = round(p * size, 2);
            nextTick().then(() => {
                if (suppress === 'price') {
                    suppress = null;
                }
            });
        },
        { immediate: true },
    );

    async function onPackPriceInput(v: number | null) {
        packPrice.value = v;

        if (v === null) {
            return;
        }

        const size = Math.max(1, Number(packSize.value) || 1);

        if (size > 0) {
            suppress = 'pack';
            price.value = round(v / size, 6);
            await nextTick();

            if (suppress === 'pack') {
                suppress = null;
            }
        }
    }

    return { onPackPriceInput };
}

function round(value: number, decimals: number): number {
    const factor = 10 ** decimals;

    return Math.round(value * factor) / factor;
}
