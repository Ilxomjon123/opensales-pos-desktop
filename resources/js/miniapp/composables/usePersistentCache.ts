/**
 * localStorage da TTL bilan kesh — stale-while-revalidate pattern uchun.
 * Mini app sessiya orasida ham tezda ko'rsatish imkonini beradi.
 */

const PREFIX = 'miniapp:cache:';

type Entry<T> = { ts: number; data: T };

export function readCache<T>(key: string, ttlMs: number): T | null {
    try {
        const raw = localStorage.getItem(PREFIX + key);

        if (!raw) {
return null;
}

        const parsed = JSON.parse(raw) as Entry<T>;

        if (typeof parsed?.ts !== 'number') {
return null;
}

        if (Date.now() - parsed.ts > ttlMs) {
return null;
}

        return parsed.data;
    } catch {
        return null;
    }
}

export function writeCache<T>(key: string, data: T): void {
    try {
        const entry: Entry<T> = { ts: Date.now(), data };
        localStorage.setItem(PREFIX + key, JSON.stringify(entry));
    } catch {
        /* quota exceeded yoki Safari private mode — jim o'tkazib yuboramiz */
    }
}

export function deleteCache(key: string): void {
    try {
        localStorage.removeItem(PREFIX + key);
    } catch { /* */ }
}
