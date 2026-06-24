import { ref } from 'vue';

export function formatMoney(amount: number): string {
    return String(Math.round(amount)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

// Joriy diller valyutasi belgisi — REAKTIV (Vue ref). Yagona manba: Inertia
// `currency` shared prop'i (AppSidebarLayout watchEffect orqali sync). Reaktiv
// bo'lgani uchun `formatMoneySum`'ni template'da chaqirgan har bir komponent
// belgi o'zgarganda qayta render bo'ladi — refresh/impersonation'da "so'm"ga
// tushib qolmaydi. Default — so'm (UZS), prop kelguncha.
const currentCurrencySymbol = ref("so'm");

export function setCurrencySymbol(symbol: string): void {
    if (symbol !== '') {
        currentCurrencySymbol.value = symbol;
    }
}

export function currencySymbol(): string {
    return currentCurrencySymbol.value;
}

export function formatMoneySum(amount: number, symbol: string = currentCurrencySymbol.value): string {
    return formatMoney(amount) + ' ' + symbol;
}

const MONTH_LABELS = ['Yan', 'Fev', 'Mar', 'Apr', 'May', 'Iyun', 'Iyul', 'Avg', 'Sen', 'Okt', 'Noy', 'Dek'] as const;

export function formatMonth(ym: string): string {
    if (!ym) {
return '';
}

    const [y, m] = ym.split('-');
    const idx = parseInt(m ?? '', 10) - 1;

    if (idx < 0 || idx > 11) {
return ym;
}

    return `${MONTH_LABELS[idx]} ${(y ?? '').slice(2)}`;
}
