import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatMoney } from '@/lib/format';

export type CurrencyShare = {
    code: string;
    symbol: string;
};

const FALLBACK: CurrencyShare = { code: 'UZS', symbol: "so'm" };

/**
 * Diller darajasidagi valyuta (Inertia `currency` shared prop). Pul summasini
 * joriy diller valyutasi belgisi bilan formatlaydi.
 */
export function useCurrency() {
    const page = usePage();

    const currency = computed<CurrencyShare>(() => page.props.currency ?? FALLBACK);

    const symbol = computed<string>(() => currency.value.symbol);
    const code = computed<string>(() => currency.value.code);

    /** "1 200 000" — belgisiz, guruhlangan. */
    function format(amount: number): string {
        return formatMoney(amount);
    }

    /** "1 200 000 so'm" / "1 200 000 ₽" — belgi bilan. */
    function formatWithSymbol(amount: number): string {
        return `${formatMoney(amount)} ${symbol.value}`;
    }

    return { currency, symbol, code, format, formatWithSymbol };
}
