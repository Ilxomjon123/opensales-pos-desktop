// Kirill (ruscha + o'zbekcha) → lotin transliteratsiya. Qidiruv ixtiyoriy
// alifboda ishlashi uchun: "moskva" ↔ "Москва", "fargona" ↔ "Farg'ona".

const MAP: Record<string, string> = {
    // Ruscha
    а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'yo', ж: 'zh', з: 'z',
    и: 'i', й: 'y', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r',
    с: 's', т: 't', у: 'u', ф: 'f', х: 'h', ц: 'ts', ч: 'ch', ш: 'sh',
    щ: 'sch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya',
    // O'zbekcha kirill qo'shimchalari
    ў: 'o', қ: 'q', ғ: 'g', ҳ: 'h',
};

const APOSTROPHE = /['ʻʼ`´‘’]/;

export type NormalizedText = { norm: string; map: number[] };

/**
 * Matnni qidiruv uchun normallashtiradi: kichik harf, kirill→lotin,
 * apostrof olib tashlanadi. `map[i]` — norm[i] hosil bo'lgan asl indeks
 * (highlight diapazonini asl matnga qaytarish uchun).
 */
export function translitNormalize(input: string): NormalizedText {
    const lower = input.toLowerCase();
    let norm = '';
    const map: number[] = [];

    for (let i = 0; i < lower.length; i++) {
        const ch = lower[i];

        if (APOSTROPHE.test(ch)) {
            continue;
        }

        const rep = MAP[ch] ?? ch;

        for (const c of rep) {
            norm += c;
            map.push(i);
        }
    }

    return { norm, map };
}

/**
 * `query` `text` ichida (alifbodan qat'i nazar) mos kelsa, asl matndagi
 * [start, end) diapazonini qaytaradi; aks holda null.
 */
export function matchRange(text: string, query: string): [number, number] | null {
    const q = translitNormalize(query).norm;

    if (q === '') {
        return null;
    }

    const { norm, map } = translitNormalize(text);
    const idx = norm.indexOf(q);

    if (idx === -1) {
        return null;
    }

    const start = map[idx];
    const end = map[idx + q.length - 1] + 1;

    return [start, end];
}

/**
 * Alifbodan qat'i nazar moslik bor-yo'qligi.
 */
export function translitMatches(text: string, query: string): boolean {
    return matchRange(text, query) !== null;
}
