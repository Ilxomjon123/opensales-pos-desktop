import { nextTick, watch } from 'vue';

type SyncSource = 'price' | 'pack' | null;

type Row = {
    price: number | null;
    pack_size: number;
    pack_price: number | null;
};

/**
 * Per-row two-way sync between price and pack_price for arrays of rows
 * (e.g. ProductType editor). Each row's source-of-truth is the field the user
 * last edited; the other side is recomputed without overwriting user input.
 */
export function useTypeRowPackPrice<T extends Row>(rows: T[]) {
    const suppress = new WeakMap<T, SyncSource>();

    watch(
        () => rows.map((r) => [r.price, r.pack_size]),
        () => {
            for (const row of rows) {
                if (suppress.get(row) === 'pack') {
                    continue;
                }

                if (row.price === null) {
                    row.pack_price = null;
                    continue;
                }

                const size = Math.max(1, Number(row.pack_size) || 1);
                suppress.set(row, 'price');
                row.pack_price = round(row.price * size, 2);

                nextTick().then(() => {
                    if (suppress.get(row) === 'price') {
                        suppress.set(row, null);
                    }
                });
            }
        },
        { deep: true, immediate: true },
    );

    async function onPackPriceInput(idx: number, v: number | null) {
        const row = rows[idx];

        if (!row) {
            return;
        }

        row.pack_price = v;

        if (v === null) {
            return;
        }

        const size = Math.max(1, Number(row.pack_size) || 1);

        if (size > 0) {
            suppress.set(row, 'pack');
            row.price = round(v / size, 6);
            await nextTick();

            if (suppress.get(row) === 'pack') {
                suppress.set(row, null);
            }
        }
    }

    return { onPackPriceInput };
}

function round(value: number, decimals: number): number {
    const factor = 10 ** decimals;

    return Math.round(value * factor) / factor;
}
