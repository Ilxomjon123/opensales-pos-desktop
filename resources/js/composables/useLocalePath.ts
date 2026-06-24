import { useLocale } from '@/composables/useLocale';

/**
 * Landing havolalari uchun til prefiksini qo'shadi: uz (default) prefiksiz,
 * qolgan tillarda `/<locale>` bilan. Foydalanish: `lp('/blog')` → `/ru/blog`.
 */
export function useLocalePath() {
    const { current } = useLocale();

    const lp = (path: string): string => {
        if (current.value === 'uz') {
            return path;
        }

        return path === '/' ? `/${current.value}` : `/${current.value}${path}`;
    };

    return { lp };
}
