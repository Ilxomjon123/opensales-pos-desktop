import { useI18n } from 'vue-i18n';

/**
 * Mahsulot birligini (ProductUnit) joriy tilga moslab ko'rsatadi:
 * "dona" → uz "dona" / ru "шт" / en "pcs". Noma'lum birlik xom holicha qaytadi.
 */
export function useUnitLabel() {
    const { t, te } = useI18n();

    return (unit?: string | null): string => {
        if (!unit) {
            return '';
        }

        const key = `enums.ProductUnit.${unit}`;

        return te(key) ? t(key) : unit;
    };
}
