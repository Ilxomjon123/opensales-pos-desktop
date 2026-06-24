import type { InertiaLinkProps } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import type { ComputedRef, DeepReadonly } from 'vue';
import { computed, readonly } from 'vue';
import { toUrl } from '@/lib/utils';

export type UseCurrentUrlReturn = {
    currentUrl: DeepReadonly<ComputedRef<string>>;
    isCurrentUrl: (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
        startsWith?: boolean,
    ) => boolean;
    isCurrentOrParentUrl: (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) => boolean;
    whenCurrentUrl: <T, F = null>(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: T,
        ifFalse?: F,
    ) => T | F;
};

const page = usePage();
const currentUrlReactive = computed(() => {
    const base = typeof window !== 'undefined' ? window.location.origin : 'http://localhost';

    return new URL(page.url, base);
});

function parseUrl(raw: string): URL {
    if (raw.startsWith('http')) {
        return new URL(raw);
    }

    return new URL(raw, 'http://localhost');
}

function queryMatches(target: URL, current: URL): boolean {
    for (const [key, value] of target.searchParams.entries()) {
        if (current.searchParams.get(key) !== value) {
            return false;
        }
    }

    return true;
}

export function useCurrentUrl(): UseCurrentUrlReturn {
    function isCurrentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
        startsWith: boolean = false,
    ) {
        const urlString = toUrl(urlToCheck);
        const target = parseUrl(urlString);
        const current = currentUrl !== undefined
            ? parseUrl(currentUrl)
            : currentUrlReactive.value;

        const pathOk = startsWith
            ? current.pathname.startsWith(target.pathname)
            : current.pathname === target.pathname;

        if (! pathOk) {
            return false;
        }

        return queryMatches(target, current);
    }

    function isCurrentOrParentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) {
        return isCurrentUrl(urlToCheck, currentUrl, true);
    }

    function whenCurrentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: any,
        ifFalse: any = null,
    ) {
        return isCurrentUrl(urlToCheck) ? ifTrue : ifFalse;
    }

    return {
        currentUrl: readonly(currentUrlReactive),
        isCurrentUrl,
        isCurrentOrParentUrl,
        whenCurrentUrl,
    };
}
