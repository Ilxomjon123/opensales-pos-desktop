/**
 * Telegram <-> Server proxy (ikki tomonlama).
 *
 * Sabab: server Rossiyada, Telegram bloklangan. Worker Cloudflare da ishlaydi
 * va ikkala yo'nalish uchun ham proxy bo'ladi.
 *
 * Trafik yo'nalishlari:
 *
 *   1) Telegram -> Worker -> Server (webhook)
 *      Telegram so'rovi: POST https://<worker>/webhook/{dealer}
 *      Worker uni serverga uzatadi:
 *        POST {ORIGIN_URL}/webhook/{dealer}
 *      Header X-Telegram-Bot-Api-Secret-Token saqlab qolinadi
 *      (server VerifyTelegramWebhook middleware orqali tekshiradi).
 *
 *   2) Server -> Worker -> Telegram (Bot API + file download)
 *      Server so'rovi: ANY https://<worker>/bot{token}/{method}
 *                       yoki  https://<worker>/file/bot{token}/{path}
 *      Worker tegishli api.telegram.org endpointiga uzatadi.
 *      Server X-Proxy-Secret header yuborishi shart (PROXY_SHARED_SECRET bilan tekshiriladi).
 *
 * Sozlanmalar (Cloudflare Worker -> Settings -> Variables):
 *   ORIGIN_URL            (Secret) — masalan: https://opensales.uz
 *   PROXY_SHARED_SECRET   (Secret) — server -> worker yo'nalishi uchun maxfiy kalit
 */

const TELEGRAM_API = 'https://api.telegram.org';

// Forward qilinmaydigan headerlar — CF runtime o'zi boshqaradi yoki forward
// qilinsa "error 1101" ga olib keladi.
const HOP_BY_HOP = new Set([
  'host',
  'content-length',
  'connection',
  'keep-alive',
  'transfer-encoding',
  'upgrade',
  'proxy-authenticate',
  'proxy-authorization',
  'te',
  'trailers',
  'cf-connecting-ip',
  'cf-ipcountry',
  'cf-ray',
  'cf-visitor',
  'x-real-ip',
  'x-forwarded-host',
]);

export default {
  async fetch(request, env, ctx) {
    try {
      const url = new URL(request.url);
      const path = url.pathname;

      if (path.startsWith('/webhook/')) {
        return await forwardWebhookToOrigin(request, url, env);
      }

      if (path.startsWith('/bot') || path.startsWith('/file/bot')) {
        return await forwardToTelegram(request, url, env);
      }

      if (path === '/' || path === '/health') {
        return new Response('ok', { status: 200 });
      }

      return new Response('Not found', { status: 404 });
    } catch (err) {
      return new Response(
        'Worker error: ' + (err && err.message ? err.message : String(err)),
        { status: 502 }
      );
    }
  },
};

function cleanHeaders(source, extraDelete = []) {
  const headers = new Headers();
  for (const [key, value] of source.entries()) {
    const lower = key.toLowerCase();
    if (HOP_BY_HOP.has(lower)) continue;
    if (extraDelete.includes(lower)) continue;
    headers.set(key, value);
  }
  return headers;
}

async function forwardWebhookToOrigin(request, url, env) {
  let origin = (env.ORIGIN_URL || '').trim().replace(/\/+$/, '');

  if (!origin) {
    return new Response('ORIGIN_URL not configured', { status: 500 });
  }

  // Sxema yo'q bo'lsa default https:// qo'shamiz.
  if (!/^https?:\/\//i.test(origin)) {
    origin = 'https://' + origin;
  }

  if (request.method !== 'POST') {
    return new Response('Method not allowed', { status: 405 });
  }

  let target;

  try {
    target = new URL(origin);
  } catch {
    return new Response(
      `ORIGIN_URL is not a valid URL (got: "${origin}")`,
      { status: 500 }
    );
  }

  target.pathname = url.pathname;
  target.search = url.search;

  const headers = cleanHeaders(request.headers);
  headers.set('X-Forwarded-For', request.headers.get('CF-Connecting-IP') || '');
  headers.set('X-Forwarded-Proto', 'https');

  // Body'ni avval o'qib olish — streaming forward CF runtime'da `duplex: 'half'`
  // talab qiladi va ba'zan "error 1101" beradi. ArrayBuffer eng ishonchli yo'l.
  const body = await request.arrayBuffer();

  return fetch(target.toString(), {
    method: 'POST',
    headers,
    body,
  });
}

async function forwardToTelegram(request, url, env) {
  if (env.PROXY_SHARED_SECRET) {
    const provided = request.headers.get('X-Proxy-Secret');

    if (provided !== env.PROXY_SHARED_SECRET) {
      return new Response('Forbidden', { status: 403 });
    }
  }

  const target = TELEGRAM_API + url.pathname + url.search;

  const headers = cleanHeaders(request.headers, ['x-proxy-secret']);

  const init = { method: request.method, headers };

  if (request.method !== 'GET' && request.method !== 'HEAD') {
    init.body = await request.arrayBuffer();
  }

  return fetch(target, init);
}
