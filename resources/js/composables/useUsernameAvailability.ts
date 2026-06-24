import { ref, watch, type Ref } from 'vue';

export type UsernameStatus = 'idle' | 'short' | 'invalid' | 'checking' | 'available' | 'taken';

type Options = {
    /** User ID to ignore (for edit pages). */
    ignoreId?: number | null;
    /** Initial value — skip check if equal (avoids "taken by self" on Edit page first load). */
    initialValue?: string;
    /** Debounce in ms. */
    debounce?: number;
    /** Endpoint to check against (default: authenticated /api/username-availability). */
    endpoint?: string;
};

const USERNAME_REGEX = /^[A-Za-z0-9._@-]+$/;
const MIN_LENGTH = 5;

export function useUsernameAvailability(username: Ref<string>, options: Options = {}) {
    const status = ref<UsernameStatus>('idle');
    const initial = options.initialValue ?? '';
    const debounceMs = options.debounce ?? 500;
    const endpoint = options.endpoint ?? '/api/username-availability';

    let timer: ReturnType<typeof setTimeout> | null = null;
    let abort: AbortController | null = null;

    watch(username, (value) => {
        if (timer) {
            clearTimeout(timer);
        }
        if (abort) {
            abort.abort();
            abort = null;
        }

        const trimmed = value.trim();

        if (trimmed === '' || trimmed === initial) {
            status.value = 'idle';

            return;
        }

        if (trimmed.length < MIN_LENGTH) {
            status.value = 'short';

            return;
        }

        if (!USERNAME_REGEX.test(trimmed)) {
            status.value = 'invalid';

            return;
        }

        status.value = 'checking';

        timer = setTimeout(async () => {
            abort = new AbortController();
            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set('username', trimmed);

            if (options.ignoreId != null) {
                url.searchParams.set('ignore', String(options.ignoreId));
            }

            try {
                const res = await fetch(url.toString(), {
                    signal: abort.signal,
                    headers: { Accept: 'application/json' },
                });
                const data = await res.json();
                status.value = data.status as UsernameStatus;
            } catch (e) {
                if ((e as Error).name === 'AbortError') {
                    return;
                }
                status.value = 'idle';
            }
        }, debounceMs);
    });

    return { status };
}
