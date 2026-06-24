function pad(n: number): string {
    return String(n).padStart(2, '0');
}

function parse(input: string | number | Date | null | undefined): Date | null {
    if (input === null || input === undefined || input === '') {
return null;
}

    const d = input instanceof Date ? input : new Date(input);

    return isNaN(d.getTime()) ? null : d;
}

export function formatDate(input: string | number | Date | null | undefined, fallback = '—'): string {
    const d = parse(input);

    if (!d) {
return fallback;
}

    return `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()}`;
}

export function formatDateTime(input: string | number | Date | null | undefined, fallback = '—'): string {
    const d = parse(input);

    if (!d) {
return fallback;
}

    return `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function formatUnix(seconds: number | null | undefined, fallback = '—'): string {
    if (!seconds) {
return fallback;
}

    return formatDateTime(seconds * 1000, fallback);
}
