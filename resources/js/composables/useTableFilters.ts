import { router } from '@inertiajs/vue3';
import { ref  } from 'vue';
import type {Ref} from 'vue';

export type SortDirection = 'asc' | 'desc';

export type SortState = {
    column: string;
    direction: SortDirection;
};

type Options<F extends Record<string, unknown>> = {
    url: string;
    initialFilters: F;
    initialSort?: SortState;
    defaultSortColumn?: string;
    exportUrl?: string;
};

export function useTableFilters<F extends Record<string, unknown>>(options: Options<F>) {
    const filters = ref({ ...options.initialFilters }) as Ref<F>;
    const sortColumn = ref(options.initialSort?.column ?? options.defaultSortColumn ?? 'created_at');
    const sortDirection = ref<SortDirection>(options.initialSort?.direction ?? 'desc');

    const buildPayload = (extra: Record<string, unknown> = {}): Record<string, unknown> => ({
        ...filters.value,
        sort: sortColumn.value,
        direction: sortDirection.value,
        ...extra,
    });

    const apply = (extra: Record<string, unknown> = {}) => {
        router.get(options.url, buildPayload(extra) as Record<string, string | number>, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    // Search/date inputlar uchun: foydalanuvchi yozayotganda har bosishga
    // request ketmasin, oxirgi tugashidan 300ms keyin yuboramiz.
    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    const applyDebounced = (extra: Record<string, unknown> = {}, delay = 300) => {
        if (debounceTimer) {
clearTimeout(debounceTimer);
}

        debounceTimer = setTimeout(() => apply(extra), delay);
    };

    const reset = () => {
        filters.value = { ...options.initialFilters };
        Object.keys(filters.value as Record<string, unknown>).forEach((key) => {
            (filters.value as Record<string, unknown>)[key] = undefined;
        });
        sortColumn.value = options.defaultSortColumn ?? 'created_at';
        sortDirection.value = 'desc';
        apply();
    };

    const toggleSort = (column: string) => {
        if (sortColumn.value === column) {
            sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        } else {
            sortColumn.value = column;
            sortDirection.value = 'desc';
        }

        apply();
    };

    const goToPage = (page: number) => {
        router.get(options.url, buildPayload({ page }) as Record<string, string | number>, {
            preserveState: true,
            preserveScroll: false,
        });
    };

    // Sahifa tugmasiga hover paytida fonda ma'lumotni oldindan keshlash.
    // Cache 30s "fresh", keyingi 30s "stale-while-revalidate".
    const prefetchPage = (page: number) => {
        router.prefetch(
            options.url,
            { method: 'get', data: buildPayload({ page }) as Record<string, string | number> },
            { cacheFor: ['30s', '1m'] },
        );
    };

    const exportCsv = () => {
        if (!options.exportUrl) {
return;
}

        const params = new URLSearchParams();
        Object.entries(filters.value as Record<string, unknown>).forEach(([k, v]) => {
            if (v === undefined || v === null || v === '') {
                return;
            }
            if (Array.isArray(v)) {
                v.forEach((item) => {
                    if (item !== null && item !== undefined && item !== '') {
                        params.append(`${k}[]`, String(item));
                    }
                });

                return;
            }
            params.set(k, String(v));
        });
        const qs = params.toString();
        window.location.href = options.exportUrl + (qs ? '?' + qs : '');
    };

    return {
        filters,
        sortColumn,
        sortDirection,
        apply,
        applyDebounced,
        reset,
        toggleSort,
        goToPage,
        prefetchPage,
        exportCsv,
    };
}
