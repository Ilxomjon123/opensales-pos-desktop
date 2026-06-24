export function getTg() {
    return (window as any).Telegram?.WebApp;
}

function getDealerId(): number {
    return Number(document.getElementById('miniapp')?.dataset.dealerId ?? 0);
}

export function getDealerVisibility(): 'private' | 'public' {
    const v = document.getElementById('miniapp')?.dataset.dealerVisibility;

    return v === 'public' ? 'public' : 'private';
}

// Tanlangan mijoz (mini app davomida almashtirilsa ham so'rov header'iga tushadi)
let activeShopId: number | null = null;

export function setActiveShopId(shopId: number | null): void {
    activeShopId = shopId;
}

export function getActiveShopId(): number | null {
    return activeShopId;
}

export function lastShopStorageKey(dealerId: number): string {
    return `miniapp:last-shop:${dealerId}`;
}

/**
 * Telegram user ID olish (3 ta manba, birinchi topilganini ishlatadi):
 * 1. Telegram.WebApp.initData (InlineKeyboard WebApp)
 * 2. Telegram.WebApp.initDataUnsafe.user.id
 * 3. URL query param ?tg_id= (botdan yuborilgan)
 */
function getAuthParam(): string {
    const tg = getTg();

    const initData = tg?.initData ?? '';

    if (initData) {
        return `_auth=${encodeURIComponent(initData)}`;
    }

    const unsafeId = tg?.initDataUnsafe?.user?.id;

    if (unsafeId) {
        return `_tg_id=${unsafeId}`;
    }

    const urlParams = new URLSearchParams(window.location.search);
    const tgIdFromUrl = urlParams.get('tg_id');

    if (tgIdFromUrl) {
        return `_tg_id=${tgIdFromUrl}`;
    }

    return '';
}

/**
 * Telegram platformasiga qarab tegishli native shrift stack'ini tanlaydi.
 * Telegram WebApp shrift API bermaydi — har platformada o'sha klient
 * ishlatadigan system shrift bilan moslashtirib qo'yamiz.
 */
function telegramFontStack(platform: string | undefined): string {
    const emoji = `'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji'`;

    switch (platform) {
        case 'ios':
        case 'macos':
            return `-apple-system, BlinkMacSystemFont, 'SF Pro Text', 'SF Pro Display', 'Helvetica Neue', sans-serif, ${emoji}`;
        case 'android':
            return `Roboto, 'Noto Sans', 'Helvetica Neue', sans-serif, ${emoji}`;
        case 'tdesktop':
            return `system-ui, 'Segoe UI', 'Open Sans', Roboto, sans-serif, ${emoji}`;
        case 'weba':
        case 'webk':
        case 'web':
        default:
            return `system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif, ${emoji}`;
    }
}

/**
 * Telegram klientining native shriftini mini app ga qo'llaydi.
 * `--font-sans` ni inline style sifatida o'rnatadi — bu Tailwind v4
 * `@theme` qiymatidan ustun bo'ladi, shu sababli barcha `font-sans`
 * utility lari Telegram shriftiga moslashadi.
 */
function applyTelegramFont(): void {
    const tg = getTg();
    const platform = (tg?.platform as string | undefined) ?? undefined;
    const stack = telegramFontStack(platform);

    document.documentElement.style.setProperty('--font-sans', stack);
    document.documentElement.style.fontFamily = stack;
    document.body.style.fontFamily = stack;
}

/**
 * Telegram themeParams → CSS variables.
 * Shunda butun mini app (Tailwind utility larigacha) foydalanuvchi
 * Telegram da tanlagan rang palitrasiga moslashadi.
 */
function applyTelegramTheme(): void {
    const tg = getTg();

    if (!tg) {
return;
}

    const params = (tg.themeParams ?? {}) as Record<string, string | undefined>;
    const root = document.documentElement;
    const scheme = (tg.colorScheme as 'light' | 'dark' | undefined) ?? 'light';

    // Tailwind `dark:` variantlari uchun
    root.classList.toggle('dark', scheme === 'dark');

    const setVar = (name: string, value: string | undefined): void => {
        if (value) {
root.style.setProperty(name, value);
}
    };

    const bg = params.bg_color;
    const text = params.text_color;
    const hint = params.hint_color;
    const link = params.link_color;
    const btn = params.button_color;
    const btnText = params.button_text_color;
    const secondaryBg = params.secondary_bg_color ?? params.section_bg_color;
    const destructive = params.destructive_text_color;
    const accent = params.accent_text_color ?? link;

    setVar('--background', bg);
    setVar('--foreground', text);

    setVar('--card', secondaryBg ?? bg);
    setVar('--card-foreground', text);

    setVar('--popover', secondaryBg ?? bg);
    setVar('--popover-foreground', text);

    setVar('--primary', btn);
    setVar('--primary-foreground', btnText);

    setVar('--secondary', secondaryBg);
    setVar('--secondary-foreground', text);

    setVar('--muted', secondaryBg);
    setVar('--muted-foreground', hint);

    setVar('--accent', secondaryBg);
    setVar('--accent-foreground', accent);

    setVar('--destructive', destructive);
    setVar('--destructive-foreground', btnText ?? '#ffffff');

    setVar('--ring', btn ?? link);

    // Border va input — Telegram ochiq bermaydi, hint dan shaffof derivatsiya
    if (hint && /^#[0-9a-fA-F]{6}$/.test(hint)) {
        const alpha = scheme === 'dark' ? '33' : '26';
        setVar('--border', `${hint}${alpha}`);
        setVar('--input', `${hint}${alpha}`);
    }

    // Fon — butun sahifa Telegram bg rangida
    if (bg) {
document.body.style.backgroundColor = bg;
}
}

export function useMiniApp() {
    const tg = getTg();
    const dealerId = getDealerId();

    function init() {
        applyTelegramFont();

        if (tg) {
            tg.ready();
            tg.expand();
            tg.enableClosingConfirmation();
            applyTelegramTheme();
            tg.onEvent?.('themeChanged', applyTelegramTheme);
        } else {
            // Telegram webview tashqarisida (masalan brauzerda test) — oddiy palitra
            applyTelegramTheme();
        }
    }

    return { tg, init, dealerId };
}

export function useApi() {
    async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
        const dealerId = getDealerId();
        const authParam = getAuthParam();
        const separator = path.includes('?') ? '&' : '?';
        const url = `/api/miniapp/${dealerId}${path}${authParam ? separator + authParam : ''}`;

        const headers: Record<string, string> = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...(options.headers as Record<string, string> | undefined ?? {}),
        };

        if (activeShopId) {
            headers['X-Shop-Id'] = String(activeShopId);
        }

        const res = await fetch(url, { ...options, headers });

        if (!res.ok) {
            const err = await res.json().catch(() => ({ message: 'Xatolik yuz berdi' }));

            throw new Error(err.message);
        }

        return res.json();
    }

    const get = <T>(path: string) => request<T>(path);
    const post = <T>(path: string, body?: unknown) => request<T>(path, {
        method: 'POST',
        body: body ? JSON.stringify(body) : undefined,
    });
    const patch = <T>(path: string, body?: unknown) => request<T>(path, {
        method: 'PATCH',
        body: body ? JSON.stringify(body) : undefined,
    });
    const del = <T>(path: string, body?: unknown) => request<T>(path, {
        method: 'DELETE',
        body: body ? JSON.stringify(body) : undefined,
    });

    return { get, post, patch, del };
}
